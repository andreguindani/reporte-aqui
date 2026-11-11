<?php
/**
 * GET /api/admin_solicitacao.php?id=..
 * Dados completos de uma solicitação para o painel lateral da prefeitura.
 */
require_once __DIR__ . '/../app/iniciar.php';
api_exigir_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$s = $id ? buscar_solicitacao_id($id) : null;
if (!$s) {
    json_resposta(['ok' => false, 'erro' => 'Solicitação não encontrada.'], 404);
}

$pdo = db();
$st = $pdo->prepare('SELECT nome, telefone, email FROM usuarios WHERE id = ?');
$st->execute([$s['usuario_id']]);
$autor = $st->fetch();

$st = $pdo->prepare(
    'SELECT h.situacao, h.mensagem, h.visivel_cidadao, h.criado_em, u.nome AS autor, u.perfil
       FROM historico h JOIN usuarios u ON u.id = h.autor_id
      WHERE h.solicitacao_id = ? ORDER BY h.criado_em DESC, h.id DESC'
);
$st->execute([$id]);
$historico = array_map(fn($h) => [
    'situacao' => $h['situacao'],
    'mensagem' => $h['mensagem'],
    'interno'  => !$h['visivel_cidadao'],
    'quando'   => data_br($h['criado_em'], true),
    'autor'    => $h['perfil'] === 'admin' ? $h['autor'] : 'Morador(a)',
], $st->fetchAll());

$st = $pdo->prepare(
    'SELECT n.nota, n.criado_em, u.nome AS autor FROM notas_internas n JOIN usuarios u ON u.id = n.autor_id
      WHERE n.solicitacao_id = ? ORDER BY n.criado_em DESC, n.id DESC'
);
$st->execute([$id]);
$notas = array_map(fn($n) => [
    'nota' => $n['nota'], 'quando' => data_br($n['criado_em'], true), 'autor' => $n['autor'],
], $st->fetchAll());

$st = $pdo->prepare('SELECT arquivo FROM fotos WHERE solicitacao_id = ? ORDER BY id');
$st->execute([$id]);
$fotos = array_map(fn($a) => url('uploads/' . $a), $st->fetchAll(PDO::FETCH_COLUMN));

$st = $pdo->prepare('SELECT nota, comentario FROM avaliacoes WHERE solicitacao_id = ?');
$st->execute([$id]);
$avaliacao = $st->fetch() ?: null;

json_resposta(['ok' => true, 'solicitacao' => [
    'id'           => (int) $s['id'],
    'protocolo'    => $s['protocolo'],
    'categoria'    => $s['categoria'],
    'subcategoria' => $s['subcategoria'],
    'situacao'     => $s['situacao'],
    'prioridade'   => $s['prioridade'],
    'setor_id'     => (int) $s['setor_id'],
    'descricao'    => $s['descricao'],
    'endereco'     => endereco_completo($s),
    'referencia'   => $s['referencia'],
    'lat'          => (float) $s['latitude'],
    'lng'          => (float) $s['longitude'],
    'registrada'   => data_br($s['criado_em'], true),
    'previsao'     => data_br($s['previsao']),
    'concluida_em' => $s['concluido_em'] ? data_br($s['concluido_em'], true) : null,
    'atrasada'     => esta_atrasada($s),
    'apoios'       => (int) $s['apoios'],
    'sigilo'       => (bool) $s['sigilo'],
    'solicitante'  => ['nome' => $autor['nome'], 'telefone' => formatar_telefone($autor['telefone']), 'email' => $autor['email']],
    'link'         => url('public/solicitacao.php?protocolo=' . urlencode($s['protocolo'])),
    'fotos'        => $fotos,
    'historico'    => $historico,
    'notas'        => $notas,
    'avaliacao'    => $avaliacao ? ['nota' => (int) $avaliacao['nota'], 'comentario' => $avaliacao['comentario']] : null,
]]);
