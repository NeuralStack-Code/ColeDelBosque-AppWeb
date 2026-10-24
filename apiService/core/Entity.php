<?php
/**
 * Base de las entidades de negocio (los business). Un business SOLO trae y lleva datos: declara sus
 * CAMPOS como propiedades públicas y, en cada operación, arma su comando al stored procedure y regresa
 * la tabla que contestó la base. No valida ni decide nada: eso es trabajo del controller.
 *
 *     class ClienteBusiness extends Entity {
 *         public ?int   $IdCliente = null;
 *         public string $Nombre    = '';
 *
 *         public function eliminar(): array {
 *             $this->db->command('sp_cliente_eliminar');
 *             $this->db->addParameter('IdCliente', $this->IdCliente);
 *             return $this->db->execute();
 *         }
 *     }
 *
 * $this->db (core/Command.php) ejecuta el procedure y guarda el estado: si hubo error, su código y su
 * mensaje. La entidad solo lo expone para que el controller sepa qué pasó.
 * Nada es automático en los comandos, salvo los dos campos de auditoría: IdUsuarioRegistra / IdUsuarioCambia
 * los llena Command con el usuario en sesión cuando el procedure los declara; el business no los escribe.
 * Quien le pone los campos y le pide la operación es el server api (core/ServerApi.php).
 */
abstract class Entity
{
    /** Conexión a la base: comando → parámetros → ejecutar. */
    protected Command $db;

    public function __construct(mysqli $db) { $this->db = new Command($db); }

    /* ── Estado de la última operación (igual para todas las entidades) ── */
    public function error(): bool     { return $this->db->error(); }
    public function code(): string  { return $this->db->code(); }
    public function message(): string { return $this->db->message(); }
    public function http(): int       { return $this->db->http(); }
}
