# Documentacion · Colegio del Bosque

Rama **solo de documentación** del sitio del Colegio del Bosque (`ColeDelBosque-AppWeb`).

## Reglas

- **Nunca entra a `master`.** Igual que `DeployBacklog`, esta rama no comparte historial con `master`: git rechaza
  mezclarlas, así que la documentación no puede llegar por accidente al servidor (`master` se publica solo en Hostinger).
- **Nunca contraseñas, llaves, `.env` ni datos de alumnos o familias.** Nombres de bases y rutas sí; credenciales no.
- **El SQL no va aquí:** va en `DeployBacklog`.
- Cada cambio importante del código o de la base actualiza su documento el mismo día, y una línea en
  [arquitectura/06-historial.md](arquitectura/06-historial.md).

## Contenido

| Carpeta | Qué hay |
|---|---|
| [arquitectura/](arquitectura/README.md) | La documentación vigente: arquitectura, catálogo de permisos y perfiles, despliegue automático, historial |
| [guias/](guias/) | Guías de temas puntuales (por ejemplo, [probar en local sin tocar producción](guias/probar-en-local.md)) |

## Cómo consultarla o actualizarla sin cambiar de rama

```bash
git fetch origin Documentacion
git worktree add ../documentacion Documentacion
cd ../documentacion
git add -A && git commit -m "Docs: …" && git push origin Documentacion
cd - && git worktree remove ../documentacion
```
