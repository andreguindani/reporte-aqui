<?php
/**
 * Ponto de entrada comum: carrega configuração, funções e inicia a sessão.
 */
require_once __DIR__ . '/../config.php';

ini_set('display_errors', MODO_DEBUG ? '1' : '0');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/auth.php';

iniciar_sessao();
