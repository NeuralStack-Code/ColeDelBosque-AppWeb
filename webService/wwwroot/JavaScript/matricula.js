/**
 * Matrícula automática en altas (alumnos y maestros): AAA######
 *   inicial del nombre + 2 letras del apellido paterno + 6 dígitos al azar.
 * Uso:  const mat = window.matriculaAuto(nombre, paterno, matricula);
 *       mat.activar()     → alta: sortea los dígitos y sigue lo que se escribe
 *       mat.desactivar()  → edición: no la toca (es la clave de acceso)
 * Si el admin la escribe a mano, deja de sobrescribirse. El servidor valida que no se repita.
 */
(function () {
    const letras = s => s.normalize('NFD').replace(/[^A-Za-z]/g, '').toUpperCase();
    const sortear = () => String(crypto.getRandomValues(new Uint32Array(1))[0] % 1000000).padStart(6, '0');

    window.matriculaAuto = function (nombre, paterno, matricula) {
        let activa = false, digitos = '';

        function generar() {
            if (!activa) return;
            const n = letras(nombre.value), p = letras(paterno.value);
            matricula.value = (n && p) ? n[0] + (p + 'X').slice(0, 2) + digitos : '';
        }
        nombre.addEventListener('input', generar);
        paterno.addEventListener('input', generar);
        matricula.addEventListener('input', () => { activa = false; });

        return {
            activar()    { activa = true; digitos = sortear(); generar(); },
            desactivar() { activa = false; },
        };
    };
})();
