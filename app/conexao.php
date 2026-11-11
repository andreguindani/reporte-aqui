<?php
/**
 * Conexão única com o banco via PDO.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Mantém o relógio do MySQL igual ao do PHP (prazos e "há X dias").
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    } catch (PDOException $ex) {
        http_response_code(500);
        $detalhe = MODO_DEBUG ? htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') : '';
        exit('<h1>Não foi possível conectar ao banco de dados</h1>'
            . '<p>Verifique se o MySQL está ligado, se o arquivo <code>database.sql</code> foi importado '
            . 'e se os dados em <code>config.php</code> estão corretos.</p><p>' . $detalhe . '</p>');
    }
    return $pdo;
}
