<?php
/**
 * GET /api/geocodificar.php?lat=..&lng=..
 * Geocodificação reversa pelo Nominatim (OpenStreetMap), feita pelo servidor para
 * enviar o User-Agent exigido pelo serviço e respeitar o limite de 1 consulta por segundo.
 * Restrito a usuários logados, para não virar um proxy aberto.
 */
require_once __DIR__ . '/../app/iniciar.php';
api_exigir_login();

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
if ($lat === null || $lat === false || $lng === null || $lng === false
    || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_resposta(['ok' => false, 'erro' => 'Coordenadas inválidas.'], 400);
}

// No máximo 1 consulta por segundo por sessão (política de uso do Nominatim).
$espera = 1.0 - (microtime(true) - (float) ($_SESSION['geo_ultima'] ?? 0));
if ($espera > 0) {
    usleep((int) ($espera * 1_000_000));
}
$_SESSION['geo_ultima'] = microtime(true);
session_write_close(); // libera a sessão enquanto aguarda o serviço externo

$endereco = NOMINATIM_URL . '?' . http_build_query([
    'format' => 'jsonv2', 'lat' => $lat, 'lon' => $lng, 'zoom' => 18,
    'addressdetails' => 1, 'accept-language' => 'pt-BR',
]);

$corpo = false;
if (function_exists('curl_init')) {
    $ch = curl_init($endereco);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => NOMINATIM_USER_AGENT,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $corpo = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        $corpo = false;
    }
    curl_close($ch);
} elseif (ini_get('allow_url_fopen')) {
    $corpo = @file_get_contents($endereco, false, stream_context_create([
        'http' => ['timeout' => 8, 'header' => "User-Agent: " . NOMINATIM_USER_AGENT . "\r\nAccept: application/json\r\n"],
    ]));
}

$dados = $corpo ? json_decode($corpo, true) : null;
if (!is_array($dados) || empty($dados['address'])) {
    json_resposta(['ok' => false, 'erro' => 'Não foi possível obter o endereço automaticamente.'], 502);
}

$a = $dados['address'];
$primeiro = function (array $chaves) use ($a): string {
    foreach ($chaves as $c) {
        if (!empty($a[$c]) && is_string($a[$c])) {
            return $a[$c];
        }
    }
    return '';
};

json_resposta([
    'ok'       => true,
    'endereco' => mb_substr($primeiro(['road', 'pedestrian', 'footway', 'residential', 'path', 'square', 'hamlet']), 0, 160),
    'bairro'   => mb_substr($primeiro(['suburb', 'neighbourhood', 'quarter', 'city_district', 'village', 'hamlet']), 0, 80),
    'cidade'   => $primeiro(['city', 'town', 'municipality', 'village']),
    'numero'   => mb_substr($primeiro(['house_number']), 0, 60),
]);
