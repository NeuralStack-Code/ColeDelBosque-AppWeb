# 03 · Catálogo de permisos y perfiles

No hay roles. Cada **cuenta** tiene uno o varios **perfiles** (`CuentaPerfiles`). Cada perfil concede **objetos** del
catálogo (`PerfilObjeto`).

## Objetos

| Tipo | Qué es | Ejemplo |
|---|---|---|
| 1 · Grupo | Un encabezado del menú | `escolar`, `pagos`, `padre`, `sistema` |
| 2 · Vista | Una pantalla | `grupo/index` |
| 3 · Método | Una acción de esa pantalla | `grupo/listar`, `grupo/guardar` |

- `Acceso` puede valer:
  - `publico`: sin sesión, por ejemplo `auth/entrar` y `contacto/*`;
  - `sesion`: cualquier cuenta con sesión, por ejemplo `recibo/imprimir`;
  - `perfil`: hay que tenerlo concedido.
- `SoloPost`: las lecturas (`listar*`, `obtener*`, `catalogos`, `contexto`, `resumen`…) aceptan GET; lo demás solo POST.
- `Sensible`: solo un perfil administrador del sistema lo puede conceder (`cuenta/guardar`, `perfilacceso/*`, `tablas/*`).
- `ObjetoUsos`: una vista que llama métodos de otra (por ejemplo, `alumno/index` usa `grupo/listar`).

## Perfiles de inicio

| Perfil | Inicio | Recibe sola una cuenta nueva de tipo |
|---|---|---|
| Administrador | `/dashboard/index` | 1 |
| Maestro | `/maestro` | 2 |
| Padre | `/padre/index` | 3 |
| Desarrollador (concede todo) | `/desarrollador` | nunca: se asigna a mano |

`reporte/grupos` (ver y enviar el reporte semanal de **todos** los grupos) es un objeto aparte que solo concede
Administrador.

## Reglas

- Nadie concede lo que no tiene. Lo sensible, solo un administrador del sistema.
- No se puede quitar el último perfil administrador ni borrar un perfil con cuentas.
- Todo cambio queda en `BitacoraPermisos`. El menú se recalcula solo cuando cambia la bitácora.

## Dónde se administra

Panel del desarrollador:
- **Cuentas** (`/cuenta/index`): los perfiles de cada cuenta.
- **Perfiles y permisos** (`/perfilacceso/index`): un árbol con casillas.

## Cuando agregas o cambias un controller

1. `php apiService/tools/catalogo/generar_semilla.php` (ajustes de menú, accesos y sensibles en
   `webService/libs/CatalogoCodigo.php::ajustes()`).
2. La semilla nueva va en una entrega de `DeployBacklog`. La Action **Integración** falla si el catálogo del código no
   es el del SQL.
