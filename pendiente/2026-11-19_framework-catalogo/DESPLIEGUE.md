# Colegio del Bosque · framework NeuralStack + catálogo de permisos (2026-11-19)

Base: **la del colegio** (la del `.env` de producción). El código que lo usa es el refactor al estándar de
NeuralStack Code (core 1.5.0, router por catálogo, Loader propio sin Composer, perfiles en vez de roles).

## Qué hace

| Archivo | Qué es | ¿Cambia datos? |
|---|---|---|
| `01_sp_sistema.sql` | Procedures del framework (versión y firmas de procedures para `Command`) | No |
| `02_tablas_catalogo.sql` | Tablas nuevas: `TipoObjetos`, `Componentes`, `Objetos`, `ObjetoUsos`, `Perfiles`, `PerfilObjeto`, `CuentaPerfiles`, `BitacoraPermisos`, `CatalogoVersion`, `Rutas` | Solo crea (IF NOT EXISTS) |
| `03_procedures.sql` | Los procedures de todos los módulos (incluye `sp_catalogo_*`, `sp_objeto_*`, `sp_ruta_*`). Ninguno estaba en producción | No (DROP + CREATE de procedures) |
| `04_catalogo_semilla.sql` | El catálogo: 22 componentes, 23 pantallas, 70 acciones. **Generado** por `apiService/tools/catalogo/generar_semilla.php` | Llena el catálogo |
| `05_perfiles_semilla.sql` | Perfiles Administrador, Maestro, Padre y Desarrollador; qué concede cada uno; y a cada cuenta el perfil de su tipo (`cuenta.permiso_id` 1–4) | Llena perfiles y `CuentaPerfiles` |
| `06_rutas_semilla.sql` | Páginas sueltas (portada, oferta educativa, actividades) y las URLs anteriores que redirigen (301) | Llena `Rutas` |

Todo es **aditivo** y se puede correr dos veces (probado: dos pases seguidos sin errores). No toca columnas ni
borra nada de las tablas que ya existen. El código viejo sigue funcionando con estas tablas puestas.

## Orden

Automático (ver `automatizacion/README.md`): al subir esta entrega a la rama `DeployBacklog`, la Action hace el ensayo,
respalda la base, aplica `01` → `06` y la mueve a `desplegado/`.

1. Subir esta entrega a `DeployBacklog` y esperar la Action en verde.
2. Después, subir el código nuevo a `master`.
3. En el servidor, borrar lo que el código ya no usa si el despliegue no lo borra solo: `vendor/`, `composer.json`,
   `webService/views/partials/guard.php` y, si existe, `apiService/middleware/auth.php`. Ojo con
   `apiService/core/crypto.php` (minúsculas, el viejo): en Linux convive con `Crypto.php` (mayúscula, el del framework);
   se borra solo el de minúsculas. La carpeta `storage/` de la raíz se mueve sola a `apiService/storage/` en la
   primera petición.

## Revisar después

- Entrar con una cuenta de cada tipo: cada una cae en su panel (Administrador → `/dashboard/index`,
  Maestro → `/maestro`, alumno/familia → `/padre/index`, Desarrollador → `/desarrollador`).
- Panel del desarrollador → **Perfiles y permisos**: el árbol de lo que concede cada perfil.
- `/administrador/alumnos` y demás URLs viejas redirigen a la nueva.

## Notas

- Probado en local con MySQL 8 (base desechable :3309, esquema base reconstruido de los procedures porque no hay
  export de producción). **Falta el ensayo en MariaDB 11.8**: usa `JSON_TABLE` (MariaDB ≥ 10.6) y una FK
  `CuentaPerfiles → cuenta(id_cuenta)` (requiere `cuenta` InnoDB con `id_cuenta INT`, como ya lo piden las FK del
  reporte semanal).
- Las columnas de auditoría del catálogo (`IdUsuarioRegistra`/`IdUsuarioCambia`) guardan la **cuenta** que actuó.
- Una cuenta nueva recibe sola el perfil de su tipo (`Perfiles.TipoCuenta`); un perfil administrador nunca se da solo.
- `esquema/produccion/colegio.sql` sigue pendiente: hace falta el export de estructura de producción.
