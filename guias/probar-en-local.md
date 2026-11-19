# Probar en local sin tocar producción

El `.env` del colegio apunta a **producción**. Nunca se prueba con él.

1. Una base MySQL/MariaDB desechable (en Docker o en otro puerto), armada con el SQL de `DeployBacklog`:
   `esquema/produccion/colegio.sql` + `desplegado/` + `pendiente/`. Nunca una copia de los datos de producción.
2. Un `.env` de prueba (o un router de pruebas) con `DB_*` hacia esa base.
3. `php -S localhost:8787` en la raíz del proyecto.
4. Antes de subir: que pasen `php -l` y `bash .github/scripts/integracion.sh <checkout de DeployBacklog>`.
