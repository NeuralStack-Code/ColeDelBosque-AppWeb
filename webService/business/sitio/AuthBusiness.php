<?php
/** Entidad Auth: el acceso por matrícula (tablas cuenta + usuario). Procedures: webService/sql/sp/sp_auth.sql. */
class AuthBusiness extends Entity
{
    public string $Matricula = '';   // ya ENCRIPTADA: así se guarda en la base (webService/libs/Cifrado.php)

    /** La cuenta de esa matrícula; sin filas = matrícula incorrecta. */
    public function authenticate(): array
    {
        $this->db->command('sp_auth_autenticar');
        $this->db->addParameter('Matricula', $this->Matricula);
        return $this->db->execute();
    }
}
