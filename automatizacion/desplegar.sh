#!/usr/bin/env bash
# Lado de GitHub Actions del despliegue de SQL. Se corre desde la raíz de un checkout de la rama DeployBacklog.
#
#   bash automatizacion/desplegar.sh ensayo        arma una MariaDB 11.8 desechable (Docker) con esquema/produccion,
#                                                  las entregas desplegadas y TODAS las pendientes; falla si alguna no corre
#   bash automatizacion/desplegar.sh listas        imprime qué entregas de pendiente/ están listas para producción
#   bash automatizacion/desplegar.sh revisar       entra al servidor y revisa cliente, .env, conexión y permisos (no cambia nada)
#   bash automatizacion/desplegar.sh produccion    aplica las entregas listas en producción y las mueve a desplegado/
#
# Una entrega está LISTA si no tiene espera.txt, o si cada línea de su espera.txt ("<repo> <rama>", p. ej.
# "ColeDelBosque-AppWeb mi-rama") ya está integrada en master de ese repo (o su PR ya se hizo merge, aunque la rama se haya borrado).
#
# Variables (secretos del repo en la Action):
#   SSH_HOST SSH_PORT SSH_USER SSH_KEY SSH_KNOWN_HOSTS   acceso SSH a Hostinger
#   RUTA_SITIO        carpeta del sitio del colegio desde el home (variable del repo, OBLIGATORIA: no hay default para
#                     no tocar nunca la base de otro sitio); su .env trae las credenciales DB_*
#   GH_TOKEN          para revisar espera.txt con la API de GitHub (y para el push a DeployBacklog)
#   DUENO             dueño de los repos (default NeuralStack-Code)
#   RAMA              rama del SQL (default DeployBacklog)
#   ENSAYO=0          en "produccion", no repetir el ensayo (la Action ya lo corrió con este commit)
set -euo pipefail

MODO="${1:-}"
RAIZ="$(pwd)"
[ -d "$RAIZ/pendiente" ] && [ -f "$RAIZ/automatizacion/servidor.sh" ] || { echo "Correr desde la raíz de DeployBacklog." >&2; exit 2; }
DUENO="${DUENO:-NeuralStack-Code}"
RAMA="${RAMA:-DeployBacklog}"
RUTA_SITIO="${RUTA_SITIO:-}"
CORRIDA="${CORRIDA:-${GITHUB_RUN_ID:-local-$(date +%s)}}"
URL_CORRIDA="${URL_CORRIDA:-${GITHUB_SERVER_URL:-https://github.com}/${GITHUB_REPOSITORY:-?}/actions/runs/${GITHUB_RUN_ID:-?}}"
TMP="$(mktemp -d)"
trap 'ssh -o ControlPath="$TMP/cm" -O exit "${SSH_USER:-x}@${SSH_HOST:-x}" >/dev/null 2>&1 || true; rm -rf "$TMP"' EXIT

titulo() { echo; echo "── $* ──"; }
pendientes() { find pendiente -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | LC_ALL=C sort; }

# --- ¿Ya está la rama integrada en master? ----------------------------------------------------------------------------
rama_integrada() { # rama_integrada <repo> <rama>
  local repo="$1" rama="$2" estado n
  estado="$(gh api "repos/$DUENO/$repo/compare/master...$rama" --jq .status 2>/dev/null || true)"
  case "$estado" in identical|behind) return 0 ;; ahead|diverged) return 1 ;; esac
  # La rama ya no existe (se borró al hacer merge): basta un PR suyo que se haya unido.
  n="$(gh api "repos/$DUENO/$repo/pulls?state=closed&head=$DUENO:$rama&per_page=100" \
        --jq '[.[] | select(.merged_at != null)] | length' 2>/dev/null || echo 0)"
  [ "${n:-0}" -gt 0 ]
}
esta_lista() { # esta_lista <entrega>   (escribe el motivo en stderr cuando no)
  local f="pendiente/$1/espera.txt" repo rama
  [ -f "$f" ] || return 0
  while read -r repo rama _; do
    case "$repo" in ''|'#'*) continue ;; esac
    [ -n "${rama:-}" ] || { echo "   $1: espera.txt mal escrito ('$repo' sin rama)" >&2; return 1; }
    if ! rama_integrada "$repo" "$rama"; then echo "   $1: espera a que $repo/$rama llegue a master" >&2; return 1; fi
  done < <(tr -d '\r' < "$f")
  return 0
}
listas() { local e; for e in $(pendientes); do esta_lista "$e" && echo "$e"; done; return 0; }

# --- SSH ----------------------------------------------------------------------------------------------------------------
preparar_ssh() {
  : "${SSH_HOST:?falta el secreto SSH_HOST}" "${SSH_USER:?falta el secreto SSH_USER}" "${SSH_KEY:?falta el secreto SSH_KEY}"
  : "${SSH_KNOWN_HOSTS:?falta el secreto SSH_KNOWN_HOSTS (huella del servidor)}"
  : "${RUTA_SITIO:?falta la variable RUTA_SITIO (carpeta del sitio del colegio, p. ej. domains/<dominio>/public_html)}"
  ( umask 077; printf '%s\n' "$SSH_KEY" | tr -d '\r' > "$TMP/llave"; printf '%s\n' "$SSH_KNOWN_HOSTS" | tr -d '\r' > "$TMP/known_hosts" )
  SSH=(ssh -p "${SSH_PORT:-22}" -i "$TMP/llave" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes
       -o UserKnownHostsFile="$TMP/known_hosts" -o ConnectTimeout=20 -o ServerAliveInterval=30
       # Hostinger a veces no contesta el primer intento: se reintenta y se reutiliza una sola conexión por corrida.
       -o ConnectionAttempts=4 -o ControlMaster=auto -o ControlPath="$TMP/cm" -o ControlPersist=120
       "$SSH_USER@$SSH_HOST")
}
ruta_remota() { case "$RUTA_SITIO" in /*) printf '%q' "$RUTA_SITIO" ;; *) printf '"$HOME"/%q' "$RUTA_SITIO" ;; esac; }
# Manda el script (+ entregas) en un tar por la misma conexión, lo corre allá y borra la carpeta temporal.
remoto() { # remoto <script.sh> <args…>   (stdin: el tar)
  local script="$1" args="" a; shift
  for a in "$@"; do args+=" $(printf '%q' "$a")"; done
  "${SSH[@]}" "d=\$(mktemp -d \"\$HOME/.sql-corrida.XXXXXX\") || exit 2
tar -xzf - -C \"\$d\" || { rm -rf \"\$d\"; exit 2; }
cd \"\$d\" && bash ./$script --env $(ruta_remota)/.env$args; rc=\$?
cd \"\$HOME\"; rm -rf \"\$d\"; exit \$rc"
}

# --- Mover a desplegado/ y subir a DeployBacklog (con reintentos si alguien más empujó) ---------------------------------
mover_y_subir() {
  local lista=("$@") intento e movidas origen
  origen="$(git rev-parse HEAD)"
  git config user.name  "${GIT_NOMBRE:-github-actions[bot]}"
  git config user.email "${GIT_CORREO:-41898282+github-actions[bot]@users.noreply.github.com}"
  for intento in 1 2 3 4 5; do
    git fetch --quiet origin "$RAMA"
    git reset --quiet --hard "origin/$RAMA"
    movidas=()
    for e in "${lista[@]}"; do
      [ -d "pendiente/$e" ] || continue          # otra corrida ya la movió
      if [ -e "desplegado/$e" ]; then echo "!! desplegado/$e ya existe; se deja en pendiente/ para revisarla" >&2; continue; fi
      mkdir -p desplegado
      git mv "pendiente/$e" "desplegado/$e"
      printf 'Aplicada en producción: %s\nCorrida: %s\nDisparo: %s\nCommit de DeployBacklog aplicado: %s\n' \
        "$(date -u '+%Y-%m-%d %H:%M UTC')" "$URL_CORRIDA" "${DISPARO:-manual}" "$origen" > "desplegado/$e/aplicado.txt"
      git add "desplegado/$e/aplicado.txt"
      movidas+=("$e")
    done
    [ ${#movidas[@]} -gt 0 ] || { echo "Nada que mover (ya estaban en desplegado/)."; return 0; }
    git commit --quiet -m "deploy: ${movidas[*]} aplicada(s) en producción [skip ci]" \
      -m "Movidas de pendiente/ a desplegado/ por la corrida $URL_CORRIDA"
    if git push --quiet origin "HEAD:$RAMA"; then echo "Movidas a desplegado/: ${movidas[*]}"; return 0; fi
    echo "   El push chocó con otro cambio; reintento $intento…" >&2; sleep $(( intento * 3 ))
  done
  echo "!! No se pudo subir el movimiento a $RAMA. Las entregas YA están aplicadas (quedan en Migraciones):" >&2
  echo "   la siguiente corrida las reconoce y las vuelve a intentar mover." >&2
  return 1
}

case "$MODO" in
# ------------------------------------------------------------------------------------------------------------------------
listas)
  titulo "Entregas listas para producción"
  L="$(listas)"; [ -n "$L" ] && echo "$L" || echo "(ninguna)"
  ;;
# ------------------------------------------------------------------------------------------------------------------------
revisar)
  preparar_ssh
  titulo "Revisión del servidor (sin cambios)"
  tar -C automatizacion -czf - servidor.sh | remoto servidor.sh --revisar
  ;;
# ------------------------------------------------------------------------------------------------------------------------
produccion)
  [ "${GITHUB_ACTIONS:-}" = true ] || [ "${PERMITIR_LOCAL:-}" = 1 ] || { echo "Solo corre en GitHub Actions." >&2; exit 2; }
  titulo "Entregas listas"
  mapfile -t LISTAS < <(listas)
  if [ ${#LISTAS[@]} -eq 0 ]; then echo "No hay entregas listas en pendiente/. Nada que aplicar."; exit 0; fi
  printf '   %s\n' "${LISTAS[@]}"
  # Antes de tocar producción, el ensayo (salvo que la Action ya lo haya corrido con este mismo commit).
  if [ "${ENSAYO:-1}" = 1 ]; then bash "$0" ensayo; fi
  mkdir -p "$TMP/paquete/entregas"
  cp automatizacion/servidor.sh "$TMP/paquete/"
  for e in "${LISTAS[@]}"; do cp -r "pendiente/$e" "$TMP/paquete/entregas/$e"; done
  preparar_ssh
  titulo "Aplicando en el servidor"
  set +e
  tar -C "$TMP/paquete" -czf - . | remoto servidor.sh --entregas ./entregas --respaldos '~/respaldos-sql' --corrida "$CORRIDA" > "$TMP/salida"
  RC=$?
  set -e
  grep '^@@ ' "$TMP/salida" | sed 's/^@@ /   /' || true
  mapfile -t APLICADAS < <(sed -n 's/^@@ LISTA //p' "$TMP/salida")
  if [ ${#APLICADAS[@]} -gt 0 ]; then titulo "Moviendo a desplegado/"; mover_y_subir "${APLICADAS[@]}" || RC=1; fi
  if [ "$RC" != 0 ]; then
    echo; echo "!! La corrida terminó con error ($RC). Lo que no se aplicó sigue en pendiente/; el respaldo está en ~/respaldos-sql."
    sed -n 's/^@@ ERROR /   /p' "$TMP/salida"
  fi
  exit "$RC"
  ;;
# ------------------------------------------------------------------------------------------------------------------------
ensayo)
  command -v docker >/dev/null || { echo "El ensayo necesita Docker." >&2; exit 2; }
  IMAGEN="${IMAGEN_ENSAYO:-mariadb:11.8}"
  N="ensayo-sql-$$"
  trap 'docker rm -f "$N" >/dev/null 2>&1 || true; rm -rf "$TMP"' EXIT
  titulo "Levantando $IMAGEN"
  docker run -d --name "$N" -e MARIADB_ROOT_PASSWORD=ensayo "$IMAGEN" >/dev/null
  for _ in $(seq 1 90); do docker exec "$N" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 && break; sleep 2; done
  docker exec "$N" healthcheck.sh --connect --innodb_initialized >/dev/null || { docker logs "$N" | tail -20; exit 1; }
  # Lo que se copia al contenedor: el script, los esquemas y las entregas. Desplegadas = solo las que el export de
  # producción todavía no trae (las posteriores a esquema/produccion/hasta.txt; sin ese archivo, todas).
  HASTA="$(tr -d '\r\n ' < esquema/produccion/hasta.txt 2>/dev/null || true)"
  mkdir -p "$TMP/ensayo/desplegado" "$TMP/ensayo/pendiente"
  [ -f esquema/produccion/colegio.sql ] || { echo "Falta esquema/produccion/colegio.sql (export de SOLO estructura de la base de producción). Sin él no hay ensayo y no se toca producción." >&2; exit 1; }
  cp automatizacion/servidor.sh esquema/produccion/colegio.sql "$TMP/ensayo/"
  # servidor.sh las corre por nombre. Las del mismo día se copian con un número, en el orden en que llegaron a
  # producción (primero las corridas a mano, sin aplicado.txt; luego por la fecha de su aplicado.txt): así una que
  # necesita a otra del mismo día no falla en el ensayo (2026-11-04: instalador-cliente necesita versiones-proyectos).
  dia=""; n=0
  for d in $(for c in desplegado/*/; do [ -d "$c" ] || continue; c="${c%/}"; e="${c#desplegado/}"   # sin entregas desplegadas, el patrón queda literal
               t="$(head -n1 "$c/aplicado.txt" 2>/dev/null | tr -d '\r' || true)"
               printf '%s\t%s\t%s\t%s\n' "${e:0:10}" "${t:+1}" "$t" "$e"; done | LC_ALL=C sort | cut -f4); do
    [[ -n "$HASTA" && ! "$d" > "$HASTA" ]] && continue
    if [ "${d:0:10}" = "$dia" ]; then n=$((n + 1)); else dia="${d:0:10}"; n=1; fi
    cp -r "desplegado/$d" "$TMP/ensayo/desplegado/${d:0:10}_$(printf '%02d' "$n")${d:10}"
  done
  for d in $(pendientes); do cp -r "pendiente/$d" "$TMP/ensayo/pendiente/"; done
  cat > "$TMP/ensayo/.env" <<'ENV'
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=ensayo
DB_NAME=ensayo_colegio
ENV
  docker exec "$N" mkdir -p /ensayo
  tar -C "$TMP/ensayo" -cf - . | docker exec -i "$N" tar -xf - -C /ensayo
  titulo "Estructura de producción (esquema/produccion)"
  docker exec -i "$N" bash -c 'set -e; cd /ensayo
    for b in colegio; do
      mariadb -uroot -pensayo -e "CREATE DATABASE ensayo_$b CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
      mariadb -uroot -pensayo ensayo_$b < $b.sql && echo "   $b cargada"
    done'
  titulo "Entregas ya desplegadas (${HASTA:+posteriores a $HASTA}${HASTA:-todas: falta esquema/produccion/hasta.txt})"
  docker exec "$N" bash /ensayo/servidor.sh --entregas /ensayo/desplegado --env /ensayo/.env --sin-respaldo --corrida ensayo | sed 's/^@@ /   /'
  titulo "Entregas pendientes"
  docker exec "$N" bash /ensayo/servidor.sh --entregas /ensayo/pendiente --env /ensayo/.env --sin-respaldo --corrida ensayo | sed 's/^@@ /   /'
  titulo "Segunda corrida de las pendientes (no debe volver a aplicar nada)"
  SALIDA2="$(docker exec "$N" bash /ensayo/servidor.sh --entregas /ensayo/pendiente --env /ensayo/.env --sin-respaldo --corrida ensayo 2>&1)"
  echo "$SALIDA2" | grep -q ' aplicado (' && { echo "$SALIDA2"; echo "!! La segunda corrida volvió a aplicar algo." >&2; exit 1; }
  echo "   Todo reconocido como ya aplicado."
  titulo "Ensayo correcto"
  ;;
*)
  sed -n '2,21p' "$0" | sed 's/^# \{0,1\}//'; exit 2 ;;
esac
