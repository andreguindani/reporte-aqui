<?php
/**
 * GET /api/proximas.php?categoria_id=..&lat=..&lng=..
 * Procura solicitação em aberto do mesmo tipo a menos de 50 metros.
 */
require_once __DIR__ . '/../app/iniciar.php';
api_exigir_login();

$categoria = filter_input(INPUT_GET, 'categoria_id', FILTER_VALIDATE_INT);
$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
if (!$categoria || !is_float($lat) || !is_float($lng)) {
    json_resposta(['ok' => false, 'erro' => 'Parâmetros inválidos.'], 400);
}

$p = buscar_solicitacao_proxima($categoria, $lat, $lng);
if (!$p) {
    json_resposta(['ok' => true, 'proxima' => null]);
}

$u = usuario();
json_resposta(['ok' => true, 'proxima' => [
    'id'        => (int) $p['id'],
    'protocolo' => $p['protocolo'],
    'endereco'  => endereco_completo($p),
    'situacao'  => situacao_nome($p['situacao']),
    'distancia' => (int) round($p['distancia']),
    'apoios'    => (int) $p['apoios'],
    'data'      => data_br($p['criado_em']),
    'propria'   => (int) $p['usuario_id'] === (int) $u['id'],
    'ja_apoia'  => usuario_apoia((int) $p['id'], (int) $u['id']),
]]);
