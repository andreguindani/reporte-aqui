<?php
/**
 * Relatório em CSV das solicitações, respeitando os filtros do painel.
 * Usa ponto e vírgula e BOM UTF-8 para abrir corretamente no Excel em português.
 */
require_once __DIR__ . '/../../app/iniciar.php';
require_once __DIR__ . '/../../app/admin.php';
exigir_admin();

$filtros = admin_filtros();
$st = db()->prepare(
    "SELECT s.*, c.nome AS categoria, sc.nome AS subcategoria, st.nome AS setor, u.nome AS autor_nome,
            (SELECT COUNT(*) FROM apoios a WHERE a.solicitacao_id = s.id) AS apoios,
            (SELECT nota FROM avaliacoes av WHERE av.solicitacao_id = s.id) AS nota
       FROM solicitacoes s
       JOIN categorias c     ON c.id = s.categoria_id
       JOIN subcategorias sc ON sc.id = s.subcategoria_id
       JOIN setores st       ON st.id = s.setor_id
       JOIN usuarios u       ON u.id = s.usuario_id" . $filtros['sql'] . ADMIN_ORDEM
);
$st->execute($filtros['params']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte-aqui-' . date('Y-m-d-Hi') . '.csv"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$saida = fopen('php://output', 'w');
fwrite($saida, "\xEF\xBB\xBF");
$linha = fn(array $campos) => fputcsv($saida, array_map('csv_seguro', $campos), ';', '"', '');

$linha(['Protocolo', 'Tipo', 'Subtipo', 'Situação', 'Prioridade', 'Setor', 'Endereço', 'Número/trecho', 'Bairro',
        'Referência', 'Latitude', 'Longitude', 'Solicitante', 'Nome em sigilo', 'Registrada em', 'Previsão',
        'Concluída em', 'Atrasada', 'Apoios', 'Avaliação', 'Descrição']);

foreach ($st as $s) {
    $linha([
        $s['protocolo'], $s['categoria'], $s['subcategoria'], situacao_nome($s['situacao']),
        prioridade_nome($s['prioridade']), $s['setor'], $s['endereco'], $s['numero'], $s['bairro'], $s['referencia'],
        // Coordenadas como texto com vírgula decimal, para a planilha não confundir com fórmula.
        str_replace('.', ',', (string) $s['latitude']), str_replace('.', ',', (string) $s['longitude']),
        $s['autor_nome'], $s['sigilo'] ? 'Sim' : 'Não',
        date('d/m/Y H:i', strtotime($s['criado_em'])), date('d/m/Y', strtotime($s['previsao'])),
        $s['concluido_em'] ? date('d/m/Y H:i', strtotime($s['concluido_em'])) : '',
        esta_atrasada($s) ? 'Sim' : 'Não', $s['apoios'], $s['nota'] ?? '',
        str_replace(["\r", "\n"], ' ', $s['descricao']),
    ]);
}
fclose($saida);
