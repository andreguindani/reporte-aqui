<?php
require_once __DIR__ . '/../app/iniciar.php';

$protocolo = isset($_GET['protocolo']) && is_string($_GET['protocolo']) ? trim($_GET['protocolo']) : '';
$erro = '';

if ($protocolo !== '') {
    $normalizado = normalizar_protocolo($protocolo);
    if (!preg_match('/^RA-\d{4}-\d{4,6}$/', $normalizado)) {
        $erro = 'O protocolo deve ter o formato RA-AAAA-0000 (por exemplo, RA-' . date('Y') . '-0001).';
    } elseif ($s = buscar_solicitacao_protocolo($normalizado)) {
        redirecionar('public/solicitacao.php?protocolo=' . urlencode($s['protocolo']));
    } else {
        $erro = 'Não encontramos nenhuma solicitação com o protocolo ' . $normalizado . '. Confira o número e tente de novo.';
    }
}

$titulo = 'Consultar protocolo';
$pagina_ativa = 'consulta';
require __DIR__ . '/../app/layout/cabecalho.php';
?>
<div class="container ra-estreito py-5">
  <div class="card ra-card">
    <div class="card-body p-4">
      <h1 class="h3 mb-1">Consultar protocolo</h1>
      <p class="text-body-secondary">Digite o número que você recebeu ao registrar a solicitação. Não é preciso entrar na conta.</p>

      <form method="get" novalidate>
        <div class="mb-3">
          <label for="protocolo" class="form-label">Número do protocolo</label>
          <input type="text" class="form-control form-control-lg ra-protocolo<?= $erro ? ' is-invalid' : '' ?>" id="protocolo"
                 name="protocolo" value="<?= e($protocolo) ?>" placeholder="RA-<?= date('Y') ?>-0001" maxlength="20"
                 autocapitalize="characters" required autofocus>
<?php if ($erro): ?>
          <div class="invalid-feedback"><?= e($erro) ?></div>
<?php endif; ?>
        </div>
        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Consultar</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../app/layout/rodape.php'; ?>
