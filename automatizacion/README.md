# Despliegue automático del SQL · Colegio del Bosque

El mismo sistema de NeuralStack Code (WebSite y apiWebSite), con **una sola base**: la del `.env` del sitio del
colegio (`DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`). Ya no se corre nada a mano en phpMyAdmin.

Repo: `NeuralStack-Code/ColeDelBosque-AppWeb`. Hostinger publica `master` solo (Git vinculado en hPanel).

## Cuándo corre

| Evento | Qué pasa |
|---|---|
| Push a `DeployBacklog` | Ensayo en MariaDB 11.8 con **todas** las pendientes; si pasa, aplica las **listas** (`.github/workflows/sql.yml` de esta rama) |
| Push a `master` | Hostinger publica el código; la Action aplica las entregas listas (`.github/workflows/sql-produccion.yml` de `master`) |
| A mano | Actions → *SQL a producción* → *Run workflow* (`aplicar`, `revisar` o `listas`) |

Una entrega está **lista** si no tiene `espera.txt`, o si cada línea de su `espera.txt` (`<repo> <rama>`, p. ej.
`ColeDelBosque-AppWeb mi-rama`) ya llegó a `master`.

En el servidor, `servidor.sh` hace lo mismo que en NeuralStack:
1. Toma el candado `~/.sql-produccion.lock`, el mismo de WebSite y apiWebSite, así que nunca corren dos a la vez.
2. Respalda la base en `~/respaldos-sql/` y conserva los últimos 10.
3. Registra cada archivo en `Migraciones` con su sha256.
4. Se detiene al primer error y rechaza un archivo ya aplicado que vuelva cambiado.

Al terminar, la entrega pasa a `desplegado/` con su `aplicado.txt` y un commit `[skip ci]`.

## Reglas de una entrega

- `pendiente/AAAA-MM-DD_nombre/` con `01_…sql`, `02_…sql` en orden. `base.txt` no se usa: siempre es la base del colegio.
- Que el código anterior siga funcionando con la base nueva. Hostinger publica `master` alrededor de un minuto antes de
  que la Action aplique el SQL. Por eso lo nuevo **se sube primero a DeployBacklog** y después el código a `master`.
- Lo que no es SQL (archivos que hay que borrar en el servidor, `.env`) va anotado en el `DESPLIEGUE.md` de la entrega.

## Configuración (una sola vez, la hace ella)

En **ColeDelBosque-AppWeb** → *Settings → Secrets and variables → Actions*:

| Secreto | Qué es |
|---|---|
| `SSH_HOST` | Host del SSH de Hostinger (hPanel → Avanzado → Acceso SSH). Si el colegio está en la misma cuenta de Hostinger que NeuralStack, es el mismo |
| `SSH_PORT` | `65002` |
| `SSH_USER` | Usuario SSH de esa cuenta |
| `SSH_KEY` | Llave privada ed25519 (puede ser la misma de WebSite si es la misma cuenta, o una nueva con su pública en hPanel) |
| `SSH_KNOWN_HOSTS` | `ssh-keyscan -p 65002 <host>` |
| `DEPLOY_TOKEN` | Opcional: solo si una entrega espera una rama de **otro** repo. Sin él se usa el token propio de la Action |

| Variable | Qué es |
|---|---|
| `RUTA_SITIO` | **Obligatoria.** Carpeta del sitio del colegio desde el home, p. ej. `domains/<dominio-del-colegio>/public_html`. No tiene valor por defecto, para que nunca toque la base de otro sitio |

Además: *Settings → Actions → General → Workflow permissions* → **Read and write** (para mover a `desplegado/`).

Estado: secretos SSH, `RUTA_SITIO` y permiso de escritura configurados el 2026-11-19.

Primera vez: Actions → *SQL a producción* → `revisar`. Muestra cliente, conexión y permisos sin cambiar nada.

## Primer despliegue (el SQL ANTES que el código)

`Run workflow` solo aparece cuando el YAML ya está en `master` (la rama por defecto). Por eso la primera vez va así:

1. **Push de `DeployBacklog`.** Todavía sin secretos: solo corre el ensayo (`sql.yml`) y nada llega a producción.
   Esperar a que salga en verde. Para eso ya debe estar `esquema/produccion/colegio.sql`.
2. **Commit SOLO con `.github/`, sobre `origin/master` (dacd6ae, el código viejo), y push.** Hostinger publica el
   mismo código de antes más el YAML. La Action corre, pero sin secretos solo avisa y no aplica nada.
3. **Configurar los secretos y la variable** de la tabla de abajo.
4. Actions → *SQL a producción* → *Run workflow* → **`revisar`**: conexión y permisos, sin cambios.
5. Actions → *SQL a producción* → *Run workflow* → **`aplicar`**:
   - respalda la base;
   - corre `2026-11-19_framework-catalogo`;
   - la mueve a `desplegado/`.

   El código viejo sigue funcionando con estas tablas y procedures, porque la entrega es aditiva.
6. Revisar en phpMyAdmin que `Migraciones` tenga los 6 archivos `aplicado`.
7. **Ahora sí, push del código nuevo a `master`** (el rebase sobre dacd6ae). Hostinger lo publica. La Action vuelve a
   correr, ve que no hay entregas pendientes y no hace nada.
8. Lo manual del `DESPLIEGUE.md` de la entrega: borrar `vendor/`, `composer.json`, el `crypto.php` en minúsculas, etc.

De ahí en adelante ya no hay pasos a mano: entrega a `DeployBacklog` y luego código a `master`.

## Falta antes de la primera corrida

`esquema/produccion/colegio.sql`: el export de **solo estructura** de la base de producción (phpMyAdmin → Exportar →
Personalizado → Estructura, con procedures). El ensayo lo carga y luego corre encima las pendientes. Mientras no esté,
el ensayo falla y **no se toca producción**.

## Probarlo en tu equipo

```bash
bash automatizacion/desplegar.sh listas        # qué está listo
bash automatizacion/desplegar.sh ensayo        # necesita Docker y esquema/produccion/colegio.sql
```

`servidor.sh` se probó contra un MySQL desechable local con cinco casos:
- aplicar;
- segunda corrida sin cambios;
- archivo alterado rechazado;
- error a la mitad y reintento;
- `--revisar`.
