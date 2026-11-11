<?php
require_once __DIR__ . '/../app/iniciar.php';

$pdo = db();
$categorias = listar_categorias();

$numeros = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM solicitacoes WHERE criado_em >= NOW() - INTERVAL 30 DAY) AS recentes,
        (SELECT COUNT(*) FROM solicitacoes WHERE situacao = 'concluida') AS concluidas,
        (SELECT AVG(TIMESTAMPDIFF(HOUR, criado_em, concluido_em)) / 24
           FROM solicitacoes WHERE situacao = 'concluida' AND concluido_em IS NOT NULL) AS media_dias"
)->fetch();

$ultimas = $pdo->query(
    "SELECT s.protocolo, s.situacao, s.endereco, s.numero, s.bairro, s.criado_em, c.nome AS categoria, c.icone,
            (SELECT COUNT(*) FROM apoios a WHERE a.solicitacao_id = s.id) AS apoios
       FROM solicitacoes s JOIN categorias c ON c.id = s.categoria_id
      ORDER BY s.criado_em DESC LIMIT 6"
)->fetchAll();

$titulo = 'Início';
$pagina_ativa = 'inicio';
$usa_mapa = true;
$scripts = ['inicio.js'];
require __DIR__ . '/../app/layout/cabecalho.php';
?>
<section class="ra-hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <h1>Viu um problema na sua rua?<br>Reporte aqui.</h1>
        <p class="lead mt-3">Registre buracos, lâmpadas apagadas, lixo acumulado e outros problemas de Videira
          com foto e localização no mapa, e acompanhe o atendimento até a prefeitura resolver.</p>
        <div class="d-flex flex-wrap gap-2 mt-4">
<?php if (eh_admin()): ?>
          <a class="btn btn-acao btn-lg" href="<?= e(url('public/admin/index.php')) ?>">Abrir o painel da prefeitura</a>
<?php else: ?>
          <a class="btn btn-acao btn-lg" href="<?= e(url('public/nova.php')) ?>"><i class="bi bi-geo-alt-fill"></i> Registrar um problema</a>
<?php endif; ?>
          <a class="btn btn-outline-light btn-lg" href="<?= e(url('public/como-funciona.php')) ?>">Como funciona</a>
        </div>
      </div>
      <div class="col-lg-5">
        <form class="ra-busca" method="get" action="<?= e(url('public/consulta.php')) ?>">
          <label for="protocolo-hero" class="form-label">Já tem um protocolo?</label>
          <div class="input-group">
            <input type="text" class="form-control" id="protocolo-hero" name="protocolo"
                   placeholder="RA-<?= date('Y') ?>-0001" maxlength="20" required>
            <button class="btn btn-light" type="submit">Consultar</button>
          </div>
          <div class="form-text text-white-50">A consulta é pública e não exige login.</div>
        </form>
      </div>
    </div>
  </div>
</section>

<div class="container">
  <div class="row g-3 ra-numeros">
    <div class="col-md-4">
      <div class="ra-numero">
        <div class="valor"><?= (int) $numeros['recentes'] ?></div>
        <div class="rotulo">solicitações nos últimos 30 dias</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="ra-numero">
        <div class="valor"><?= (int) $numeros['concluidas'] ?></div>
        <div class="rotulo">problemas resolvidos</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="ra-numero">
        <div class="valor"><?= e(formatar_dias($numeros['media_dias'] !== null ? (float) $numeros['media_dias'] : null)) ?></div>
        <div class="rotulo">tempo médio para concluir</div>
      </div>
    </div>
  </div>

  <section class="mt-5" aria-labelledby="titulo-mapa">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
      <div>
        <h2 class="h4 mb-1" id="titulo-mapa">Mapa das ocorrências</h2>
        <div class="ra-legenda">
<?php foreach (SITUACOES as $chave => $nome): ?>
          <span><span class="ra-ponto sit-<?= e($chave) ?>"></span><?= e($nome) ?></span>
<?php endforeach; ?>
        </div>
      </div>
      <form class="d-flex flex-wrap gap-2" id="filtros-mapa">
        <div>
          <label for="filtro-tipo" class="visually-hidden">Tipo</label>
          <select class="form-select form-select-sm" id="filtro-tipo" name="categoria">
            <option value="">Todos os tipos</option>
<?php foreach ($categorias as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="filtro-situacao" class="visually-hidden">Situação</label>
          <select class="form-select form-select-sm" id="filtro-situacao" name="situacao">
            <option value="">Todas as situações</option>
<?php foreach (SITUACOES as $chave => $nome): ?>
            <option value="<?= e($chave) ?>"><?= e($nome) ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
    <div id="mapa-geral" class="ra-mapa" role="region" aria-label="Mapa com as ocorrências registradas"></div>
    <p class="small text-body-secondary mt-2" id="mapa-contagem" aria-live="polite"></p>
  </section>

  <section class="mt-5" aria-labelledby="titulo-ultimas">
    <h2 class="h4 mb-3" id="titulo-ultimas">Últimas solicitações</h2>
<?php if (!$ultimas): ?>
    <p class="text-body-secondary">Ainda não há solicitações registradas. Seja a primeira pessoa a reportar!</p>
<?php else: ?>
    <div class="row g-3">
<?php   foreach ($ultimas as $s): ?>
      <div class="col-md-6 col-xl-4">
        <a class="ra-item h-100" href="<?= e(url('public/solicitacao.php?protocolo=' . urlencode($s['protocolo']))) ?>">
          <div class="ra-item-icone"><i class="bi <?= e($s['icone']) ?>"></i></div>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between gap-2">
              <span class="ra-item-titulo"><?= e($s['categoria']) ?></span>
              <?= badge_situacao($s['situacao']) ?>
            </div>
            <div><?= e(endereco_completo($s)) ?></div>
            <div class="ra-item-meta">
              <span class="ra-protocolo"><?= e($s['protocolo']) ?></span> · <?= e(tempo_atras($s['criado_em'])) ?>
<?php     if ($s['apoios'] > 0): ?>
              · <i class="bi bi-hand-thumbs-up"></i> <?= (int) $s['apoios'] ?>
<?php     endif; ?>
            </div>
          </div>
        </a>
      </div>
<?php   endforeach; ?>
    </div>
<?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../app/layout/rodape.php'; ?>
