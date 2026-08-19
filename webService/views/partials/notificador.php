<?php
/**
 * Partial: Notificador de mensajes del servidor
 * Componente NeuralStack — no modificar, es genérico para todas las apps.
 *
 * Uso desde PHP (flash antes de redirect):
 *   $_SESSION['flash'] = ['type' => 'success', 'message' => 'Guardado.'];
 *   header('Location: /ruta'); exit;
 *
 * Uso desde JS:
 *   window.notify('success', 'Mensaje.');
 *   window.notifyResponse(data);   // usa data.success + data.message
 *
 * Tipos: success | error | warning | info
 */

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>

<div id="ns-notificador" aria-live="polite" aria-atomic="true"></div>

<?php if ($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.notify(
        <?= json_encode($flash['type']    ?? 'info') ?>,
        <?= json_encode($flash['message'] ?? '')     ?>
    );
});
</script>
<?php endif; ?>

<style>
#ns-notificador {
    position: fixed; top: 1.25rem; right: 1.25rem;
    z-index: 9999; display: flex; flex-direction: column;
    gap: 0.75rem; max-width: 360px;
    width: calc(100% - 2.5rem); pointer-events: none;
}
.ns-toast {
    display: flex; align-items: flex-start; gap: 0.75rem;
    padding: 0.9rem 1.1rem;
    background: var(--ns-color-white, #fff);
    border-left: 5px solid var(--ns-info, #2980b9);
    box-shadow: var(--ns-shadow, 4px 4px 0 rgba(0,0,0,.15));
    font-family: var(--ns-font-body, monospace);
    font-size: 0.85rem; color: var(--ns-color-text, #222);
    pointer-events: all;
    animation: ns-slide-in 0.25s ease forwards;
}
.ns-toast.ns-success { border-color: var(--ns-success, #4a7c59); }
.ns-toast.ns-error   { border-color: var(--ns-error,   #c0392b); }
.ns-toast.ns-warning { border-color: var(--ns-warning,  #c89a00); }
.ns-toast.ns-info    { border-color: var(--ns-info,     #2980b9); }
.ns-toast-icon  { font-size: 1.2rem; flex-shrink: 0; margin-top: 1px; }
.ns-toast-body  { flex: 1; line-height: 1.5; }
.ns-toast-close { background: none; border: none; cursor: pointer; font-size: 1rem; color: inherit; opacity: 0.5; padding: 0; }
.ns-toast-close:hover { opacity: 1; }
.ns-toast.ns-hiding { animation: ns-slide-out 0.2s ease forwards; }
@keyframes ns-slide-in  { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
@keyframes ns-slide-out { from { opacity:1; transform:translateX(0); } to { opacity:0; transform:translateX(20px); } }
</style>

<script>
(function () {
    const ICONS    = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️' };
    const DURATION = { success:4000, error:7000, warning:5000, info:4000 };

    window.notify = function (type, message) {
        if (!message) return;
        const container = document.getElementById('ns-notificador');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `ns-toast ns-${type}`;
        toast.innerHTML = `
            <span class="ns-toast-icon">${ICONS[type] ?? 'ℹ️'}</span>
            <span class="ns-toast-body">${message}</span>
            <button class="ns-toast-close" aria-label="Cerrar">✕</button>`;
        const close  = toast.querySelector('.ns-toast-close');
        const remove = () => {
            toast.classList.add('ns-hiding');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        };
        close.addEventListener('click', remove);
        const timer = setTimeout(remove, DURATION[type] ?? 4000);
        close.addEventListener('click', () => clearTimeout(timer), { once: true });
        container.appendChild(toast);
    };

    window.notifyResponse = function (data) {
        window.notify(data.success ? 'success' : 'error', data.message ?? '');
    };
})();
</script>