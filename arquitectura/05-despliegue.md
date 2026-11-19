# 05 · Ramas y despliegue

| Rama | Qué tiene | Qué pasa al subir |
|---|---|---|
| `master` | Solo código | Hostinger lo publica solo. Corren **CI** (`php -l` 8.1 y 8.3), **Integración** (base MariaDB 11.8 con todo el SQL + catálogo) y **SQL a producción** (aplica las entregas listas) |
| `DeployBacklog` (huérfana) | El SQL: `pendiente/`, `desplegado/`, `esquema/produccion/colegio.sql`, `automatizacion/` | Ensayo en MariaDB 11.8 y, si pasa, aplica las entregas listas |
| `Documentacion` (huérfana) | Esto | Nada |

El detalle del despliegue automático está en `automatizacion/README.md` de `DeployBacklog`:
- respaldo antes de aplicar;
- tabla `Migraciones` con huella sha256;
- candado compartido con los sitios de NeuralStack Code;
- `espera.txt`.

## Regla de oro

**El SQL antes que el código.** Una entrega debe dejar funcionando el código que ya está publicado: primero se agrega y
lo que se quita va en una entrega posterior. Se sube primero la entrega a `DeployBacklog` y después el código a `master`.

## Configuración (una sola vez)

Settings → Secrets and variables → Actions, **a nivel repositorio** (no hace falta un *environment*):

| Secreto | Valor |
|---|---|
| `SSH_HOST` | Host del SSH de Hostinger (hPanel → Avanzado → Acceso SSH) |
| `SSH_PORT` | `65002` |
| `SSH_USER` | Usuario SSH |
| `SSH_KEY` | Llave privada ed25519; su pública en hPanel → Acceso SSH → Llaves |
| `SSH_KNOWN_HOSTS` | `ssh-keyscan -p 65002 <host>` |
| `DEPLOY_TOKEN` | Opcional: solo si una entrega espera una rama de otro repo |

| Variable | Valor |
|---|---|
| `RUTA_SITIO` | **Obligatoria.** Carpeta del sitio desde el home, por ejemplo `domains/<dominio>/public_html`. Sin valor por defecto |

Además: Settings → Actions → General → Workflow permissions → **Read and write**.

## Primer despliegue (2026-11, framework + catálogo)

1. `DeployBacklog` arriba con el ensayo en verde.
2. Commit **solo con `.github/`** sobre `origin/master` y push. Así aparece *Run workflow*: GitHub solo lo muestra cuando
   el YAML está en la rama por defecto.
3. Configurar secretos y variable.
4. Actions → *SQL a producción* → *Run workflow* → `revisar`, y luego `aplicar`.
5. Revisar `Migraciones`. Luego, push del código nuevo a `master`.
6. Lo manual del `DESPLIEGUE.md` de la entrega: borrar `vendor/`, `composer.json` y el `crypto.php` en minúsculas.
