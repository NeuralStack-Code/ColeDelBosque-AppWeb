# 01 · Arquitectura

## Carpetas (`master`)

| Carpeta | Qué hay |
|---|---|
| `apiService/core/` | El framework de NeuralStack Code: `Controller`, `Entity`, `Command`, `Catalog`, `Loader`, `Crypto`… No se edita a mano: se actualiza desde la api |
| `apiService/tools/catalogo/` | `generar_semilla.php`: arma la semilla del catálogo leyendo los controllers |
| `webService/controllers/` | Un controller por componente (`/alumno/...` → `AlumnoController`). Es el backend: valida, decide y responde |
| `webService/business/<módulo>/` | Solo datos: llama a los procedures |
| `webService/models/<módulo>/` | Las variables de cada entidad |
| `webService/libs/` | Lo que comparten varios módulos (`TipoCuenta`, `GrupoOpcion`, `PersonaOpcion`, `CatalogoCodigo`, cifrado). No va en `wwwroot`: es PHP privado |
| `webService/views/` | Las pantallas |
| `webService/wwwroot/` | Lo único público: CSS, JavaScript e imágenes |

Módulos: `escolar`, `pagos`, `padre`, `sistema`, y `framework`, que siempre está activo.

## Cómo viaja una petición

```mermaid
sequenceDiagram
  participant N as Navegador
  participant R as index.php (router)
  participant C as Catalog
  participant BD as Base
  participant K as Controller
  N->>R: /grupo/listar
  R->>BD: tabla Rutas (páginas sueltas y redirecciones 301)
  R->>C: resolve(cuenta, grupo, listar)
  C->>BD: sp_objeto_autorizar
  BD-->>C: existe · tipo · solo POST · ¿concedido?
  C-->>R: 404 / 405 / 401 → /auth/entrar / 403 → inicio / OK
  R->>K: Loader::controller + activate(MODULE)
  K->>BD: business → sp_*
  K-->>N: vista o JSON
```

- No hay rutas escritas en el código: si un método no está en el catálogo, responde 404.
- Las URLs viejas (`/administrador/...`, `/inicio-sesion`…) redirigen con 301 desde la tabla `Rutas`.
- Auditoría: `IdUsuarioRegistra`/`IdUsuarioCambia` guardan la **cuenta** que actuó (`Command::actor`).
