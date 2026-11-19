<?php
/** Lectura del catálogo desde el CÓDIGO para la terminal. La lógica vive en webService/libs/CatalogoCodigo.php. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function leerCodigo(string $root, array $ajustes): array
{
    return CatalogoCodigo::read($root, $ajustes);
}

function huellaCatalogo(array $codigo, array $ajustes): string
{
    return CatalogoCodigo::fingerprint($codigo, $ajustes);
}
