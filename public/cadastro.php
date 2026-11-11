<?php
require_once __DIR__ . '/../app/iniciar.php';

if (esta_logado()) {
    redirecionar(eh_admin() ? 'public/admin/index.php' : 'public/minhas.php');
}

$erros = [];
$v = ['nome' => '', 'cpf' => '', 'email' => '', 'telefone' => ''];

if (eh_post()) {
    csrf_exigir();
    $v['nome']     = preg_replace('/\s+/u', ' ', post_texto('nome'));
    $v['cpf']      = post_texto('cpf');
    $v['email']    = mb_strtolower(post_texto('email'));
    $v['telefone'] = post_texto('telefone');
    $senha         = (string) ($_POST['senha'] ?? '');
    $confirma      = (string) ($_POST['senha_confirma'] ?? '');
    $cpf           = so_digitos($v['cpf']);
    $telefone      = so_digitos($v['telefone']);

    if (mb_strlen($v['nome']) < 5 || !str_contains($v['nome'], ' ') || mb_strlen($v['nome']) > 120) {
        $erros['nome'] = 'Informe seu nome completo (nome e sobrenome).';
    }
    if (!validar_cpf($cpf)) {
        $erros['cpf'] = 'CPF inválido. Confira os números digitados.';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 160) {
        $erros['email'] = 'Informe um e-mail válido.';
    }
    if (strlen($telefone) < 10 || strlen($telefone) > 11) {
        $erros['telefone'] = 'Informe o telefone com DDD (10 ou 11 dígitos).';
    }
    if (strlen($senha) < 8) {
        $erros['senha'] = 'A senha precisa ter pelo menos 8 caracteres.';
    } elseif (strlen($senha) > 200) {
        $erros['senha'] = 'A senha é longa demais.';
    } elseif ($senha !== $confirma) {
        $erros['senha_confirma'] = 'As senhas não são iguais.';
    }

    if (!$erros) {
        $st = db()->prepare('SELECT cpf, email FROM usuarios WHERE cpf = ? OR email = ?');
        $st->execute([$cpf, $v['email']]);
        foreach ($st as $existente) {
            if ($existente['cpf'] === $cpf) {
                $erros['cpf'] = 'Já existe uma conta com este CPF. Tente entrar.';
            }
            if ($existente['email'] === $v['email']) {
                $erros['email'] = 'Já existe uma conta com este e-mail. Tente entrar.';
            }
        }
    }

    if (!$erros) {
        try {
            $st = db()->prepare(
                "INSERT INTO usuarios (nome, cpf, email, telefone, senha_hash, perfil)
                 VALUES (?, ?, ?, ?, ?, 'cidadao')"
            );
            $st->execute([$v['nome'], $cpf, $v['email'], $telefone, password_hash($senha, PASSWORD_DEFAULT)]);
            registrar_sessao_usuario([
                'id' => db()->lastInsertId(), 'nome' => $v['nome'], 'email' => $v['email'], 'perfil' => 'cidadao',
            ]);
            flash('sucesso', 'Conta criada! Agora você já pode registrar sua primeira solicitação.');
            redirecionar('public/minhas.php');
        } catch (PDOException $ex) {
            // Corrida rara: outra pessoa cadastrou o mesmo CPF/e-mail no mesmo instante.
            $erros['geral'] = $ex->getCode() === '23000'
                ? 'Já existe uma conta com este CPF ou e-mail.'
                : 'Não foi possível criar a conta agora. Tente novamente.';
        }
    }
}

$invalido = fn(string $c): string => isset($erros[$c]) ? ' is-invalid' : '';
$msg = fn(string $c): string => isset($erros[$c]) ? '<div class="invalid-feedback">' . e($erros[$c]) . '</div>' : '';

$titulo = 'Criar conta';
$scripts = ['mascaras.js'];
require __DIR__ . '/../app/layout/cabecalho.php';
?>
<div class="container ra-estreito py-5">
  <div class="card ra-card">
    <div class="card-body p-4">
      <h1 class="h3 mb-1">Criar conta</h1>
      <p class="text-body-secondary">Leva menos de um minuto. Seus dados só são usados para o atendimento.</p>

<?php if ($erros): ?>
      <div class="alert alert-danger" role="alert">
        <?= e($erros['geral'] ?? 'Confira os campos destacados abaixo.') ?>
      </div>
<?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_campo() ?>
        <div class="mb-3">
          <label for="nome" class="form-label">Nome completo</label>
          <input type="text" class="form-control<?= $invalido('nome') ?>" id="nome" name="nome"
                 value="<?= e($v['nome']) ?>" maxlength="120" autocomplete="name" required>
          <?= $msg('nome') ?>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label for="cpf" class="form-label">CPF</label>
            <input type="text" class="form-control<?= $invalido('cpf') ?>" id="cpf" name="cpf" data-mascara="cpf"
                   value="<?= e($v['cpf']) ?>" inputmode="numeric" placeholder="000.000.000-00" maxlength="14" required>
            <?= $msg('cpf') ?>
          </div>
          <div class="col-sm-6">
            <label for="telefone" class="form-label">Telefone</label>
            <input type="tel" class="form-control<?= $invalido('telefone') ?>" id="telefone" name="telefone" data-mascara="telefone"
                   value="<?= e($v['telefone']) ?>" inputmode="numeric" placeholder="(49) 99999-0000" maxlength="15" autocomplete="tel" required>
            <?= $msg('telefone') ?>
          </div>
        </div>
        <div class="mb-3">
          <label for="email" class="form-label">E-mail</label>
          <input type="email" class="form-control<?= $invalido('email') ?>" id="email" name="email"
                 value="<?= e($v['email']) ?>" maxlength="160" autocomplete="email" required>
          <?= $msg('email') ?>
        </div>
        <div class="row g-3 mb-4">
          <div class="col-sm-6">
            <label for="senha" class="form-label">Senha</label>
            <input type="password" class="form-control<?= $invalido('senha') ?>" id="senha" name="senha"
                   minlength="8" autocomplete="new-password" required>
            <?= $msg('senha') ?: '<div class="form-text">Mínimo de 8 caracteres.</div>' ?>
          </div>
          <div class="col-sm-6">
            <label for="senha_confirma" class="form-label">Repita a senha</label>
            <input type="password" class="form-control<?= $invalido('senha_confirma') ?>" id="senha_confirma"
                   name="senha_confirma" minlength="8" autocomplete="new-password" required>
            <?= $msg('senha_confirma') ?>
          </div>
        </div>
        <button class="btn btn-primary w-100" type="submit">Criar conta</button>
      </form>

      <p class="mt-4 mb-0 text-center">Já tem conta? <a href="<?= e(url('public/login.php')) ?>">Entrar</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../app/layout/rodape.php'; ?>
