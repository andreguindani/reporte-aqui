<?php
/**
 * Funções do painel da prefeitura (filtros compartilhados pela tabela e pelo CSV).
 */

/**
 * Lê os filtros da URL e monta o WHERE.
 * Devolve ['sql' => string, 'params' => array, 'valores' => array].
 */
function admin_filtros(): array
{
    $texto = fn(string $c): string => isset($_GET[$c]) && is_string($_GET[$c]) ? trim($_GET[$c]) : '';

    $valores = [
        'q'         => mb_substr($texto('q'), 0, 80),
        'categoria' => (int) $texto('categoria'),
        'bairro'    => mb_substr($texto('bairro'), 0, 80),
        'situacao'  => $texto('situacao'),
    ];
    if ($valores['situacao'] !== 'todas' && !isset(SITUACOES[$valores['situacao']])) {
        $valores['situacao'] = 'abertas';
    }

    $onde = [];
    $params = [];
    if ($valores['q'] !== '') {
        // Busca por protocolo ou rua; % e _ digitados são tratados como texto comum.
        $like = '%' . addcslashes($valores['q'], '\\%_') . '%';
        $onde[] = '(s.protocolo LIKE ? OR s.endereco LIKE ?)';
        array_push($params, $like, $like);
    }
    if ($valores['categoria'] > 0) {
        $onde[] = 's.categoria_id = ?';
        $params[] = $valores['categoria'];
    }
    if ($valores['bairro'] !== '') {
        $onde[] = 's.bairro = ?';
        $params[] = $valores['bairro'];
    }
    if ($valores['situacao'] === 'abertas') {
        $onde[] = "s.situacao <> 'concluida'";
    } elseif ($valores['situacao'] !== 'todas') {
        $onde[] = 's.situacao = ?';
        $params[] = $valores['situacao'];
    }

    return [
        'sql'     => $onde ? ' WHERE ' . implode(' AND ', $onde) : '',
        'params'  => $params,
        'valores' => $valores,
    ];
}

/** Ordem do painel: abertas primeiro, maior prioridade, depois as mais antigas. */
const ADMIN_ORDEM = " ORDER BY (s.situacao = 'concluida'), FIELD(s.prioridade, 'alta', 'normal', 'baixa'), s.criado_em ASC";
