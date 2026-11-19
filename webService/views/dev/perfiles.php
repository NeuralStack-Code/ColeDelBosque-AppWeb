<?php
/**
 * Perfiles y permisos (catálogo de permisos). La pinta PerfilAccesoController::index().
 * Recibe: $perfiles (PerfilAcceso, cada uno con lo que Concede), $objetos (el catálogo activo: grupos, vistas y
 * métodos), $esAdmin (quien entra tiene un perfil administrador), $concedibles (IdObjeto que quien entra puede dar),
 * $mios (IdPerfil de quien entra), $nombre y $base. Su JavaScript (JavaScript/perfiles.js) llama a /perfilacceso/<método>.
 */
$title    = 'Perfiles y permisos | Desarrollador';
$extraCss = ['admin.css'];
$img      = $base . '/webService/wwwroot/img';
$svgChev  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>';
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require __DIR__ . '/../partials/head.php'; ?></head>
<body>
<?php require __DIR__ . '/../partials/notificador.php'; ?>

    <header class="panel-top">
        <div class="contenedor inner">
            <a href="<?= $base ?>/desarrollador" class="marca">
                <img src="<?= $img ?>/logo.png" alt="Colegio del Bosque">
                <span>Colegio del Bosque<small>Panel del desarrollador</small></span>
            </a>
            <button class="btn-salir" type="button" data-action="salir">Cerrar sesión</button>
        </div>
    </header>

    <a class="volver-panel" href="<?= $base ?>/desarrollador">
        <svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Regresar al panel
    </a>

    <section class="admin-wrap">
        <div class="admin-cab">
            <div>
                <h2>Perfiles y permisos</h2>
                <p class="nota-perfiles">Qué pantallas y acciones concede cada perfil. Una cuenta puede tener varios: se suman. Se asignan en Cuentas.</p>
            </div>
            <button class="btn btn-primario" type="button" data-action="nuevo">+ Nuevo perfil</button>
        </div>

        <div class="tabla-scroll">
            <table class="tabla">
                <thead><tr><th>Perfil</th><th class="col-desc">Descripción</th><th>Inicio</th><th>Concede</th><th>Cuentas</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php if ($perfiles === []): ?>
                    <tr><td colspan="7" class="tabla-vacia">Sin perfiles registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($perfiles as $p): ?>
                    <tr data-record="<?= record($p) ?>">
                        <td>
                            <strong><?= e($p->Nombre) ?></strong>
                            <?php if ($p->EsAdmin): ?><span class="badge-estatus parcial">concede todo</span><?php endif; ?>
                            <?php if (in_array($p->IdPerfil, $mios, true)): ?><span class="marca-yo">← tuyo</span><?php endif; ?>
                        </td>
                        <td class="col-desc"><?= e($p->Descripcion ?: '—') ?></td>
                        <td><code><?= e($p->Inicio ?: '—') ?></code></td>
                        <td><?= $p->EsAdmin ? 'todo' : e($p->Objetos) ?></td>
                        <td><?= e($p->Cuentas) ?></td>
                        <td><span class="<?= $p->Activo ? 'badge-estatus pagado' : 'pill-sin' ?>"><?= $p->Activo ? 'Activo' : 'Inactivo' ?></span></td>
                        <td><div class="acciones">
                            <button class="icon-btn aplicar" type="button" data-action="permisos" title="Qué concede" aria-label="Qué concede"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></button>
                            <button class="icon-btn editar" type="button" data-action="editar" title="Editar" aria-label="Editar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg></button>
                            <?php if ($p->Cuentas === 0 && !in_array($p->IdPerfil, $mios, true)): ?>
                            <button class="icon-btn borrar" type="button" data-action="eliminar" title="Eliminar" aria-label="Eliminar"><svg class="ic-s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Un solo formulario para crear y editar. Cada campo se llama igual que en el model PerfilAcceso. -->
    <dialog class="modal" id="modal">
        <div class="modal-in">
            <h3 id="modalTitulo">Nuevo perfil</h3>
            <form id="form">
                <input type="hidden" name="IdPerfil">
                <div class="campo"><label for="Nombre">Nombre</label><input type="text" name="Nombre" id="Nombre" maxlength="80" required placeholder="Ej. Coordinación"></div>
                <div class="campo"><label for="Descripcion">Descripción</label><textarea name="Descripcion" id="Descripcion" maxlength="255" placeholder="Para qué es este perfil…"></textarea></div>
                <div class="campo"><label for="Inicio">Pantalla de inicio</label><input type="text" name="Inicio" id="Inicio" maxlength="80" placeholder="/maestro"></div>
                <div class="fila-2">
                    <div class="campo"><label for="EsAdmin">Tipo</label>
                        <select name="EsAdmin" id="EsAdmin"<?= $esAdmin ? '' : ' disabled' ?>>
                            <option value="0">Normal (lo que se marque)</option>
                            <option value="1">Administrador (concede todo)</option>
                        </select>
                    </div>
                    <div class="campo"><label for="Activo">Estado</label>
                        <select name="Activo" id="Activo"><option value="1">Activo</option><option value="0">Inactivo</option></select>
                    </div>
                </div>
                <div class="modal-acciones">
                    <button type="button" class="btn btn-fantasma" data-action="cerrar">Cancelar</button>
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Qué concede el perfil: el árbol del catálogo (módulo → pantalla → acciones). Marcar algo marca lo que cuelga de él. -->
    <dialog class="modal ancho" id="modalPermisos">
        <div class="modal-in">
            <h3>Qué concede · <span id="permisosPerfil"></span></h3>
            <p class="modal-nota" id="permisosNota">Marca las pantallas y acciones que concede este perfil; marcar un módulo o una pantalla marca lo que cuelga de él.</p>
            <p class="modal-nota" id="permisosAdmin" hidden>Este perfil es <strong>administrador</strong>: concede todo.</p>
            <p class="modal-nota" id="permisosBloqueado" hidden>Este perfil es tuyo: no lo puedes modificar (nadie se da permisos a sí mismo).</p>
            <div class="arbol-herramientas" id="permisosHerramientas">
                <input type="search" id="permisosBuscar" placeholder="Buscar módulo, pantalla o acción (ej. eliminar, ciclo/listar)" autocomplete="off" aria-label="Buscar en el catálogo">
                <button type="button" class="btn btn-fantasma" data-action="abrirTodo">Abrir todo</button>
                <button type="button" class="btn btn-fantasma" data-action="cerrarTodo">Cerrar todo</button>
            </div>
            <div class="arbol-permisos" id="permisosArbol"></div>
            <div class="modal-acciones">
                <button type="button" class="btn btn-fantasma" data-action="cerrarPermisos">Cerrar</button>
                <button type="button" class="btn btn-primario" id="permisosGuardar" data-action="guardarPermisos">Guardar</button>
            </div>
        </div>
    </dialog>

    <script>
    window.CATALOGO = <?= json_encode([
        'objetos' => $objetos, 'esAdmin' => $esAdmin, 'concedibles' => $concedibles, 'mios' => $mios, 'chevron' => $svgChev,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= $base ?>/webService/wwwroot/JavaScript/perfiles.js"></script>
</body>
</html>
