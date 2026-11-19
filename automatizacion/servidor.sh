#!/usr/bin/env bash
# Aplica entregas de DeployBacklog en la base del colegio. Corre DENTRO del servidor (Hostinger, por SSH) o dentro del contenedor
# del ensayo: se conecta a la base por localhost con el cliente de consola, que entiende DELIMITER igual que phpMyAdmin.
#
#   bash servidor.sh --entregas DIR --env ARCHIVO --respaldos DIR [--corrida ID]
#   bash servidor.sh --entregas DIR --env ARCHIVO --sin-respaldo            (solo el ensayo)
#   bash servidor.sh --revisar --env ARCHIVO                                (no cambia nada: revisa conexión y permisos)
#
#   --entregas   carpeta con las entregas a aplicar (una subcarpeta por entrega, AAAA-MM-DD_nombre). Se aplican en
#                orden de nombre; dentro de cada una, sus .sql en orden de nombre.
#   --env        .env del sitio: de ahí salen las credenciales (DB_HOST, DB_USER, DB_PASSWORD, DB_NAME).
#                Colegio del Bosque tiene UNA base ("colegio"): base.txt no se usa.
#   --respaldos  dónde guardar el respaldo de cada base antes de tocarla (se conservan los últimos 10 por base).
#   --corrida    identificador de la corrida (el run de GitHub); queda en Migraciones.RequestId.
#
# Qué garantiza:
#   - Un candado: dos corridas nunca aplican a la vez (aunque vengan de repos distintos).
#   - Tabla Migraciones en la base (9.9 del estándar): un archivo ya aplicado con la misma huella no se vuelve a
#     correr; si llega con otra huella, se detiene (lo desplegado no se cambia: va en una entrega nueva).
#   - Respaldo de la base antes del primer cambio. Si el respaldo falla, no se aplica nada.
#   - Al primer error se detiene: lo que sigue queda pendiente y el siguiente intento empieza en el archivo que falló.
#
# Salida para la Action (stdout, una línea por evento):
#   @@ LISTA <entrega>                      la entrega quedó completa: ya se puede mover a desplegado/
#   @@ ERROR <entrega> <archivo> <mensaje>
# Termina en 0 si todo quedó aplicado, 1 si algo falló, 2 si los argumentos o el entorno están mal.
set -uo pipefail

ENTREGAS=""; ENV_FILE=""; RESPALDOS=""; CORRIDA="manual"; SIN_RESPALDO=0; REVISAR=0
while [ $# -gt 0 ]; do
  case "$1" in
    --entregas)     ENTREGAS="$2"; shift 2 ;;
    --env)          ENV_FILE="$2"; shift 2 ;;
    --respaldos)    RESPALDOS="$2"; shift 2 ;;
    --corrida)      CORRIDA="$2"; shift 2 ;;
    --sin-respaldo) SIN_RESPALDO=1; shift ;;
    --revisar)      REVISAR=1; shift ;;
    *) echo "Opción desconocida: $1" >&2; exit 2 ;;
  esac
done
log()   { echo "   $*" >&2; }
falla() { echo "!! $*" >&2; exit 2; }
case "$RESPALDOS" in "~/"*) RESPALDOS="$HOME/${RESPALDOS#\~/}" ;; esac
[ -f "$ENV_FILE" ] || falla "--env no existe: $ENV_FILE"
[ "$REVISAR" = 1 ] || [ -d "$ENTREGAS" ] || falla "--entregas no es una carpeta: $ENTREGAS"
[ "$REVISAR" = 1 ] || [ "$SIN_RESPALDO" = 1 ] || [ -n "$RESPALDOS" ] || falla "falta --respaldos (o --sin-respaldo, solo para el ensayo)"

# Cliente de consola: MariaDB lo llama mariadb / mariadb-dump; en servidores viejos, mysql / mysqldump.
CLIENTE="$(command -v mariadb || command -v mysql || true)"
VOLCADO="$(command -v mariadb-dump || command -v mysqldump || true)"
[ -n "$CLIENTE" ] || falla "no está el cliente mariadb/mysql en este servidor"
[ "$SIN_RESPALDO" = 1 ] || [ -n "$VOLCADO" ] || falla "no está mariadb-dump/mysqldump para el respaldo"

TMP="$(mktemp -d)"; chmod 700 "$TMP"
trap 'rm -rf "$TMP"' EXIT

# --- Candado ---------------------------------------------------------------------------------------------------------
CANDADO="${HOME:-/tmp}/.sql-produccion.lock"
if command -v flock >/dev/null 2>&1; then
  exec 9>"$CANDADO"
  if ! flock -n 9; then
    log "Otra corrida está aplicando; esperando el candado…"
    flock -w 900 9 || falla "otra corrida tiene el candado desde hace más de 15 minutos"
  fi
else
  for _ in $(seq 1 180); do mkdir "$CANDADO.d" 2>/dev/null && break; sleep 5; done
  [ -d "$CANDADO.d" ] || falla "no se pudo tomar el candado $CANDADO.d"
  trap 'rm -rf "$TMP"; rmdir "$CANDADO.d" 2>/dev/null' EXIT
fi

# --- Credenciales desde el .env (sin ejecutarlo: solo se leen las líneas CLAVE=valor) --------------------------------
leer_env() {
  local v
  v="$(grep -E "^[[:space:]]*$1[[:space:]]*=" "$ENV_FILE" | tail -n1 | sed -E 's/^[^=]*=[[:space:]]*//; s/\r$//; s/[[:space:]]+$//')"
  case "$v" in \"*\") v="${v#\"}"; v="${v%\"}" ;; \'*\') v="${v#\'}"; v="${v%\'}" ;; esac
  printf '%s' "$v"
}
esc_cnf() { printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'; }
# Por base: archivo de opciones (chmod 600) para no poner la contraseña en la línea de comandos, y el nombre de la base.
preparar_base() {
  local base="$1" suf="" host user pass name
  host="$(leer_env "DB_HOST$suf")"; user="$(leer_env "DB_USER$suf")"
  pass="$(leer_env "DB_PASSWORD$suf")"; name="$(leer_env "DB_NAME$suf")"
  [ -n "$user" ] && [ -n "$name" ] || falla "el .env no trae DB_USER$suf / DB_NAME$suf"
  ( umask 077
    { echo "[client]"
      echo "host=\"$(esc_cnf "${host:-localhost}")\""
      echo "user=\"$(esc_cnf "$user")\""
      echo "password=\"$(esc_cnf "$pass")\""
      echo "default-character-set=utf8mb4"
    } > "$TMP/$base.cnf" )
  printf '%s' "$name" > "$TMP/$base.nombre"
}
sql() { # sql <base> [args…]   (el SQL entra por stdin)
  local base="$1"; shift
  "$CLIENTE" --defaults-extra-file="$TMP/$base.cnf" --batch --skip-column-names "$@" "$(cat "$TMP/$base.nombre")"
}
literal() { printf "'%s'" "$(printf '%s' "$1" | sed "s/\\\\/\\\\\\\\/g; s/'/''/g")"; }

asegurar_tabla() {
  sql "$1" <<'SQL'
CREATE TABLE IF NOT EXISTS Migraciones (
    IdMigracion       INT NOT NULL AUTO_INCREMENT,
    Entrega           VARCHAR(80)  NOT NULL,
    Archivo           VARCHAR(120) NOT NULL,
    Sha256            CHAR(64)     NOT NULL,
    Resultado         ENUM('aplicado','error') NOT NULL,
    Mensaje           VARCHAR(1000) NOT NULL DEFAULT '',
    DuracionMs        INT NOT NULL DEFAULT 0,
    RequestId         CHAR(16) NOT NULL DEFAULT '',
    FechaRegistro     DATETIME DEFAULT CURRENT_TIMESTAMP,
    IdUsuarioRegistra INT NOT NULL DEFAULT 0,
    NumeroCambios     INT NOT NULL DEFAULT 0,
    FechaUltimoCambio DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    IdUsuarioCambia   INT DEFAULT NULL,
    PRIMARY KEY (IdMigracion),
    UNIQUE KEY UX_Migraciones_Archivo (Entrega, Archivo),
    KEY IX_Migraciones_Fecha (FechaRegistro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
}

registrar() { # registrar <base> <entrega> <archivo> <sha> <resultado> <mensaje> <ms>
  local m
  m="$(printf '%s' "$6" | tr '\n' ' ' | cut -c1-900)"
  sql "$1" <<SQL
INSERT INTO Migraciones (Entrega, Archivo, Sha256, Resultado, Mensaje, DuracionMs, RequestId, IdUsuarioRegistra)
VALUES ($(literal "$2"), $(literal "$3"), '$4', '$5', $(literal "$m"), $7, $(literal "${CORRIDA:0:16}"), -1)
ON DUPLICATE KEY UPDATE Sha256 = VALUES(Sha256), Resultado = VALUES(Resultado), Mensaje = VALUES(Mensaje),
    DuracionMs = VALUES(DuracionMs), RequestId = VALUES(RequestId), NumeroCambios = NumeroCambios + 1,
    IdUsuarioCambia = -1;
SQL
}

respaldar() {
  local base="$1" nombre destino
  nombre="$(cat "$TMP/$base.nombre")"
  mkdir -p "$RESPALDOS" && chmod 700 "$RESPALDOS"
  destino="$RESPALDOS/${nombre}_$(date +%Y%m%d-%H%M%S)_${CORRIDA}.sql.gz"
  log "Respaldo de $base → $destino"
  if ! "$VOLCADO" --defaults-extra-file="$TMP/$base.cnf" --single-transaction --routines --triggers --events \
        --no-tablespaces "$nombre" 2>"$TMP/volcado.err" | gzip > "$destino"; then
    rm -f "$destino"; log "$(cat "$TMP/volcado.err")"
    return 1
  fi
  chmod 600 "$destino"
  # Se conservan los últimos 10 de cada base.
  ls -1t "$RESPALDOS/${nombre}_"*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
}

ms() { local t; t="$(date +%s%3N)"; case "$t" in *N) echo $(( $(date +%s) * 1000 )) ;; *) echo "$t" ;; esac; }

# --- Revisión (sin cambios) -----------------------------------------------------------------------------------------
if [ "$REVISAR" = 1 ]; then
  echo "Cliente: $CLIENTE ($("$CLIENTE" --version 2>/dev/null | head -n1))"
  echo "Respaldo: ${VOLCADO:-NO HAY mariadb-dump/mysqldump}"
  echo "Candado: $(command -v flock || echo 'sin flock (se usa mkdir)')"
  for base in colegio; do
    preparar_base "$base"
    echo "== $base"
    if ! sql "$base" <<<"SELECT CONCAT('base ', DATABASE(), ' · usuario ', CURRENT_USER(), ' · servidor ', VERSION())" 2>&1; then
      ESTADO_R=1; continue
    fi
    sql "$base" <<<"SHOW GRANTS" 2>&1 | sed 's/IDENTIFIED BY PASSWORD .*//' | sed 's/^/   /'
    sql "$base" <<<"SELECT CONCAT('Migraciones: ', IF(COUNT(*) > 0, 'existe', 'aún no existe (se crea en la primera corrida)')) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Migraciones'"
  done
  exit "${ESTADO_R:-0}"
fi

# --- Recorrido -------------------------------------------------------------------------------------------------------
declare -A PREPARADA=() RESPALDADA=()
ESTADO=0
# Sin "< <(…)": en Hostinger no existe /dev/fd. Las listas pasan por archivos temporales.
CARPETAS=()
find "$ENTREGAS" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | LC_ALL=C sort > "$TMP/carpetas" || falla "no se pudo leer $ENTREGAS"
mapfile -t CARPETAS < "$TMP/carpetas"
if [ "${#CARPETAS[@]}" -eq 0 ]; then log "No hay entregas que aplicar."; exit 0; fi

for entrega in "${CARPETAS[@]}"; do
  dir="$ENTREGAS/$entrega"
  base=colegio
  if [ -z "${PREPARADA[$base]:-}" ]; then
    preparar_base "$base"
    asegurar_tabla "$base" 2>"$TMP/err" || falla "no se pudo crear Migraciones en $base: $(cat "$TMP/err")"
    PREPARADA[$base]=1
  fi
  echo "== $entrega ($base)" >&2
  ARCHIVOS=()
  find "$dir" -maxdepth 1 -type f -name '*.sql' -printf '%f\n' | LC_ALL=C sort > "$TMP/archivos" || falla "no se pudo leer $dir"
  mapfile -t ARCHIVOS < "$TMP/archivos"
  ok=1
  for archivo in "${ARCHIVOS[@]}"; do
    huella="$(sha256sum "$dir/$archivo" | cut -d' ' -f1)"
    previo="$(sql "$base" <<<"SELECT CONCAT(Resultado, ' ', Sha256) FROM Migraciones WHERE Entrega = $(literal "$entrega") AND Archivo = $(literal "$archivo")")"
    if [ "${previo%% *}" = aplicado ]; then
      if [ "${previo#* }" = "$huella" ]; then log "$archivo ya estaba aplicado"; continue; fi
      msg="ya se aplicó con otra huella; un cambio a lo desplegado va en una entrega nueva"
      echo "@@ ERROR $entrega $archivo $msg"; log "$archivo: $msg"; ok=0; break
    fi
    if [ "$SIN_RESPALDO" = 0 ] && [ -z "${RESPALDADA[$base]:-}" ]; then
      respaldar "$base" || { echo "@@ ERROR $entrega $archivo no se pudo respaldar la base; no se aplicó nada"; ok=0; break; }
      RESPALDADA[$base]=1
    fi
    t0="$(ms)"
    if sql "$base" < "$dir/$archivo" > "$TMP/salida" 2>&1; then
      dur=$(( $(ms) - t0 ))
      registrar "$base" "$entrega" "$archivo" "$huella" aplicado "" "$dur"
      log "$archivo aplicado (${dur} ms)"
    else
      dur=$(( $(ms) - t0 ))
      msg="$(grep -m1 -E '^ERROR' "$TMP/salida" || head -n1 "$TMP/salida")"
      registrar "$base" "$entrega" "$archivo" "$huella" error "$msg" "$dur" || true
      echo "@@ ERROR $entrega $archivo $msg"; log "$archivo falló: $msg"; ok=0; break
    fi
  done
  if [ "$ok" = 1 ]; then echo "@@ LISTA $entrega"; else ESTADO=1; break; fi
done
exit "$ESTADO"
