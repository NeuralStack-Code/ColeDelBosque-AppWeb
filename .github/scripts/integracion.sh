#!/usr/bin/env bash
# Integración con base de datos (lo corre .github/workflows/integracion.yml).
#
#   bash .github/scripts/integracion.sh <checkout de DeployBacklog>
#
# 1. Arma la base en MariaDB 11.8 con esquema/produccion/colegio.sql (estructura de producción) y encima TODAS las
#    entregas (desplegado/ y pendiente/, por fecha) con el mismo servidor.sh que aplica en producción.
# 2. Revisa que el catálogo de permisos que genera el CÓDIGO sea el mismo que ya trae el SQL (huella de
#    CatalogoVersion). Si un controller cambió y falta la entrega con la semilla nueva, falla.
# 3. Revisa el router contra la base: una ruta pública existe y una ruta inventada no.
#
# Variables: DB_HOST DB_PORT DB_USER DB_PASSWORD (la MariaDB del servicio de la Action).
set -euo pipefail

DBL="${1:?uso: integracion.sh <checkout de DeployBacklog>}"
RAIZ="$(pwd)"
BASE=colegio_ci
ESQUEMA="$DBL/esquema/produccion/colegio.sql"

if [ ! -f "$ESQUEMA" ]; then
  echo "::warning::Falta esquema/produccion/colegio.sql en DeployBacklog (export de solo estructura de producción). No hay prueba de integración hasta que esté."
  exit 0
fi

CLI="$(command -v mariadb || command -v mysql)"
db() { MYSQL_PWD="$DB_PASSWORD" "$CLI" -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" --batch --skip-column-names "$@"; }

echo "── Base desde la estructura de producción"
db -e "DROP DATABASE IF EXISTS $BASE; CREATE DATABASE $BASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
db "$BASE" < "$ESQUEMA"

echo "── Entregas (desplegado/ y pendiente/)"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/entregas"
for d in "$DBL"/desplegado/*/ "$DBL"/pendiente/*/; do [ -d "$d" ] && cp -r "$d" "$TMP/entregas/"; done
printf 'DB_HOST=%s\nDB_USER=%s\nDB_PASSWORD=%s\nDB_NAME=%s\n' "$DB_HOST" "$DB_USER" "$DB_PASSWORD" "$BASE" > "$TMP/.env"
MYSQL_TCP_PORT="$DB_PORT" bash "$DBL/automatizacion/servidor.sh" --entregas "$TMP/entregas" --env "$TMP/.env" \
  --sin-respaldo --corrida ci | sed 's/^@@ /   /'

echo "── Catálogo: código contra SQL"
php apiService/tools/catalogo/generar_semilla.php --stdout > "$TMP/semilla.sql"
CODIGO="$(grep -oE "sp_catalogo_version_registrar\('[0-9a-f]{64}'" "$TMP/semilla.sql" | grep -oE '[0-9a-f]{64}')"
SQL_BASE="$(db "$BASE" -e "SELECT Huella FROM CatalogoVersion WHERE Origen = 'semilla' ORDER BY IdCatalogoVersion DESC LIMIT 1")"
if [ "$CODIGO" != "$SQL_BASE" ]; then
  echo "::error::El catálogo del código ($CODIGO) no es el del SQL de DeployBacklog (${SQL_BASE:-ninguno}). Regenera la semilla (php apiService/tools/catalogo/generar_semilla.php) y súbela en una entrega nueva."
  exit 1
fi
echo "   Igual ($CODIGO)."

echo "── Router contra la base"
existe() { db "$BASE" -e "CALL sp_objeto_autorizar(0, '$1', '$2')" | head -n1 | cut -f1; }
[ "$(existe auth entrar)" = 1 ] || { echo "::error::/auth/entrar no está en el catálogo"; exit 1; }
[ "$(existe noexiste nada)" = 0 ] || { echo "::error::una ruta inventada aparece en el catálogo"; exit 1; }
echo "   /auth/entrar existe; /noexiste/nada no."
echo "── Integración correcta"
