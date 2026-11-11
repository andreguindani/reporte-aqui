<?php
/**
 * Reporte Aqui — configuração.
 * Ajuste os dados abaixo de acordo com o seu ambiente (padrão do XAMPP já preenchido).
 */

// Banco de dados (MySQL / MariaDB)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'reporte_aqui');
define('DB_USER', 'root');
define('DB_PASS', '');

// Endereço base do sistema no navegador, sem barra no final.
// Deixe vazio para detectar automaticamente (ex.: /reporte-aqui).
define('BASE_URL', '');

// Centro do mapa (Videira - SC) e raio máximo aceito para uma solicitação.
define('MAPA_LAT', -27.008);
define('MAPA_LNG', -51.151);
define('MAPA_ZOOM', 14);
define('MAPA_RAIO_MAX_KM', 30);

// Serviço de geocodificação reversa e identificação exigida pela política de uso do Nominatim (OpenStreetMap).
define('NOMINATIM_URL', 'https://nominatim.openstreetmap.org/reverse');
define('NOMINATIM_USER_AGENT', 'ReporteAqui/1.0 (projeto academico UNOESC Videira)');

// Fotos
define('FOTO_MAX_BYTES', 5 * 1024 * 1024);
define('FOTO_MAX_QTD', 3);
define('FOTO_MAX_LADO', 1600);

// Exibir erros detalhados (true só em desenvolvimento).
define('MODO_DEBUG', false);

date_default_timezone_set('America/Sao_Paulo');
