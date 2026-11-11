<?php
require_once __DIR__ . '/../../app/iniciar.php';
require_once __DIR__ . '/../../app/admin.php';
exigir_admin();

$pdo = db();
$filtros = admin_filtros();
$f = $filtros['valores'];

$indicadores = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM solicitacoes WHERE situacao <> 'concluida') AS abertas,
        (SELECT COUNT(*) FROM solicitacoes WHERE situacao = 'recebida') AS triagem,
        (SELECT COUNT(*) FROM solicitacoes WHERE situacao <> 'concluida' AND previsao < CURDATE()) AS atrasadas,
        (SELECT AVG(TIMESTAMPDIFF(HOUR, criado_em, concluido_em)) / 24
           FROM solicitacoes WHERE situacao = 'concluida' AND concluido_em IS NOT NULL) AS media_dias"
)->fetch();

$st = $pdo->prepare(
    "SELECT s.id, s.protocolo, s.situacao, s.prioridade, s.endereco, s.numero, s.bairro, s.previsao, s.criado_em,
            c.nome AS categoria, c.icone, st.nome AS setor,
            (SELECT COUNT(*) FROM apoios a WHERE a.solicitacao_id = s.id) AS apoios
       FROM solicitacoes s
       JOIN categorias c ON c.id = s.categoria_id
       JOIN setores st   ON st.id = s.setor_id" . $filtros['sql'] . ADMIN_ORDEM . ' LIMIT 500'
);
$st->execute($filtros['params']);
$linhas = $st->fetchAll();

$categorias = listar_categorias();
$setores = $pdo->query('SELECT id, nome FROM setores ORDER BY nome')->fetchAll();
$bairros = $pdo->query('SELECT DISTINCT bairro FROM solicitacoes ORDER BY bairro')->fetchAll(PDO::FETCH_COLUMN);

$porBairro = $pdo->query(
    "SELECT bairro AS nome, COUNT(*) AS total FROM solicitacoes WHERE situacao <> 'concluida'
      GROUP BY bairro ORDER BY total DESC, bairro LIMIT 8"
)->fetchAll();
$porTipo = $pdo->query(
    "SELECT c.nome, COUNT(s.id) AS total
       FROM categorias c LEFT JOIN solicitacoes s ON s.categoria_id = c.id AND s.situacao <> 'concluida'
      GROUP BY c.id, c.nome ORDER BY total DESC, c.nome"
)->fetchAll();

/** Barras horizontais simples, em HTML/CSS. */
function grafico_barras(array $dados): string
{
    $max = max(1, ...array_map(fn($d) => (int) $d['total'], $dados ?: [['total' => 0]]));
    $html = '<div class="ra-barras">';
    foreach ($dados as $d) {
        $pct = round((int) $d['total'] / $max * 100);
        $html .= '<div class="ra-barra"><span class="nome" title="' . e($d['nome']) . '">' . e($d['nome']) . '</span>'
               . '<span class="trilho"><span class="valor d-block" style="width:' . $pct . '%'
               . ((int) $d['total'] === 0 ? ';visibility:hidden' : '') . '"></span></span>'
               . '<span class="n">' . (int) $d['total'] . '</span></div>';
    }
    return $html . '</div>';
}

$consulta = http_build_query(array_filter($f, fn($x) => $x !== '' && $x !== 0));
$temFiltro = $f['q'] !== '' || $f['categoria'] || $f['bairro'] !== '' || $f['situacao'] !== 'abertas';

$titulo = 'Painel da prefeitura';
$pagina_ativa = 'painel';
$layout_largo = true;
$scripts = ['admin.js'];
$dados_js = [
    'situacoes'   => SITUACOES,
    'prioridades' => PRIORIDADES,
    'setores'     => array_map(fn($x) => ['id' => (int) $x['id'], 'nome' => $x['nome']], $setores),
    'abrir'       => filter_input(INPUT_GET, 'abrir', FILTER_VALIDATE_INT) ?: null,
];
require __DIR__ . '/../../app/layout/cabecalho.php';
?>
<div class="container-fluid px-lg-4 py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Painel da prefeitura</h1>
    <a class="btn btn-outline-primary" href="<?= e(url('public/admin/exportar.php' . ($consulta ? '?' . $consulta : ''))) ?>">
      <i class="bi bi-download"></i> Exportar CSV</a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="ra-numero">
      <div class="valor"><?= (int) $indicadores['abertas'] ?></div><div class="rotulo">em aberto</div></div></div>
    <div class="col-6 col-lg-3"><div class="ra-numero">
      <div class="valor"><?= (int) $indicadores['triagem'] ?></div><div class="rotulo">aguardando triagem</div></div></div>
    <div class="col-6 col-lg-3"><div class="ra-numero<?= $indicadores['atrasadas'] > 0 ? ' alerta' : '' ?>">
      <div class="valor"><?= (int) $indicadores['atrasadas'] ?></div><div class="rotulo">fora do prazo</div></div></div>
    <div class="col-6 col-lg-3"><div class="ra-numero">
      <div class="valor"><?= e(formatar_dias($indicadores['media_dias'] !== null ? (float) $indicadores['media_dias'] : null)) ?></div>
      <div class="rotulo">tempo médio de conclusão</div></div></div>
  </div>

  <div class="row g-4">
    <div class="col-xxl-9">
      <form class="card ra-card mb-3" method="get">
        <div class="card-body row g-2 align-items-end">
          <div class="col-md-4">
            <label for="q" class="form-label small mb-1">Buscar</label>
            <input type="search" class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Protocolo ou rua" maxlength="80">
          </div>
          <div class="col-6 col-md-2">
            <label for="categoria" class="form-label small mb-1">Tipo</label>
            <select class="form-select" id="categoria" name="categoria">
              <option value="">Todos</option>
<?php foreach ($categorias as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= $f['categoria'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['nome']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label for="bairro" class="form-label small mb-1">Bairro</label>
            <select class="form-select" id="bairro" name="bairro">
              <option value="">Todos</option>
<?php foreach ($bairros as $b): ?>
              <option value="<?= e($b) ?>"<?= $f['bairro'] === $b ? ' selected' : '' ?>><?= e($b) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label for="situacao" class="form-label small mb-1">Situação</label>
            <select class="form-select" id="situacao" name="situacao">
              <option value="abertas"<?= $f['situacao'] === 'abertas' ? ' selected' : '' ?>>Em aberto</option>
<?php foreach (SITUACOES as $chave => $nome): ?>
              <option value="<?= e($chave) ?>"<?= $f['situacao'] === $chave ? ' selected' : '' ?>><?= e($nome) ?></option>
<?php endforeach; ?>
              <option value="todas"<?= $f['situacao'] === 'todas' ? ' selected' : '' ?>>Todas</option>
            </select>
          </div>
          <div class="col-6 col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit">Filtrar</button>
<?php if ($temFiltro): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('public/admin/index.php')) ?>" title="Limpar filtros" aria-label="Limpar filtros"><i class="bi bi-x-lg"></i></a>
<?php endif; ?>
          </div>
        </div>
      </form>

      <div class="card ra-card overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover mb-0 ra-tabela" id="tabela">
            <caption class="px-3 small">
              <?= count($linhas) ?> <?= count($linhas) === 1 ? 'solicitação' : 'solicitações' ?> ·
              ordenadas por prioridade e antiguidade · clique em uma linha para atender
            </caption>
            <thead>
              <tr>
                <th scope="col">Protocolo</th><th scope="col">Tipo</th><th scope="col" class="ra-col-local">Local</th>
                <th scope="col">Situação</th><th scope="col">Prioridade</th><th scope="col">Setor</th>
                <th scope="col">Registrada</th><th scope="col">Previsão</th>
                <th scope="col" class="text-end">Apoios</th>
              </tr>
            </thead>
            <tbody>
<?php if (!$linhas): ?>
              <tr class="pe-none"><td colspan="9" class="text-center text-body-secondary py-5">Nenhuma solicitação encontrada com esses filtros.</td></tr>
<?php endif; ?>
<?php foreach ($linhas as $s): ?>
              <tr data-id="<?= (int) $s['id'] ?>" tabindex="0" role="button" aria-label="Atender <?= e($s['protocolo']) ?>">
                <td class="ra-protocolo text-nowrap"><?= e($s['protocolo']) ?></td>
                <td class="text-nowrap"><i class="bi <?= e($s['icone']) ?> text-primary"></i> <?= e($s['categoria']) ?></td>
                <td><?= e($s['endereco'] . ($s['numero'] ? ', ' . $s['numero'] : '')) ?><div class="ra-item-meta"><?= e($s['bairro']) ?></div></td>
                <td><?= badge_situacao($s['situacao']) ?></td>
                <td><?= badge_prioridade($s['prioridade']) ?></td>
                <td><?= e($s['setor']) ?></td>
                <td class="text-nowrap"><?= e(data_br($s['criado_em'])) ?><div class="ra-item-meta"><?= e(tempo_atras($s['criado_em'])) ?></div></td>
                <td class="text-nowrap">
<?php   if ($s['situacao'] === 'concluida'): ?>
                  <span class="text-body-secondary">—</span>
<?php   else: ?>
                  <?= e(data_br($s['previsao'])) ?>
<?php     if (esta_atrasada($s)): ?>
                  <div><span class="badge atrasada">Atrasada</span></div>
<?php     endif; ?>
<?php   endif; ?>
                </td>
                <td class="text-end"><?= (int) $s['apoios'] ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-xxl-3">
      <div class="row g-3">
        <div class="col-md-6 col-xxl-12">
          <div class="card ra-card h-100"><div class="card-body">
            <h2 class="h6 mb-3">Em aberto por bairro</h2>
            <?= $porBairro ? grafico_barras($porBairro) : '<p class="text-body-secondary small mb-0">Nenhuma ocorrência em aberto.</p>' ?>
          </div></div>
        </div>
        <div class="col-md-6 col-xxl-12">
          <div class="card ra-card h-100"><div class="card-body">
            <h2 class="h6 mb-3">Em aberto por tipo</h2>
            <?= grafico_barras($porTipo) ?>
          </div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Painel lateral de atendimento -->
<div class="offcanvas offcanvas-end ra-lateral" tabindex="-1" id="lateral" aria-labelledby="lateral-titulo">
  <div class="offcanvas-header border-bottom">
    <h2 class="offcanvas-title h5" id="lateral-titulo">Solicitação</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
  </div>
  <div class="offcanvas-body" id="lateral-corpo"></div>
</div>
<?php require __DIR__ . '/../../app/layout/rodape.php'; ?>
