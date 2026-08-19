/**
 * Modal de confirmación reutilizable (reemplaza confirm() nativo).
 * Uso:  if (await window.confirmar('¿Eliminar esto?')) { ... }
 * Opciones: window.confirmar(msg, { titulo, confirmar, peligro:true|false })
 */
(function () {
    let dlg, resolver;

    function crear() {
        if (dlg) return;
        dlg = document.createElement('dialog');
        dlg.className = 'ns-confirm';
        dlg.innerHTML = `
            <div class="ns-confirm-in">
                <div class="ns-confirm-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                        <path d="M12 9v4"/><path d="M12 17h.01"/>
                    </svg>
                </div>
                <h3 class="ns-confirm-tit">¿Estás seguro?</h3>
                <p class="ns-confirm-msg"></p>
                <div class="ns-confirm-acc">
                    <button type="button" class="ns-confirm-no">Cancelar</button>
                    <button type="button" class="ns-confirm-si">Confirmar</button>
                </div>
            </div>`;
        document.body.appendChild(dlg);

        const cerrar = (val) => { dlg.close(); if (resolver) resolver(val); resolver = null; };
        dlg.querySelector('.ns-confirm-no').addEventListener('click', () => cerrar(false));
        dlg.querySelector('.ns-confirm-si').addEventListener('click', () => cerrar(true));
        dlg.addEventListener('cancel', (e) => { e.preventDefault(); cerrar(false); }); // tecla ESC
        dlg.addEventListener('click', (e) => { if (e.target === dlg) cerrar(false); }); // clic fuera
    }

    window.confirmar = function (mensaje, opts = {}) {
        crear();
        dlg.querySelector('.ns-confirm-tit').textContent = opts.titulo || '¿Estás seguro?';
        dlg.querySelector('.ns-confirm-msg').textContent = mensaje || '';
        const si = dlg.querySelector('.ns-confirm-si');
        si.textContent = opts.confirmar || 'Confirmar';
        si.classList.toggle('peligro', opts.peligro !== false); // peligro por defecto
        return new Promise((res) => { resolver = res; dlg.showModal(); });
    };
})();
