<?php
require_once __DIR__ . '/../app/iniciar.php';
exigir_cidadao();

$u = usuario();
$abas = ['abertas' => 'Em aberto', 'concluidas' => 'Concluídas', 'todas' => 'Todas'];
$aba = isset($_GET['aba']) && is_string($_GET['aba']) && isset($abas[$_GET['aba']]) ? $_GET['aba'] : 'abertas';

$filtro = ['abertas' => " AND s.situacao <> 'concluida'", 'concluidas' => " AND s.situacao = 'concluida'", 'todas' => ''][$aba];

$st = db()->prepare(
    "SELECT s.id, s.protocolo, s.situacao, s.endereco, s.numero, s.bairro, s.previsao, s.criado_em, s.concluido_em,
            c.nome AS categoria, c.icone,
            (SELECT COUNT(*) FROM apoios a WHERE a.solicitacao_id = s.id) AS apoios,
            (SELECT nota FROM avaliacoes av WHERE av.solicitacao_id = s.id) AS nota
       FROM solicitacoes s JOIN categorias c ON c.id = s.categoria_id
      WHERE s.usuario_id = ? $filtro
      ORDER BY s.criado_em DESC"
);
$st->execute([$u['id']]);
$lista = $st->fetchAll();

$stCont = db()->prepare(
    "SELECT SUM(situacao <> 'concluida') AS abertas, SUM(situacao = 'concluida') AS concluidas, COUNT(*) AS todas
       FROM solicitacoes WHERE usuario_id = ?"
);
$stCont->execute([$u['id']]);
$contagem = $stCont->fetch();

$stApoiadas = db()->prepare(
    "SELECT s.protocolo, s.situacao, s.endereco, s.numero, s.bairro, c.nome AS categoria, c.icone
       FROM apoios a JOIN solicitacoes s ON s.id = a.solicitacao_id JOIN categorias c ON c.id = s.categoria_id
      WHERE a.usuario_id = ? ORDER BY a.criado_em DESC"
);
$stApoiadas->execute([$u['id']]);
$apoiadas = $stApoiadas->fetchAll();

$titulo = 'Minhas solicitações';
$pagina_ativa = 'minhas';
require __DIR__ . '/../app/layout/cabecalho.php';
?>
<div class="container ra-medio py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Minhas solicitações</h1>
    <a class="btn btn-acao" href="<?= e(url('public/nova.php')) ?>"><i class="bi bi-plus-lg"></i> Nova solicitação</a>
  </div>

  <ul class="nav nav-pills mb-3">
<?php foreach ($abas as $chave => $nome): ?>
    <li class="nav-item">
      <a class="nav-link<?= $aba === $chave ? ' active' : '' ?>"<?= $aba === $chave ? ' aria-current="page"' : '' ?>
         href="<?= e(url('public/minhas.php?aba=' . $chave)) ?>">
        <?= e($nome) ?> <span class="badge text-bg-light ms-1"><?= (int) $contagem[$chave] ?></span>
      </a>
    </li>
<?php endforeach; ?>
  </ul>

<?php if (!$lista): ?>
  <div class="card ra-card">
    <div class="card-body text-center py-5">
      <i class="bi bi-inbox fs-1 text-body-secondary"></i>
      <p class="mt-2 mb-3 text-body-secondary">
        <?= $aba === 'concluidas' ? 'Você ainda não tem solicitações concluídas.'
            : ($aba === 'abertas' ? 'Você não tem solicitações em aberto.' : 'Você ainda não registrou nenhuma solicitação.') ?>
      </p>
      <a class="btn btn-primary" href="<?= e(url('public/nova.php')) ?>">Registrar um problema</a>
    </div>
  </div>
<?php else: ?>
  <div class="d-grid gap-2">
<?php   foreach ($lista as $s): ?>
    <a class="ra-item" href="<?= e(url('public/solicitacao.php?protocolo=' . urlencode($s['protocolo']))) ?>">
      <div class="ra-item-icone"><i class="bi <?= e($s['icone']) ?>"></i></div>
      <div class="flex-grow-1">
        <div class="d-flex flex-wrap justify-content-between gap-2">
          <span class="ra-item-titulo"><?= e($s['categoria']) ?></span>
          <span class="d-flex gap-1 align-items-center">
<?php     if (esta_atrasada($s)): ?>
            <span class="badge atrasada">Atrasada</span>
<?php     endif; ?>
            <?= badge_situacao($s['situacao']) ?>
          </span>
        </div>
        <div><?= e(endereco_completo($s)) ?></div>
        <div class="ra-item-meta">
          <span class="ra-protocolo"><?= e($s['protocolo']) ?></span> · registrada em <?= e(data_br($s['criado_em'])) ?> ·
<?php     if ($s['situacao'] === 'concluida'): ?>
          concluída em <?= e(data_br($s['concluido_em'])) ?>
          · <?= $s['nota'] ? 'sua nota: ' . (int) $s['nota'] . '/5' : '<strong class="text-primary">avalie o atendimento</strong>' ?>
<?php     else: ?>
          previsão: <?= e(data_br($s['previsao'])) ?>
<?php     endif; ?>
<?php     if ($s['apoios'] > 0): ?>
          · <i class="bi bi-hand-thumbs-up"></i> <?= (int) $s['apoios'] ?>
<?php     endif; ?>
        </div>
      </div>
    </a>
<?php   endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($apoiadas): ?>
  <h2 class="h5 mt-5 mb-3">Solicitações que você apoia</h2>
  <div class="d-grid gap-2">
<?php   foreach ($apoiadas as $s): ?>
    <a class="ra-item" href="<?= e(url('public/solicitacao.php?protocolo=' . urlencode($s['protocolo']))) ?>">
      <div class="ra-item-icone"><i class="bi <?= e($s['icone']) ?>"></i></div>
      <div class="flex-grow-1">
        <div class="d-flex flex-wrap justify-content-between gap-2">
          <span class="ra-item-titulo"><?= e($s['categoria']) ?></span>
          <?= badge_situacao($s['situacao']) ?>
        </div>
        <div><?= e(endereco_completo($s)) ?></div>
        <div class="ra-item-meta"><span class="ra-protocolo"><?= e($s['protocolo']) ?></span></div>
      </div>
    </a>
<?php   endforeach; ?>
  </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../app/layout/rodape.php'; ?>
