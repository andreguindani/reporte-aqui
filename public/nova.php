<?php
/**
 * Nova solicitação em 4 etapas: tipo → local → detalhes → revisão.
 * As etapas são controladas por JavaScript; toda a validação é refeita aqui no envio.
 */
require_once __DIR__ . '/../app/iniciar.php';
require_once __DIR__ . '/../app/upload.php';
exigir_cidadao();

$u = usuario();
$categorias = listar_categorias();
$subs = db()->query('SELECT id, categoria_id, nome FROM subcategorias ORDER BY id')->fetchAll();

$porId = [];
foreach ($categorias as $c) {
    $porId[(int) $c['id']] = $c + ['subcategorias' => []];
}
foreach ($subs as $sc) {
    $porId[(int) $sc['categoria_id']]['subcategorias'][] = ['id' => (int) $sc['id'], 'nome' => $sc['nome']];
}

$erros = [];           // campo => mensagem
$etapaInicial = 1;
$v = ['categoria_id' => 0, 'subcategoria_id' => 0, 'latitude' => '', 'longitude' => '', 'endereco' => '',
      'numero' => '', 'bairro' => '', 'referencia' => '', 'descricao' => '', 'sigilo' => 0];

if (eh_post()) {
    // Se o envio passa do post_max_size do PHP, $_POST chega vazio.
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('erro', 'O envio ficou grande demais. Use fotos de até 5 MB cada (no máximo 3) e tente de novo.');
        redirecionar('public/nova.php');
    }
    csrf_exigir();

    $v['categoria_id']    = (int) ($_POST['categoria_id'] ?? 0);
    $v['subcategoria_id'] = (int) ($_POST['subcategoria_id'] ?? 0);
    $v['latitude']        = post_texto('latitude');
    $v['longitude']       = post_texto('longitude');
    $v['endereco']        = post_texto('endereco');
    $v['numero']          = post_texto('numero');
    $v['bairro']          = post_texto('bairro');
    $v['referencia']      = post_texto('referencia');
    $v['descricao']       = post_texto('descricao');
    $v['sigilo']          = empty($_POST['sigilo']) ? 0 : 1;

    // Etapa 1
    $cat = $porId[$v['categoria_id']] ?? null;
    if (!$cat) {
        $erros['categoria_id'] = 'Escolha o tipo de problema.';
    } elseif (!in_array($v['subcategoria_id'], array_column($cat['subcategorias'], 'id'), true)) {
        $erros['subcategoria_id'] = 'Escolha o que melhor descreve o problema.';
    }

    // Etapa 2
    $lat = filter_var($v['latitude'], FILTER_VALIDATE_FLOAT);
    $lng = filter_var($v['longitude'], FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false) {
        $erros['local'] = 'Marque o local do problema clicando no mapa.';
    } elseif (distancia_metros($lat, $lng, MAPA_LAT, MAPA_LNG) > MAPA_RAIO_MAX_KM * 1000) {
        $erros['local'] = 'O ponto marcado fica fora da área de Videira. Marque um local dentro do município.';
    }
    if (mb_strlen($v['endereco']) < 3) {
        $erros['endereco'] = 'Informe o nome da rua ou avenida.';
    } elseif (mb_strlen($v['endereco']) > 160) {
        $erros['endereco'] = 'O endereço pode ter no máximo 160 caracteres.';
    }
    if (mb_strlen($v['bairro']) < 2) {
        $erros['bairro'] = 'Informe o bairro.';
    } elseif (mb_strlen($v['bairro']) > 80) {
        $erros['bairro'] = 'O bairro pode ter no máximo 80 caracteres.';
    }
    if (mb_strlen($v['numero']) > 60) {
        $erros['numero'] = 'Use no máximo 60 caracteres.';
    }
    if (mb_strlen($v['referencia']) > 160) {
        $erros['referencia'] = 'Use no máximo 160 caracteres.';
    }

    // Etapa 3
    $tamanho = mb_strlen($v['descricao']);
    if ($tamanho < 10) {
        $erros['descricao'] = 'Descreva o problema com pelo menos 10 caracteres.';
    } elseif ($tamanho > 500) {
        $erros['descricao'] = 'A descrição pode ter no máximo 500 caracteres (você usou ' . $tamanho . ').';
    }
    $errosFotos = [];
    $fotosValidas = validar_fotos($_FILES['fotos'] ?? null, $errosFotos);
    if ($errosFotos) {
        $erros['fotos'] = implode(' ', $errosFotos);
    }

    if (!$erros) {
        $salvas = [];
        $pdo = db();
        try {
            foreach ($fotosValidas as $f) {
                $salvas[] = salvar_foto($f);
            }

            $protocolo = null;
            for ($tentativa = 1; $tentativa <= 3 && $protocolo === null; $tentativa++) {
                try {
                    $pdo->beginTransaction();
                    $novo = gerar_protocolo($pdo);
                    $pdo->prepare(
                        "INSERT INTO solicitacoes
                            (protocolo, usuario_id, categoria_id, subcategoria_id, setor_id, descricao, endereco, numero,
                             bairro, referencia, latitude, longitude, sigilo, previsao)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY))"
                    )->execute([
                        $novo, $u['id'], $cat['id'], $v['subcategoria_id'], $cat['setor_id'], $v['descricao'],
                        $v['endereco'], $v['numero'] !== '' ? $v['numero'] : null, $v['bairro'],
                        $v['referencia'] !== '' ? $v['referencia'] : null,
                        round($lat, 7), round($lng, 7), $v['sigilo'], (int) $cat['prazo_dias'],
                    ]);
                    $id = (int) $pdo->lastInsertId();

                    $stFoto = $pdo->prepare('INSERT INTO fotos (solicitacao_id, arquivo) VALUES (?, ?)');
                    foreach ($salvas as $arquivo) {
                        $stFoto->execute([$id, $arquivo]);
                    }
                    $pdo->prepare(
                        "INSERT INTO historico (solicitacao_id, situacao, mensagem, autor_id)
                         VALUES (?, 'recebida', 'Solicitação registrada pelo morador.', ?)"
                    )->execute([$id, $u['id']]);
                    $pdo->commit();
                    $protocolo = $novo;
                } catch (PDOException $ex) {
                    $pdo->rollBack();
                    // 23000 = protocolo duplicado por envio simultâneo: gera outro e tenta de novo.
                    if ($ex->getCode() !== '23000' || $tentativa === 3) {
                        throw $ex;
                    }
                }
            }
            redirecionar('public/solicitacao.php?protocolo=' . urlencode($protocolo) . '&nova=1');
        } catch (Throwable $ex) {
            apagar_fotos($salvas);
            error_log('Reporte Aqui: ' . $ex->getMessage());
            $erros['geral'] = $ex instanceof RuntimeException && !($ex instanceof PDOException)
                ? $ex->getMessage()
                : 'Não foi possível registrar a solicitação agora. Tente novamente em instantes.';
        }
    }

    if ($erros) {
        $porEtapa = [1 => ['categoria_id', 'subcategoria_id'],
                     2 => ['local', 'endereco', 'bairro', 'numero', 'referencia'],
                     3 => ['descricao', 'fotos']];
        $etapaInicial = 4;
        foreach ($porEtapa as $n => $campos) {
            if (array_intersect($campos, array_keys($erros))) {
                $etapaInicial = $n;
                break;
            }
        }
    }
}

$invalido = fn(string $c): string => isset($erros[$c]) ? ' is-invalid' : '';
$msg = fn(string $c): string => '<div class="text-danger small mt-1" id="erro-' . $c . '" data-erro="' . $c . '" aria-live="polite">'
    . e($erros[$c] ?? '') . '</div>';
$fotoMaxMb = (int) round(FOTO_MAX_BYTES / 1048576);

$titulo = 'Nova solicitação';
$usa_mapa = true;
$scripts = ['nova.js'];
$dados_js = [
    'categorias'   => array_values(array_map(fn($c) => [
        'id' => (int) $c['id'], 'nome' => $c['nome'], 'setor' => $c['setor'],
        'prazo' => (int) $c['prazo_dias'], 'subcategorias' => $c['subcategorias'],
    ], $porId)),
    'etapaInicial' => $etapaInicial,
    'subSelecionada' => $v['subcategoria_id'],
    'limites' => ['fotos' => FOTO_MAX_QTD, 'bytes' => FOTO_MAX_BYTES, 'raioKm' => MAPA_RAIO_MAX_KM],
];
require __DIR__ . '/../app/layout/cabecalho.php';
?>
<div class="container ra-medio py-4">
  <h1 class="h3 mb-3">Nova solicitação</h1>

  <ol class="ra-etapas" id="etapas" aria-label="Etapas do registro">
    <li data-indicador="1"><span>Etapa 1</span>Tipo do problema</li>
    <li data-indicador="2"><span>Etapa 2</span>Local</li>
    <li data-indicador="3"><span>Etapa 3</span>Detalhes</li>
    <li data-indicador="4"><span>Etapa 4</span>Revisão</li>
  </ol>

<?php if ($erros): ?>
  <div class="alert alert-danger" role="alert">
    <strong>Não foi possível enviar.</strong>
    <?= e($erros['geral'] ?? 'Corrija os pontos abaixo e envie novamente.') ?>
<?php   if (count($erros) > (isset($erros['geral']) ? 1 : 0)): ?>
    <ul class="mb-0 mt-1">
<?php     foreach ($erros as $campo => $texto): if ($campo === 'geral') { continue; } ?>
      <li><?= e($texto) ?></li>
<?php     endforeach; ?>
    </ul>
<?php   endif; ?>
<?php   if (!empty($_FILES['fotos']['name'][0])): ?>
    <div class="mt-2 small">Por segurança, as fotos precisam ser selecionadas de novo.</div>
<?php   endif; ?>
  </div>
<?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="form-nova" novalidate>
    <?= csrf_campo() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) FOTO_MAX_BYTES ?>">

    <!-- ============================== ETAPA 1 ============================== -->
    <section class="card ra-card etapa" data-etapa="1">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Qual é o problema?</h2>
        <div class="ra-tipos" role="radiogroup" aria-label="Tipo de problema">
<?php foreach ($porId as $c): ?>
          <div class="ra-tipo">
            <input type="radio" name="categoria_id" id="cat-<?= (int) $c['id'] ?>" value="<?= (int) $c['id'] ?>"
                   <?= $v['categoria_id'] === (int) $c['id'] ? 'checked' : '' ?>>
            <label for="cat-<?= (int) $c['id'] ?>">
              <i class="bi <?= e($c['icone']) ?>" aria-hidden="true"></i>
              <?= e($c['nome']) ?>
              <small>prazo de <?= (int) $c['prazo_dias'] ?> dias</small>
            </label>
          </div>
<?php endforeach; ?>
        </div>
        <div class="text-danger small mt-2" id="erro-categoria_id" data-erro="categoria_id" aria-live="polite"><?= e($erros['categoria_id'] ?? '') ?></div>

        <div class="mt-4" id="bloco-sub" hidden>
          <label for="subcategoria_id" class="form-label">O que melhor descreve?</label>
          <select class="form-select<?= $invalido('subcategoria_id') ?>" id="subcategoria_id" name="subcategoria_id"></select>
          <?= $msg('subcategoria_id') ?>
        </div>
      </div>
    </section>

    <!-- ============================== ETAPA 2 ============================== -->
    <section class="card ra-card etapa" data-etapa="2" hidden>
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Onde fica?</h2>
        <p class="text-body-secondary">Clique no mapa para marcar o local exato. Você pode arrastar o pino para ajustar.
          Pelo teclado, mova o mapa com as setas e use “Marcar o centro do mapa”.</p>

        <div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
          <button type="button" class="btn btn-sm btn-outline-primary" id="btn-centro"><i class="bi bi-pin-map" aria-hidden="true"></i> Marcar o centro do mapa</button>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btn-gps"><i class="bi bi-crosshair" aria-hidden="true"></i> Usar minha localização</button>
        </div>
        <div id="mapa-local" class="ra-mapa ra-mapa-medio" role="region" aria-label="Mapa para marcar o local do problema"></div>
        <input type="hidden" name="latitude" id="latitude" value="<?= e($v['latitude']) ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?= e($v['longitude']) ?>">
        <div class="small mt-2" id="geo-status" aria-live="polite"></div>
        <div class="text-danger small" id="erro-local" data-erro="local" aria-live="polite"><?= e($erros['local'] ?? '') ?></div>

        <div class="alert alert-warning mt-3" id="aviso-proxima" role="alert" hidden></div>

        <div class="row g-3 mt-1">
          <div class="col-md-8">
            <label for="endereco" class="form-label">Rua / avenida</label>
            <input type="text" class="form-control<?= $invalido('endereco') ?>" id="endereco" name="endereco"
                   value="<?= e($v['endereco']) ?>" maxlength="160" placeholder="Preenchido ao clicar no mapa">
            <?= $msg('endereco') ?>
          </div>
          <div class="col-md-4">
            <label for="numero" class="form-label">Número ou trecho <span class="text-body-secondary fw-normal">(opcional)</span></label>
            <input type="text" class="form-control<?= $invalido('numero') ?>" id="numero" name="numero"
                   value="<?= e($v['numero']) ?>" maxlength="60" placeholder="Ex.: 450 ou entre nº 100 e 200">
            <?= $msg('numero') ?>
          </div>
          <div class="col-md-5">
            <label for="bairro" class="form-label">Bairro</label>
            <input type="text" class="form-control<?= $invalido('bairro') ?>" id="bairro" name="bairro"
                   value="<?= e($v['bairro']) ?>" maxlength="80">
            <?= $msg('bairro') ?>
          </div>
          <div class="col-md-7">
            <label for="referencia" class="form-label">Ponto de referência <span class="text-body-secondary fw-normal">(opcional)</span></label>
            <input type="text" class="form-control<?= $invalido('referencia') ?>" id="referencia" name="referencia"
                   value="<?= e($v['referencia']) ?>" maxlength="160" placeholder="Ex.: em frente à padaria">
            <?= $msg('referencia') ?>
          </div>
        </div>
      </div>
    </section>

    <!-- ============================== ETAPA 3 ============================== -->
    <section class="card ra-card etapa" data-etapa="3" hidden>
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Conte os detalhes</h2>

        <div class="mb-4">
          <label for="descricao" class="form-label">Descrição</label>
          <textarea class="form-control<?= $invalido('descricao') ?>" id="descricao" name="descricao" rows="4"
                    maxlength="500" placeholder="O que está acontecendo? Há quanto tempo? Oferece algum risco?"><?= e($v['descricao']) ?></textarea>
          <div class="d-flex justify-content-between">
            <?= $msg('descricao') ?>
            <div class="form-text ms-auto"><span id="contador">0</span>/500 (mínimo 10)</div>
          </div>
        </div>

        <div class="mb-4">
          <label for="fotos" class="form-label">Fotos <span class="text-body-secondary fw-normal">(opcional, até <?= (int) FOTO_MAX_QTD ?>)</span></label>
          <input type="file" class="form-control<?= $invalido('fotos') ?>" id="fotos" name="fotos[]"
                 accept="image/jpeg,image/png" multiple>
          <?= $msg('fotos') ?>
          <div class="form-text">JPG ou PNG, no máximo <?= $fotoMaxMb ?>&nbsp;MB cada.</div>
          <div class="ra-previas mt-2" id="previas"></div>
        </div>

        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="sigilo" name="sigilo" value="1" <?= $v['sigilo'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="sigilo">
            <strong>Manter meu nome em sigilo</strong><br>
            <span class="text-body-secondary small">Seu nome ficará visível apenas para a equipe da prefeitura.</span>
          </label>
        </div>
      </div>
    </section>

    <!-- ============================== ETAPA 4 ============================== -->
    <section class="card ra-card etapa" data-etapa="4" hidden>
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Confira antes de enviar</h2>
        <dl class="ra-resumo row mb-0" id="resumo"></dl>
        <div class="alert alert-info mb-0 mt-2" id="resumo-prazo"></div>
      </div>
    </section>

    <div class="d-flex justify-content-between mt-3">
      <button type="button" class="btn btn-outline-primary" id="btn-voltar" hidden><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar</button>
      <a class="btn btn-link" id="btn-cancelar" href="<?= e(url('public/minhas.php')) ?>">Cancelar</a>
      <button type="button" class="btn btn-primary ms-auto" id="btn-avancar">Continuar <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
      <button type="submit" class="btn btn-acao ms-auto" id="btn-enviar" hidden><i class="bi bi-send" aria-hidden="true"></i> Enviar solicitação</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../app/layout/rodape.php'; ?>
