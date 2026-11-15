<?php
require_once __DIR__ . '/../app/iniciar.php';

if (esta_logado()) {
    redirecionar(eh_admin() ? 'public/admin/index.php' : 'public/minhas.php');
}

// Links como "Entre para apoiar" informam para onde voltar depois do login.
// Só aceita endereços internos do próprio sistema.
$destino = $_GET['voltar'] ?? '';
if (is_string($destino) && str_starts_with($destino, base_url() . '/public/') && !preg_match('/[\x00-\x1f\\\\]/', $destino)) {
    $_SESSION['voltar_para'] = $destino;
}

$erro = '';
$identificador = '';

if (eh_post()) {
    csrf_exigir();
    $identificador = post_texto('identificador');
    $senha = (string) ($_POST['senha'] ?? '');

    // Freio simples contra tentativas em sequência nesta sessão.
    $tentativas = $_SESSION['login_falhas'] ?? ['n' => 0, 'ate' => 0];
    if ($tentativas['ate'] > time()) {
        $erro = 'Muitas tentativas. Aguarde ' . ($tentativas['ate'] - time()) . ' segundos e tente de novo.';
    } elseif ($identificador === '' || $senha === '') {
        $erro = 'Informe seu e-mail ou CPF e a senha.';
    } elseif (fazer_login($identificador, $senha)) {
        unset($_SESSION['login_falhas']);
        $voltar = $_SESSION['voltar_para'] ?? '';
        unset($_SESSION['voltar_para']);
        // Só volta para endereços internos do próprio sistema.
        if (!eh_admin() && is_string($voltar) && str_starts_with($voltar, base_url() . '/public/')) {
            header('Location: ' . $voltar);
            exit;
        }
        redirecionar(eh_admin() ? 'public/admin/index.php' : 'public/minhas.php');
    } else {
        $tentativas['n']++;
        if ($tentativas['n'] >= 5) {
            $tentativas = ['n' => 0, 'ate' => time() + 30];
        }
        $_SESSION['login_falhas'] = $tentativas;
        $erro = 'E-mail/CPF ou senha incorretos.';
    }
}

$titulo = 'Entrar';
require __DIR__ . '/../app/layout/cabecalho.php';
?>
<div class="container ra-estreito py-5">
  <div class="card ra-card">
    <div class="card-body p-4">
      <h1 class="h3 mb-1">Entrar</h1>
      <p class="text-body-secondary">Acompanhe suas solicitações ou registre um novo problema.</p>

<?php if ($erro): ?>
      <div class="alert alert-danger" role="alert"><?= e($erro) ?></div>
<?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_campo() ?>
        <div class="mb-3">
          <label for="identificador" class="form-label">E-mail ou CPF</label>
          <input type="text" class="form-control" id="identificador" name="identificador"
                 value="<?= e($identificador) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
        </div>
        <div class="mb-3">
          <label for="senha" class="form-label">Senha</label>
          <input type="password" class="form-control" id="senha" name="senha" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Entrar</button>
      </form>

      <p class="mt-4 mb-0 text-center">Ainda não tem conta?
        <a href="<?= e(url('public/cadastro.php')) ?>">Criar conta</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../app/layout/rodape.php'; ?>
