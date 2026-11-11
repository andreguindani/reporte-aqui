<?php
/**
 * Funções gerais do Reporte Aqui.
 */

// ---------------------------------------------------------------------------
// Regras de negócio fixas
// ---------------------------------------------------------------------------

/** Situações na ordem em que acontecem (só avança, nunca volta). */
const SITUACOES = [
    'recebida'    => 'Recebida',
    'em_analise'  => 'Em análise',
    'em_execucao' => 'Em execução',
    'concluida'   => 'Concluída',
];

const PRIORIDADES = [
    'baixa'  => 'Baixa',
    'normal' => 'Normal',
    'alta'   => 'Alta',
];

const DISTANCIA_DUPLICADA_METROS = 50;

function situacao_nome(string $s): string
{
    return SITUACOES[$s] ?? $s;
}

function situacao_indice(string $s): int
{
    $i = array_search($s, array_keys(SITUACOES), true);
    return $i === false ? -1 : (int) $i;
}

function prioridade_nome(string $p): string
{
    return PRIORIDADES[$p] ?? $p;
}

// ---------------------------------------------------------------------------
// Saída, URLs e respostas
// ---------------------------------------------------------------------------

/** Escapa texto para HTML. Use em TODA saída. */
function e($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    static $base = null;
    if ($base === null) {
        if (BASE_URL !== '') {
            $base = rtrim(BASE_URL, '/');
        } else {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            if (preg_match('#^(.*?)/(public|api)/#', $script, $m)) {
                $base = $m[1];
            } else {
                $base = rtrim(dirname($script), '/');
            }
        }
    }
    return $base;
}

/** Monta uma URL interna: url('public/login.php'). */
function url(string $caminho = ''): string
{
    return base_url() . '/' . ltrim($caminho, '/');
}

function redirecionar(string $caminho): void
{
    header('Location: ' . url($caminho));
    exit;
}

function json_resposta(array $dados, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** JSON seguro para colocar dentro de <script>. */
function json_script($dados): string
{
    return json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// ---------------------------------------------------------------------------
// Mensagens rápidas (flash)
// ---------------------------------------------------------------------------

function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function flash_pegar(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valido(): bool
{
    $enviado = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($enviado) && $enviado !== '' && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $enviado);
}

/** Interrompe formulários comuns com token inválido. */
function csrf_exigir(): void
{
    if (!csrf_valido()) {
        flash('erro', 'Sua sessão expirou ou o formulário é inválido. Tente novamente.');
        header('Location: ' . ($_SERVER['REQUEST_URI'] ?? url('public/index.php')));
        exit;
    }
}

function api_csrf_exigir(): void
{
    if (!csrf_valido()) {
        json_resposta(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página e tente novamente.'], 419);
    }
}

function eh_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------------------------------------------------------------------------
// Texto, CPF, telefone e datas
// ---------------------------------------------------------------------------

function so_digitos(string $s): string
{
    return preg_replace('/\D+/', '', $s);
}

/** Lê um campo de texto do POST já aparado. */
function post_texto(string $campo): string
{
    $v = $_POST[$campo] ?? '';
    return is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : '';
}

/** Valida CPF pelos dois dígitos verificadores. */
function validar_cpf(string $cpf): bool
{
    $cpf = so_digitos($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }
    return true;
}

function formatar_cpf(string $cpf): string
{
    $c = so_digitos($cpf);
    return strlen($c) === 11
        ? substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9, 2)
        : $cpf;
}

function formatar_telefone(string $tel): string
{
    $t = so_digitos($tel);
    if (strlen($t) === 11) {
        return '(' . substr($t, 0, 2) . ') ' . substr($t, 2, 5) . '-' . substr($t, 7);
    }
    if (strlen($t) === 10) {
        return '(' . substr($t, 0, 2) . ') ' . substr($t, 2, 4) . '-' . substr($t, 6);
    }
    return $tel;
}

function data_br(?string $data, bool $comHora = false): string
{
    if (!$data) {
        return '—';
    }
    $ts = strtotime($data);
    return $ts ? date($comHora ? 'd/m/Y \à\s H:i' : 'd/m/Y', $ts) : '—';
}

/** "hoje", "ontem", "há 5 dias". */
function tempo_atras(string $data): string
{
    $dias = (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($data)))) / 86400);
    if ($dias <= 0) {
        return 'hoje';
    }
    if ($dias === 1) {
        return 'ontem';
    }
    return "há $dias dias";
}

function formatar_dias(?float $dias): string
{
    if ($dias === null) {
        return '—';
    }
    $n = number_format($dias, 1, ',', '');
    $n = str_replace(',0', '', $n);
    return $n . ($dias >= 2 || $dias < 1 ? ' dias' : ' dia');
}

// ---------------------------------------------------------------------------
// Solicitações
// ---------------------------------------------------------------------------

/** Aberta e com a previsão já vencida. */
function esta_atrasada(array $s): bool
{
    return $s['situacao'] !== 'concluida' && !empty($s['previsao']) && $s['previsao'] < date('Y-m-d');
}

function endereco_completo(array $s): string
{
    $txt = $s['endereco'];
    if (!empty($s['numero'])) {
        $txt .= ', ' . $s['numero'];
    }
    if (!empty($s['bairro'])) {
        $txt .= ' — ' . $s['bairro'];
    }
    return $txt;
}

/**
 * Nome de quem registrou, respeitando o sigilo:
 * só o próprio autor e a prefeitura veem o nome de quem pediu sigilo.
 */
function nome_solicitante(array $s): string
{
    $u = usuario();
    $souAutor = $u && (int) $u['id'] === (int) $s['usuario_id'];
    if (!empty($s['sigilo']) && !$souAutor && !eh_admin()) {
        return 'Morador(a) — nome em sigilo';
    }
    return (string) $s['autor_nome'];
}

function badge_situacao(string $situacao): string
{
    return '<span class="badge sit sit-' . e($situacao) . '">' . e(situacao_nome($situacao)) . '</span>';
}

function badge_prioridade(string $p): string
{
    return '<span class="badge pri pri-' . e($p) . '">' . e(prioridade_nome($p)) . '</span>';
}

const SQL_SOLICITACAO = '
    SELECT s.*, c.nome AS categoria, c.icone, c.prazo_dias, sc.nome AS subcategoria,
           st.nome AS setor, u.nome AS autor_nome,
           (SELECT COUNT(*) FROM apoios a WHERE a.solicitacao_id = s.id) AS apoios
      FROM solicitacoes s
      JOIN categorias c     ON c.id = s.categoria_id
      JOIN subcategorias sc ON sc.id = s.subcategoria_id
      JOIN setores st       ON st.id = s.setor_id
      JOIN usuarios u       ON u.id = s.usuario_id';

function buscar_solicitacao_id(int $id): ?array
{
    $st = db()->prepare(SQL_SOLICITACAO . ' WHERE s.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function buscar_solicitacao_protocolo(string $protocolo): ?array
{
    $st = db()->prepare(SQL_SOLICITACAO . ' WHERE s.protocolo = ?');
    $st->execute([normalizar_protocolo($protocolo)]);
    return $st->fetch() ?: null;
}

/** Aceita "ra 2026 12", "RA-2026-0012" etc. */
function normalizar_protocolo(string $p): string
{
    $p = strtoupper(trim($p));
    if (preg_match('/^RA\D*(\d{4})\D*(\d{1,6})$/', $p, $m)) {
        return sprintf('RA-%s-%04d', $m[1], (int) $m[2]);
    }
    return $p;
}

/**
 * Próximo protocolo do ano: RA-AAAA-0000.
 * Deve ser chamado dentro de uma transação.
 */
function gerar_protocolo(PDO $pdo): string
{
    $prefixo = 'RA-' . date('Y') . '-';
    $st = $pdo->prepare(
        'SELECT COALESCE(MAX(CAST(SUBSTRING(protocolo, 9) AS UNSIGNED)), 0)
           FROM solicitacoes WHERE protocolo LIKE ? FOR UPDATE'
    );
    $st->execute([$prefixo . '%']);
    return $prefixo . sprintf('%04d', (int) $st->fetchColumn() + 1);
}

/** Distância em metros entre dois pontos (Haversine). */
function distancia_metros(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

/**
 * Solicitação em aberto do mesmo tipo a menos de 50 m (a mais próxima), ou null.
 */
function buscar_solicitacao_proxima(int $categoriaId, float $lat, float $lng): ?array
{
    $margem = 0.001; // ~110 m: pré-filtro que usa o índice de latitude/longitude
    $st = db()->prepare(
        "SELECT s.id, s.protocolo, s.usuario_id, s.endereco, s.numero, s.bairro, s.situacao,
                s.latitude, s.longitude, s.criado_em,
                (SELECT COUNT(*) FROM apoios a WHERE a.solicitacao_id = s.id) AS apoios
           FROM solicitacoes s
          WHERE s.categoria_id = ? AND s.situacao <> 'concluida'
            AND s.latitude BETWEEN ? AND ? AND s.longitude BETWEEN ? AND ?"
    );
    $st->execute([$categoriaId, $lat - $margem, $lat + $margem, $lng - $margem, $lng + $margem]);

    $melhor = null;
    foreach ($st as $linha) {
        $d = distancia_metros($lat, $lng, (float) $linha['latitude'], (float) $linha['longitude']);
        if ($d <= DISTANCIA_DUPLICADA_METROS && ($melhor === null || $d < $melhor['distancia'])) {
            $linha['distancia'] = $d;
            $melhor = $linha;
        }
    }
    return $melhor;
}

/**
 * Registra o apoio de um cidadão. Devolve [ok, mensagem].
 */
function apoiar_solicitacao(int $solicitacaoId, array $usuario): array
{
    if (($usuario['perfil'] ?? '') !== 'cidadao') {
        return [false, 'Apenas moradores podem apoiar solicitações.'];
    }
    $s = buscar_solicitacao_id($solicitacaoId);
    if (!$s) {
        return [false, 'Solicitação não encontrada.'];
    }
    if ((int) $s['usuario_id'] === (int) $usuario['id']) {
        return [false, 'Esta solicitação é sua — não é possível apoiar a própria solicitação.'];
    }
    if ($s['situacao'] === 'concluida') {
        return [false, 'Esta solicitação já foi concluída.'];
    }
    $st = db()->prepare('INSERT IGNORE INTO apoios (solicitacao_id, usuario_id) VALUES (?, ?)');
    $st->execute([$solicitacaoId, $usuario['id']]);
    if ($st->rowCount() === 0) {
        return [true, 'Você já apoiava esta solicitação.'];
    }
    return [true, 'Apoio registrado! Quanto mais apoios, mais visível fica o problema.'];
}

function usuario_apoia(int $solicitacaoId, int $usuarioId): bool
{
    $st = db()->prepare('SELECT 1 FROM apoios WHERE solicitacao_id = ? AND usuario_id = ?');
    $st->execute([$solicitacaoId, $usuarioId]);
    return (bool) $st->fetchColumn();
}

/** Categorias com setor e prazo. */
function listar_categorias(): array
{
    return db()->query(
        'SELECT c.id, c.nome, c.icone, c.prazo_dias, c.setor_id, st.nome AS setor
           FROM categorias c JOIN setores st ON st.id = c.setor_id ORDER BY c.id'
    )->fetchAll();
}

/** Evita que planilhas interpretem um texto como fórmula. */
function csv_seguro($valor): string
{
    $v = (string) ($valor ?? '');
    if (preg_match('/^-?\d+([.,]\d+)?$/', $v)) {
        return $v; // número simples (ex.: coordenada negativa)
    }
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}
