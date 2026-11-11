<?php
/**
 * Cabeçalho comum. Variáveis aceitas:
 *   $titulo        título da página
 *   $pagina_ativa  item do menu em destaque
 *   $usa_mapa      true para carregar o Leaflet
 *   $layout_largo  true para usar a largura toda (painel)
 */
$titulo       = $titulo ?? 'Reporte Aqui';
$pagina_ativa = $pagina_ativa ?? '';
$usa_mapa     = $usa_mapa ?? false;
$u            = usuario();
$ativo        = fn(string $p): string => $pagina_ativa === $p ? ' active' : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($titulo) ?> · Reporte Aqui</title>
  <link rel="icon" href="<?= e(url('assets/img/pino.svg')) ?>" type="image/svg+xml">
  <link href="<?= e(url('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
<?php if ($usa_mapa): ?>
  <link href="<?= e(url('assets/vendor/leaflet/leaflet.css')) ?>" rel="stylesheet">
<?php endif; ?>
  <link href="<?= e(url('assets/css/estilo.css')) ?>" rel="stylesheet">
</head>
<body>
<a class="visually-hidden-focusable" href="#conteudo">Pular para o conteúdo</a>

<nav class="navbar navbar-expand-lg ra-nav sticky-top" data-bs-theme="dark">
  <div class="container<?= !empty($layout_largo) ? '-fluid px-lg-4' : '' ?>">
    <a class="navbar-brand" href="<?= e(url('public/index.php')) ?>">
      <img src="<?= e(url('assets/img/pino.svg')) ?>" alt="" width="26" height="26">
      <span>Reporte <strong>Aqui</strong></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
            aria-controls="menu" aria-expanded="false" aria-label="Abrir menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link<?= $ativo('inicio') ?>" href="<?= e(url('public/index.php')) ?>">Início</a></li>
        <li class="nav-item"><a class="nav-link<?= $ativo('consulta') ?>" href="<?= e(url('public/consulta.php')) ?>">Consultar protocolo</a></li>
        <li class="nav-item"><a class="nav-link<?= $ativo('como') ?>" href="<?= e(url('public/como-funciona.php')) ?>">Como funciona</a></li>
<?php if ($u && $u['perfil'] === 'cidadao'): ?>
        <li class="nav-item"><a class="nav-link<?= $ativo('minhas') ?>" href="<?= e(url('public/minhas.php')) ?>">Minhas solicitações</a></li>
<?php elseif ($u && $u['perfil'] === 'admin'): ?>
        <li class="nav-item"><a class="nav-link<?= $ativo('painel') ?>" href="<?= e(url('public/admin/index.php')) ?>">Painel da prefeitura</a></li>
<?php endif; ?>
      </ul>
      <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 mt-2 mt-lg-0">
<?php if (!$u): ?>
        <a class="btn btn-outline-light btn-sm" href="<?= e(url('public/login.php')) ?>">Entrar</a>
        <a class="btn btn-acao btn-sm" href="<?= e(url('public/cadastro.php')) ?>">Criar conta</a>
<?php else: ?>
<?php   if ($u['perfil'] === 'cidadao'): ?>
        <a class="btn btn-acao btn-sm" href="<?= e(url('public/nova.php')) ?>"><i class="bi bi-plus-lg"></i> Nova solicitação</a>
<?php   endif; ?>
        <span class="ra-nav-usuario"><i class="bi bi-person-circle"></i> <?= e(explode(' ', $u['nome'])[0]) ?></span>
        <form method="post" action="<?= e(url('public/logout.php')) ?>" class="m-0">
          <?= csrf_campo() ?>
          <button class="btn btn-outline-light btn-sm" type="submit">Sair</button>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<main id="conteudo">
<?php
$mensagens = flash_pegar();
if ($mensagens): ?>
  <div class="container mt-3">
<?php   foreach ($mensagens as $m):
          $classe = ['sucesso' => 'success', 'erro' => 'danger', 'aviso' => 'warning'][$m['tipo']] ?? 'info'; ?>
    <div class="alert alert-<?= $classe ?> alert-dismissible fade show" role="alert">
      <?= e($m['mensagem']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php   endforeach; ?>
  </div>
<?php endif; ?>
