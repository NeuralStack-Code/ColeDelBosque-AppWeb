# Probar en local sin tocar producción

El `.env` del colegio apunta a **producción**. Nunca se prueba con él.

## El sitio en tu computadora

Sigue `entorno-local/README.md` de la rama `DeployBacklog`:
1. Base `colegio_local` armada con `entorno-local/mysql8/01_colegio.sql` (estructura de producción + todas las entregas).
2. Cuentas de prueba con `cuentas_prueba.php`: `ADM-LOCAL`, `MAE-LOCAL`, `ALU-LOCAL` y `DEV-LOCAL`.
3. `api_falsa.php` en `:8788` y el sitio con `entorno-local/router.php` en `:8787`. El router pisa el `.env` de
   producción con `.env.local` y se niega a arrancar si la base no termina en `_local`.

## Las pruebas (antes de subir a master)

Copia `entorno-local/.env.local` como `.env.pruebas` en la raíz del código (está en `.gitignore`). Luego:

| Comando | Qué revisa |
|---|---|
| `php apiService/tests/run.php` | Las 3 suites contra la base local |
| `php apiService/tests/run.php --sin-base` | Solo `unit`, que no necesita base (lo que corre la CI) |
| `phpstan analyse` | Análisis estático (`phpstan.neon`, nivel 0) |
| `php apiService/tools/auditoria/revisar.php --rango origin/master..HEAD` | Lo mismo que la Action *Auditoría* |

| Suite | Qué prueba |
|---|---|
| `unit` | Cifrado de matrículas (determinista), `Crypto`, catálogo leído del código (públicos, sensibles, módulos), que existan las vistas |
| `permisos` | Crea una cuenta por perfil, entra por `/auth/entrar` y recorre todo el catálogo: lo que la base concede es lo que el sitio deja (401, 403, 404, 405, pantallas de inicio). Al terminar borra sus cuentas |
| `rutas` | Cada fila de `Rutas`: las páginas sueltas dan 200 y las redirecciones mandan a un destino que existe |

Las pruebas se niegan a correr si `.env.pruebas` no apunta a una base local `_local`.

`php apiService/tools/licencia_local.php` deja una licencia simulada, por si no quieres levantar la api falsa.
