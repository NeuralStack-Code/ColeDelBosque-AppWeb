# Entorno local · Colegio del Bosque

La base del colegio lista para trabajar en tu computadora, **igual a producción** y armada con el SQL de esta rama: la
estructura de producción más todas las entregas, en orden. No trae datos reales de alumnos, familias ni maestros.

> El `.env` del código del colegio apunta a **PRODUCCIÓN**. Nunca se prueba con él: el router de esta carpeta lo pisa
> con `.env.local` y se niega a arrancar si la base no es local y su nombre no termina en `_local`.

| Archivo | Qué es |
|---|---|
| `mysql8/01_colegio.sql` | La base completa: estructura de producción y todas las entregas. Se genera con `armar.py` |
| `cuentas_prueba.php` | Crea una cuenta de prueba por tipo, cada una con su perfil |
| `router.php` | Corre el sitio contra la base local (pisa el `.env` de producción con `.env.local`) |
| `api_falsa.php` | La api central de mentiras: licencia válida, y los correos se guardan en `correos.log` |
| `.env.local.example` | Plantilla de `.env.local` (base local, llaves de prueba, api falsa) |

## Pasos (una sola vez)

Necesitas MySQL 8 o MariaDB, y PHP 8.1 o mayor con `mysqli`, `openssl` y `mbstring`. En los ejemplos, `Visual Studio`
es la carpeta donde están el código (`Colegio del bosque`) y esta rama (`ColegioDelBosque-DeployBacklog`).

**1. La base vacía**

```sql
CREATE DATABASE colegio_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**2. Importar la base** (en la terminal, dentro de `ColegioDelBosque-DeployBacklog`):

```bash
mysql -u root -p --default-character-set=utf8mb4 colegio_local < entorno-local/mysql8/01_colegio.sql
```

Van a salir muchas líneas `Id` / `Afectadas`: es normal, son las respuestas de los procedures de la semilla.
También se puede importar en MySQL Workbench: doble clic en `colegio_local` → *File → Open SQL Script* → ⚡.

**3. El `.env.local`**: copia `entorno-local/.env.local.example` como `entorno-local/.env.local`. Pon tu usuario, tu
contraseña y tu puerto de MySQL. Las llaves de prueba se quedan como vienen; **nunca pongas las de producción**.

**4. Las cuentas de prueba**

```bash
php entorno-local/cuentas_prueba.php "../Colegio del bosque"
```

**5. Levantar el sitio.** Son dos terminales y se dejan abiertas:

```bash
php -S 127.0.0.1:8788 entorno-local/api_falsa.php
```

```bash
php -S localhost:8787 -t "../Colegio del bosque" entorno-local/router.php
```

Entra a http://localhost:8787/auth/entrar con una matrícula de prueba:

| Matrícula | Perfil | Cae en |
|---|---|---|
| `ADM-LOCAL` | Administrador | `/dashboard/index` |
| `MAE-LOCAL` | Maestro | `/maestro` |
| `ALU-LOCAL` | Padre | `/padre/index` |
| `DEV-LOCAL` | Desarrollador (concede todo) | `/desarrollador` |

En el Claude de escritorio están como `colegio-local` y `colegio-local-api` (`.claude/launch.json`).

## Cuando entra una entrega nueva

Quien agrega la entrega a `pendiente/` regenera el archivo y lo sube junto con ella:

```bash
python entorno-local/armar.py
```

Para ponerte al día en tu base local, bórrala y repite los pasos 1, 2 y 4. Es lo más seguro: así queda idéntica a lo
que habrá en producción.

## Diferencias con producción

- Producción es **MariaDB 11.8**. Si importas en MySQL 8, `armar.py` cambia las intercalaciones que MySQL no conoce;
  hoy el colegio no usa ninguna.
- Las matrículas de prueba se cifran con las llaves de `.env.local`, no con las de producción. Por eso una matrícula
  real no entra en local, y una de prueba no entra en producción.
- Los correos no salen: quedan en `entorno-local/correos.log`.
