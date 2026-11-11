<?php
/**
 * POST /api/apoiar.php  (id, csrf)
 * Registra o apoio do cidadão logado a uma solicitação existente.
 */
require_once __DIR__ . '/../app/iniciar.php';

if (!eh_post()) {
    json_resposta(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}
api_exigir_login();
api_csrf_exigir();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    json_resposta(['ok' => false, 'erro' => 'Solicitação inválida.'], 400);
}

[$ok, $mensagem] = apoiar_solicitacao($id, usuario());
if (!$ok) {
    json_resposta(['ok' => false, 'erro' => $mensagem], 422);
}
$s = buscar_solicitacao_id($id);
flash('sucesso', $mensagem);
json_resposta(['ok' => true, 'mensagem' => $mensagem, 'protocolo' => $s['protocolo'], 'apoios' => (int) $s['apoios']]);
