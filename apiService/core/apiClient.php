<?php
/**
 * Cliente HTTP para consumir la API central de NeuralStack (apiWebSite).
 *
 * Estándar de consumo: el producto le pega a la api a través de aquí, nunca con
 * HTTP inline. Config (.env): API_BASE_URL (con /v1) y KEY_TOKEN (→ X-API-KEY).
 */
require_once __DIR__ . '/env.php';

function apiBaseUrl(): string {
    return rtrim($_ENV['API_BASE_URL'] ?? 'https://api.neuralstackcode.com.mx/v1', '/');
}

function apiKey(): string {
    return $_ENV['KEY_TOKEN'] ?? '';
}

/**
 * Petición a la api central. Normaliza el envelope (nuevo {success,status,code,
 * message,data} y el viejo {success,message,...}) → el consumidor lee en 'data'.
 *
 * @return array{ok:bool,status:int,data:array,message:string,raw:mixed}
 */
function apiRequest(string $metodo, string $recurso, array $datos = [], int $timeout = 15): array {
    $metodo = strtoupper($metodo);
    $url    = apiBaseUrl() . '/' . ltrim($recurso, '/');

    $headers = ['X-API-KEY: ' . apiKey(), 'Accept: application/json'];
    $http    = ['method' => $metodo, 'timeout' => $timeout, 'ignore_errors' => true];

    if ($metodo === 'GET') {
        if ($datos) $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($datos);
    } else {
        $headers[]       = 'Content-Type: application/json';
        $http['content'] = json_encode($datos);
    }
    $http['header'] = implode("\r\n", $headers);

    $resp = @file_get_contents($url, false, stream_context_create(['http' => $http]));

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    if ($resp === false) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'message' => 'No se pudo contactar la API.', 'raw' => null];
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return ['ok' => false, 'status' => $status, 'data' => [], 'message' => 'Respuesta no-JSON de la API.', 'raw' => $resp];
    }

    $data = $json['data'] ?? $json;
    return [
        'ok'      => (bool) ($json['success'] ?? ($status >= 200 && $status < 300)),
        'status'  => $status,
        'data'    => is_array($data) ? $data : [],
        'message' => $json['message'] ?? '',
        'raw'     => $json,
    ];
}

function apiGet(string $recurso, array $query = [], int $timeout = 15): array {
    return apiRequest('GET', $recurso, $query, $timeout);
}

function apiPost(string $recurso, array $datos = [], int $timeout = 15): array {
    return apiRequest('POST', $recurso, $datos, $timeout);
}
