<?php
/**
 * Sessão, login, logout e proteção de páginas por perfil.
 */

function iniciar_sessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('reporteaqui');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/** Dados do usuário logado (ou null). */
function usuario(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function esta_logado(): bool
{
    return usuario() !== null;
}

function eh_admin(): bool
{
    return (usuario()['perfil'] ?? '') === 'admin';
}

function eh_cidadao(): bool
{
    return (usuario()['perfil'] ?? '') === 'cidadao';
}

/**
 * Confere e-mail ou CPF + senha. Devolve true se entrou.
 */
function fazer_login(string $identificador, string $senha): bool
{
    $identificador = trim($identificador);
    if (str_contains($identificador, '@')) {
        $st = db()->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $st->execute([mb_strtolower($identificador)]);
    } else {
        $st = db()->prepare('SELECT * FROM usuarios WHERE cpf = ? LIMIT 1');
        $st->execute([so_digitos($identificador)]);
    }
    $u = $st->fetch();

    // Verifica sempre um hash, mesmo sem usuário, para não revelar pelo tempo se a conta existe.
    $hash = $u['senha_hash'] ?? '$2y$10$bJB1Ys2oVAtS7EmpKOhX6eA8QKSW3Qh0vbCyEYLXE0y699bbH1WYq';
    $ok = password_verify($senha, $hash);
    if (!$u || !$ok) {
        return false;
    }

    if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
            ->execute([password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
    }

    registrar_sessao_usuario($u);
    return true;
}

function registrar_sessao_usuario(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'     => (int) $u['id'],
        'nome'   => $u['nome'],
        'email'  => $u['email'],
        'perfil' => $u['perfil'],
    ];
    unset($_SESSION['csrf']); // novo token após autenticar
}

function fazer_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}

/** Página só para quem está logado (qualquer perfil). */
function exigir_login(): void
{
    if (!esta_logado()) {
        $_SESSION['voltar_para'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('aviso', 'Entre na sua conta para continuar.');
        redirecionar('public/login.php');
    }
}

/** Página só para cidadãos. */
function exigir_cidadao(): void
{
    exigir_login();
    if (!eh_cidadao()) {
        flash('aviso', 'Esta área é exclusiva para moradores. Você está no perfil da prefeitura.');
        redirecionar('public/admin/index.php');
    }
}

/** Página só para servidores da prefeitura. */
function exigir_admin(): void
{
    exigir_login();
    if (!eh_admin()) {
        http_response_code(403);
        flash('erro', 'Você não tem permissão para acessar o painel da prefeitura.');
        redirecionar('public/index.php');
    }
}

/** Versões para os endpoints JSON. */
function api_exigir_login(): void
{
    if (!esta_logado()) {
        json_resposta(['ok' => false, 'erro' => 'Sua sessão expirou. Entre novamente.'], 401);
    }
}

function api_exigir_admin(): void
{
    api_exigir_login();
    if (!eh_admin()) {
        json_resposta(['ok' => false, 'erro' => 'Acesso restrito à prefeitura.'], 403);
    }
}
