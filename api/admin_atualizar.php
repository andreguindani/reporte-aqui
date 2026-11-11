<?php
/**
 * POST /api/admin_atualizar.php
 * Campos: id, situacao, prioridade, setor_id, mensagem, nota, csrf
 *
 * Regras:
 *  - a situação só avança (Recebida → Em análise → Em execução → Concluída);
 *  - mudar a situação exige uma mensagem ao cidadão;
 *  - toda mudança grava um registro no histórico (data, situação, mensagem e autor);
 *  - a nota interna é opcional e só a prefeitura vê.
 */
require_once __DIR__ . '/../app/iniciar.php';

if (!eh_post()) {
    json_resposta(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}
api_exigir_admin();
api_csrf_exigir();

$id         = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$situacao   = post_texto('situacao');
$prioridade = post_texto('prioridade');
$setorId    = filter_input(INPUT_POST, 'setor_id', FILTER_VALIDATE_INT);
$mensagem   = post_texto('mensagem');
$nota       = post_texto('nota');
$admin      = usuario();

$erros = [];
if (!isset(SITUACOES[$situacao])) {
    $erros['situacao'] = 'Situação inválida.';
}
if (!isset(PRIORIDADES[$prioridade])) {
    $erros['prioridade'] = 'Prioridade inválida.';
}
if (mb_strlen($mensagem) > 500) {
    $erros['mensagem'] = 'A mensagem pode ter no máximo 500 caracteres.';
}
if (mb_strlen($nota) > 1000) {
    $erros['nota'] = 'A nota interna pode ter no máximo 1000 caracteres.';
}
if (!$id || $erros) {
    json_resposta(['ok' => false, 'erro' => 'Confira os campos destacados.', 'erros' => $erros], 422);
}

$pdo = db();
try {
    $pdo->beginTransaction();

    // Trava a linha para que dois servidores não alterem a mesma solicitação ao mesmo tempo.
    $st = $pdo->prepare(
        'SELECT s.id, s.protocolo, s.situacao, s.prioridade, s.setor_id, st.nome AS setor
           FROM solicitacoes s JOIN setores st ON st.id = s.setor_id WHERE s.id = ? FOR UPDATE'
    );
    $st->execute([$id]);
    $atual = $st->fetch();
    if (!$atual) {
        $pdo->rollBack();
        json_resposta(['ok' => false, 'erro' => 'Solicitação não encontrada.'], 404);
    }

    $st = $pdo->prepare('SELECT nome FROM setores WHERE id = ?');
    $st->execute([$setorId ?: 0]);
    $novoSetor = $st->fetchColumn();
    if ($novoSetor === false) {
        $erros['setor_id'] = 'Setor inválido.';
    }

    $mudouSituacao   = $situacao !== $atual['situacao'];
    $mudouPrioridade = $prioridade !== $atual['prioridade'];
    $mudouSetor      = (int) $setorId !== (int) $atual['setor_id'];

    if ($mudouSituacao && situacao_indice($situacao) < situacao_indice($atual['situacao'])) {
        $erros['situacao'] = 'A situação só pode avançar. Esta solicitação já está em "'
            . situacao_nome($atual['situacao']) . '".';
    }
    if ($mudouSituacao && $mensagem === '') {
        $erros['mensagem'] = 'Escreva uma mensagem ao cidadão explicando a mudança de situação.';
    }
    if (!$erros && !$mudouSituacao && !$mudouPrioridade && !$mudouSetor && $mensagem === '' && $nota === '') {
        $erros['geral'] = 'Nenhuma alteração para salvar.';
    }
    if ($erros) {
        $pdo->rollBack();
        json_resposta(['ok' => false, 'erro' => $erros['geral'] ?? 'Confira os campos destacados.', 'erros' => $erros], 422);
    }

    $hist = $pdo->prepare(
        'INSERT INTO historico (solicitacao_id, situacao, mensagem, autor_id, visivel_cidadao) VALUES (?, ?, ?, ?, ?)'
    );

    if ($mudouPrioridade) {
        // Registro interno: o cidadão não vê a prioridade.
        $hist->execute([$id, $atual['situacao'],
            'Prioridade alterada de ' . prioridade_nome($atual['prioridade']) . ' para ' . prioridade_nome($prioridade) . '.',
            $admin['id'], 0]);
    }
    if ($mudouSetor) {
        $hist->execute([$id, $atual['situacao'],
            'Solicitação encaminhada de ' . $atual['setor'] . ' para ' . $novoSetor . '.', $admin['id'], 1]);
    }
    if ($mudouSituacao || $mensagem !== '') {
        $hist->execute([$id, $situacao, $mensagem, $admin['id'], 1]);
    }
    if ($nota !== '') {
        $pdo->prepare('INSERT INTO notas_internas (solicitacao_id, autor_id, nota) VALUES (?, ?, ?)')
            ->execute([$id, $admin['id'], $nota]);
    }

    $pdo->prepare(
        "UPDATE solicitacoes
            SET situacao = ?, prioridade = ?, setor_id = ?,
                concluido_em = IF(? = 'concluida' AND concluido_em IS NULL, NOW(), concluido_em)
          WHERE id = ?"
    )->execute([$situacao, $prioridade, $setorId, $situacao, $id]);

    $pdo->commit();
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Reporte Aqui: ' . $ex->getMessage());
    json_resposta(['ok' => false, 'erro' => 'Não foi possível salvar agora. Tente novamente.'], 500);
}

flash('sucesso', 'Solicitação ' . $atual['protocolo'] . ' atualizada.');
json_resposta(['ok' => true]);
