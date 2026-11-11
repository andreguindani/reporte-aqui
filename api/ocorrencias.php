<?php
/**
 * GET /api/ocorrencias.php?categoria=ID&situacao=CHAVE
 * Pontos para o mapa público. Nunca devolve dados pessoais.
 */
require_once __DIR__ . '/../app/iniciar.php';

$onde = [];
$params = [];

$categoria = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT);
if ($categoria) {
    $onde[] = 's.categoria_id = ?';
    $params[] = $categoria;
}
$situacao = $_GET['situacao'] ?? '';
if (is_string($situacao) && isset(SITUACOES[$situacao])) {
    $onde[] = 's.situacao = ?';
    $params[] = $situacao;
}

$sql = 'SELECT s.protocolo, s.situacao, s.endereco, s.numero, s.bairro, s.latitude, s.longitude, s.criado_em,
               c.nome AS categoria
          FROM solicitacoes s JOIN categorias c ON c.id = s.categoria_id'
     . ($onde ? ' WHERE ' . implode(' AND ', $onde) : '')
     . ' ORDER BY s.criado_em DESC LIMIT 1000';
$st = db()->prepare($sql);
$st->execute($params);

$ocorrencias = [];
foreach ($st as $s) {
    $ocorrencias[] = [
        'protocolo' => $s['protocolo'],
        'situacao'  => $s['situacao'],
        'categoria' => $s['categoria'],
        'endereco'  => endereco_completo($s),
        'lat'       => (float) $s['latitude'],
        'lng'       => (float) $s['longitude'],
        'data'      => data_br($s['criado_em']),
    ];
}
json_resposta(['ok' => true, 'ocorrencias' => $ocorrencias]);
