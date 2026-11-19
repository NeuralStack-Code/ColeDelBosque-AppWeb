<?php
/**
 * Tipo de cuenta (columna cuenta.permiso_id, catálogo permisos): QUÉ ES la persona en el colegio. Sirve para listar
 * alumnos o docentes y para saber de quién es un recibo. NO da permisos: lo que cada cuenta puede abrir lo deciden
 * sus perfiles en el catálogo (CuentaPerfiles). Los valores son las filas base de la tabla permisos.
 */
final class TipoCuenta
{
    public const ADMINISTRADOR = 1, MAESTRO = 2, ALUMNO = 3, DESARROLLADOR = 4;
}
