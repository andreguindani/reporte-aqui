<?php
/**
 * Detalhe de uma solicitação. Página pública (consulta por protocolo),
 * com ações extras para o autor (avaliar) e para outros moradores (apoiar).
 */
require_once __DIR__ . '/../app/iniciar.php';

$protocolo = isset($_GET['protocolo']) && is_string($_GET['protocolo']) ? $_GET['protocolo'] : '';
$s = $protocolo !== '' ? buscar_solicitacao_protocolo($protocolo) : null;

if (!$s) {
    http_response_code(404);
    $titulo = 'Solicitação não encontrada';
    require __DIR__ . '/../app/layout/cabecalho.php';
    echo '<div class="container ra-estreito py-5 text-center"><h1 class="h3">Solicitação não encontrada</h1>'
       . '<p class="text-body-secondary">Confira o número do protocolo e tente novamente.</p>'
       . '<a class="btn btn-primary" href="' . e(url('public/consulta.php')) . '">Consultar protocolo</a></div>';
    require __DIR__ . '/../app/layout/rodape.php';
    exit;
}

$u        = usuario();
$id       = (int) $s['id'];
$souAutor = $u && (int) $u['id'] === (int) $s['usuario_id'];
$aqui     = 'public/solicitacao.php?protocolo=' . urlencode($s['protocolo']);

$stAval = db()->prepare('SELECT nota, comentario, criado_em FROM avaliacoes WHERE solicitacao_id = ?');
$stAval->execute([$id]);
$avaliacao = $stAval->fetch() ?: null;

$erroAvaliacao = '';

// ---------------------------------------------------------------- Ações (POST)
if (eh_post()) {
    csrf_exigir();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'apoiar') {
        exigir_login();
        [$ok, $mensagem] = apoiar_solicitacao($id, $u);
        flash($ok ? 'sucesso' : 'erro', $mensagem);
        redirecionar($aqui);
    }

    if ($acao === 'avaliar') {
        exigir_login();
        $nota = filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_INT);
        $comentario = post_texto('comentario');
        if (!$souAutor) {
            $erroAvaliacao = 'Somente quem registrou a solicitação pode avaliar o atendimento.';
        } elseif ($s['situacao'] !== 'concluida') {
            $erroAvaliacao = 'A avaliação fica disponível quando a solicitação for concluída.';
        } elseif ($avaliacao) {
            $erroAvaliacao = 'Você já avaliou este atendimento.';
        } elseif (!$nota || $nota < 1 || $nota > 5) {
            $erroAvaliacao = 'Escolha uma nota de 1 a 5 estrelas.';
        } elseif (mb_strlen($comentario) > 300) {
            $erroAvaliacao = 'O comentário pode ter no máximo 300 caracteres.';
        } else {
            try {
                db()->prepare('INSERT INTO avaliacoes (solicitacao_id, usuario_id, nota, comentario) VALUES (?, ?, ?, ?)')
                    ->execute([$id, $u['id'], $nota, $comentario !== '' ? $comentario : null]);
                flash('sucesso', 'Obrigado pela sua avaliação!');
            } catch (PDOException $ex) {
                flash('erro', 'Este atendimento já foi avaliado.');
            }
            redirecionar($aqui);
        }
    }
}

// ---------------------------------------------------------------- Dados
$sqlHist = 'SELECT h.situacao, h.mensagem, h.visivel_cidadao, h.criado_em, h.autor_id, a.nome AS autor_nome, a.perfil AS autor_perfil
              FROM historico h JOIN usuarios a ON a.id = h.autor_id
             WHERE h.solicitacao_id = ?' . (eh_admin() ? '' : ' AND h.visivel_cidadao = 1') . '
             ORDER BY h.criado_em DESC, h.id DESC';
$stHist = db()->prepare($sqlHist);
$stHist->execute([$id]);
$historico = $stHist->fetchAll();

$stFotos = db()->prepare('SELECT arquivo FROM fotos WHERE solicitacao_id = ? ORDER BY id');
$stFotos->execute([$id]);
$fotos = $stFotos->fetchAll(PDO::FETCH_COLUMN);

$jaApoia   = $u && eh_cidadao() && !$souAutor && usuario_apoia($id, (int) $u['id']);
$atrasada  = esta_atrasada($s);
$indice    = situacao_indice($s['situacao']);
$concluida = $s['situacao'] === 'concluida';

$titulo = $s['protocolo'];
$pagina_ativa = $souAutor ? 'minhas' : '';
$usa_mapa = true;
require __DIR__ . '/../app/layout/cabecalho.php';
?>
<div class="container py-4">
<?php
// Botão "Voltar": retorna à página anterior do site; se não houver, vai para a lista mais provável.
if (eh_admin()) {
    [$voltarUrl, $voltarTexto] = ['public/admin/index.php', 'Voltar ao painel'];
} elseif ($souAutor) {
    [$voltarUrl, $voltarTexto] = ['public/minhas.php', 'Voltar às minhas solicitações'];
} else {
    [$voltarUrl, $voltarTexto] = ['public/index.php', 'Voltar ao início'];
}
?>
  <a class="btn btn-outline-primary btn-sm ra-voltar mb-3" href="<?= e(url($voltarUrl)) ?>" data-voltar
     aria-label="<?= e($voltarTexto) ?>"><i class="bi bi-arrow-left"></i> Voltar</a>

<?php if (!empty($_GET['nova']) && $souAutor): ?>
  <div class="alert alert-success d-flex gap-3 align-items-start" role="status">
    <i class="bi bi-check-circle-fill fs-3"></i>
    <div>
      <h2 class="h5 mb-1">Solicitação registrada!</h2>
      Anote o seu protocolo: <strong class="ra-protocolo fs-5"><?= e($s['protocolo']) ?></strong><br>
      Setor responsável: <strong><?= e($s['setor']) ?></strong> ·
      Previsão de atendimento: <strong><?= e(data_br($s['previsao'])) ?></strong>
      (<?= (int) $s['prazo_dias'] ?> dias).
    </div>
  </div>
<?php endif; ?>

  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
      <div class="ra-item-meta mb-1">Protocolo <span class="ra-protocolo"><?= e($s['protocolo']) ?></span></div>
      <h1 class="h3 mb-1"><i class="bi <?= e($s['icone']) ?> text-primary"></i> <?= e($s['categoria']) ?></h1>
      <div class="text-body-secondary"><?= e($s['subcategoria']) ?> · <?= e(endereco_completo($s)) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <?= badge_situacao($s['situacao']) ?>
<?php if ($atrasada): ?>
      <span class="badge atrasada"><i class="bi bi-exclamation-triangle-fill"></i> Atrasada</span>
<?php endif; ?>
<?php if (eh_admin()): ?>
      <?= badge_prioridade($s['prioridade']) ?>
      <a class="btn btn-sm btn-outline-primary" href="<?= e(url('public/admin/index.php?situacao=todas&q=' . urlencode($s['protocolo']) . '&abrir=' . $id)) ?>">
        <i class="bi bi-pencil-square"></i> Atender no painel</a>
<?php endif; ?>
    </div>
  </div>

  <div class="card ra-card mb-4">
    <div class="card-body py-4">
      <ol class="ra-progresso" aria-label="Andamento da solicitação">
<?php $i = 0; foreach (SITUACOES as $chave => $nome):
        $classe = $i < $indice || ($concluida && $i === $indice) ? 'feito' : ($i === $indice ? 'atual' : ''); ?>
        <li class="<?= $classe ?>"<?= $i === $indice ? ' aria-current="step"' : '' ?>><?= e($nome) ?></li>
<?php $i++; endforeach; ?>
      </ol>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card ra-card mb-4">
        <div class="card-body">
          <h2 class="h5">Descrição</h2>
          <p class="ra-texto-livre"><?= e($s['descricao']) ?></p>

<?php if ($fotos): ?>
          <h2 class="h6 mt-4">Fotos</h2>
          <div class="ra-fotos">
<?php   foreach ($fotos as $n => $arquivo): ?>
            <a href="<?= e(url('uploads/' . $arquivo)) ?>" target="_blank" rel="noopener">
              <img src="<?= e(url('uploads/' . $arquivo)) ?>" alt="Foto <?= $n + 1 ?> do problema" loading="lazy">
            </a>
<?php   endforeach; ?>
          </div>
<?php endif; ?>
        </div>
      </div>

      <div class="card ra-card mb-4">
        <div class="card-body">
          <h2 class="h5 mb-3">Linha do tempo</h2>
          <ol class="ra-linha">
<?php foreach ($historico as $h):
        if ($h['autor_perfil'] === 'admin') {
            $autor = eh_admin() ? $h['autor_nome'] : 'Prefeitura de Videira';
        } else {
            $autor = $u && (int) $u['id'] === (int) $h['autor_id'] ? 'Você' : 'Morador(a)';
        } ?>
            <li style="--cor: var(--sit-<?= e($h['situacao']) ?>)">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <strong><?= e(situacao_nome($h['situacao'])) ?></strong>
                <span class="quando"><?= e(data_br($h['criado_em'], true)) ?> · <?= e($autor) ?></span>
              </div>
<?php   if (!$h['visivel_cidadao']): ?>
              <div class="interno mt-1"><i class="bi bi-eye-slash"></i> Registro interno: <?= e($h['mensagem']) ?></div>
<?php   else: ?>
              <div class="ra-texto-livre"><?= e($h['mensagem']) ?></div>
<?php   endif; ?>
            </li>
<?php endforeach; ?>
          </ol>
        </div>
      </div>

<?php if ($concluida): ?>
      <div class="card ra-card mb-4" id="avaliacao">
        <div class="card-body">
          <h2 class="h5">Avaliação do atendimento</h2>
<?php   if ($avaliacao): ?>
          <div class="ra-nota" aria-label="Nota <?= (int) $avaliacao['nota'] ?> de 5">
            <?= str_repeat('★', (int) $avaliacao['nota']) . str_repeat('☆', 5 - (int) $avaliacao['nota']) ?>
          </div>
<?php     if ($avaliacao['comentario']): ?>
          <p class="mb-1 ra-texto-livre">“<?= e($avaliacao['comentario']) ?>”</p>
<?php     endif; ?>
          <div class="ra-item-meta">Avaliado em <?= e(data_br($avaliacao['criado_em'])) ?></div>
<?php   elseif ($souAutor): ?>
          <p class="text-body-secondary">O problema foi resolvido? Sua nota ajuda a prefeitura a melhorar.</p>
<?php     if ($erroAvaliacao): ?>
          <div class="alert alert-danger" role="alert"><?= e($erroAvaliacao) ?></div>
<?php     endif; ?>
          <form method="post" action="<?= e(url($aqui)) ?>#avaliacao">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="avaliar">
            <fieldset class="mb-3">
              <legend class="form-label fs-6">Sua nota</legend>
              <div class="ra-estrelas">
<?php     for ($n = 5; $n >= 1; $n--): ?>
                <input type="radio" name="nota" id="nota-<?= $n ?>" value="<?= $n ?>" required
                       <?= (int) ($_POST['nota'] ?? 0) === $n ? 'checked' : '' ?>>
                <label for="nota-<?= $n ?>" title="<?= $n ?> de 5"><span class="visually-hidden"><?= $n ?> de 5</span>★</label>
<?php     endfor; ?>
              </div>
            </fieldset>
            <div class="mb-3">
              <label for="comentario" class="form-label">Comentário (opcional)</label>
              <textarea class="form-control" id="comentario" name="comentario" rows="2" maxlength="300"><?= e($_POST['comentario'] ?? '') ?></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Enviar avaliação</button>
          </form>
<?php   else: ?>
          <p class="text-body-secondary mb-0">Ainda não avaliado por quem registrou a solicitação.</p>
<?php   endif; ?>
        </div>
      </div>
<?php endif; ?>
    </div>

    <div class="col-lg-5">
      <div class="card ra-card mb-4">
        <div class="card-body">
          <div data-mapa-fixo class="ra-mapa ra-mapa-pequeno mb-3" data-lat="<?= e($s['latitude']) ?>"
               data-lng="<?= e($s['longitude']) ?>" data-situacao="<?= e($s['situacao']) ?>"
               role="img" aria-label="Mapa com o local da solicitação"></div>
          <dl class="ra-resumo mb-0">
            <dt>Local</dt>
            <dd><?= e(endereco_completo($s)) ?><?= $s['referencia'] ? '<br><span class="text-body-secondary">Referência: ' . e($s['referencia']) . '</span>' : '' ?></dd>
            <dt>Setor responsável</dt>
            <dd><?= e($s['setor']) ?></dd>
            <dt>Registrada em</dt>
            <dd><?= e(data_br($s['criado_em'], true)) ?></dd>
<?php if ($concluida): ?>
            <dt>Concluída em</dt>
            <dd><?= e(data_br($s['concluido_em'], true)) ?></dd>
<?php else: ?>
            <dt>Previsão de atendimento</dt>
            <dd><?= e(data_br($s['previsao'])) ?>
<?php   if ($atrasada): ?>
              <span class="badge atrasada ms-1">Atrasada</span>
              <div class="small text-danger mt-1">O prazo previsto já passou. A prefeitura foi sinalizada sobre o atraso.</div>
<?php   endif; ?>
            </dd>
<?php endif; ?>
            <dt>Registrada por</dt>
            <dd class="mb-0"><?= e(nome_solicitante($s)) ?>
<?php if ($s['sigilo'] && ($souAutor || eh_admin())): ?>
              <span class="badge text-bg-secondary"><i class="bi bi-eye-slash"></i> nome em sigilo</span>
<?php endif; ?>
            </dd>
          </dl>
        </div>
      </div>

      <div class="card ra-card">
        <div class="card-body">
          <h2 class="h6"><i class="bi bi-hand-thumbs-up"></i>
            <?= (int) $s['apoios'] ?> <?= (int) $s['apoios'] === 1 ? 'apoio' : 'apoios' ?></h2>
<?php if ($concluida): ?>
          <p class="text-body-secondary small mb-0">Solicitação concluída — não recebe novos apoios.</p>
<?php elseif ($souAutor): ?>
          <p class="text-body-secondary small mb-0">Outros moradores afetados pelo mesmo problema podem apoiar a sua solicitação.</p>
<?php elseif ($jaApoia): ?>
          <p class="text-success small mb-0"><i class="bi bi-check-lg"></i> Você apoia esta solicitação.</p>
<?php elseif (eh_admin()): ?>
          <p class="text-body-secondary small mb-0">Moradores afetados pelo mesmo problema.</p>
<?php elseif ($u): ?>
          <p class="text-body-secondary small">Este problema também afeta você? Apoie em vez de abrir uma solicitação repetida.</p>
          <form method="post" action="<?= e(url($aqui)) ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="apoiar">
            <button class="btn btn-acao w-100" type="submit"><i class="bi bi-hand-thumbs-up"></i> Apoiar esta solicitação</button>
          </form>
<?php else: ?>
          <p class="text-body-secondary small">Este problema também afeta você?</p>
          <a class="btn btn-outline-primary w-100" href="<?= e(url('public/login.php')) ?>">Entre para apoiar</a>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../app/layout/rodape.php'; ?>
