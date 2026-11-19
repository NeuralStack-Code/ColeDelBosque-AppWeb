<?php
/**
 * Lo que comparten las pantallas de alumnos y de maestros (AlumnoController y DocenteController):
 * listar las cuentas de un rol con su matrícula legible, validar nombre y matrícula, y dar de baja.
 */
trait CuentasPersona
{
    /** Las cuentas del rol, con la matrícula y los correos ya descifrados. @return Persona[] */
    private function personas(int $permiso): array
    {
        $this->api->command('Escolar', 'Persona', 'List');
        $this->api->addParameter('IdPermiso', 'I', $permiso);
        $result = $this->api->execute();
        if ($result->status()) $this->failure($result);

        $personas = Persona::fromTable($result->table());
        foreach ($personas as $persona) {
            $persona->Matricula    = (string) Cifrado::decrypt($persona->Matricula);
            $persona->CorreoTutor  = $persona->CorreoTutor  === null ? '' : (string) Cifrado::decrypt($persona->CorreoTutor);
            $persona->CorreoTutor2 = $persona->CorreoTutor2 === null ? '' : (string) Cifrado::decrypt($persona->CorreoTutor2);
        }
        return $personas;
    }

    /** Nombre y apellido paterno obligatorios (el materno nunca); la matrícula es 3 mayúsculas + 6 números. */
    private function validarPersona(Persona $persona): void
    {
        foreach (['nombre' => $persona->Nombre, 'apellido paterno' => $persona->Paterno] as $campo => $valor) {
            $this->ensure($valor !== '', "El $campo no puede estar vacío.");
            $this->ensure(!is_numeric($valor), "Revisa el $campo.");
        }
        $this->ensure(!is_numeric((string) $persona->Materno), 'Revisa el apellido materno.');
        $this->ensure((bool) preg_match('/^[A-Z]{3}[0-9]{6}$/', $persona->Matricula), 'La matrícula debe tener 3 mayúsculas y 6 números (AAA######).');
    }

    /** Baja de la cuenta con todo lo suyo (recibos, colegiaturas, calificaciones) y su usuario. */
    private function eliminarPersona(int $IdCuenta): void
    {
        $this->api->command('Escolar', 'Persona', 'Delete');
        $this->api->addParameter('IdCuenta', 'I', $IdCuenta);
        $result = $this->api->execute();

        if (!$result->status()) {
            $this->success('Se ha eliminado el registro y sus datos relacionados.');
        } else {
            $this->failure($result);
        }
    }
}
