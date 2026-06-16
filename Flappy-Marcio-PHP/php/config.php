<?php

$appConfig = require dirname(__DIR__) . '/config/app.php';
$dbConfig = require dirname(__DIR__) . '/config/database.php';

date_default_timezone_set($appConfig['timezone'] ?? 'America/Sao_Paulo');

define('APP_NAME', $appConfig['nome'] ?? 'Flappy Márcio');
define('APP_VERSION', $appConfig['versao'] ?? '1.0');
define('ADMIN_PADRAO_USUARIO', $appConfig['admin_padrao']['usuario'] ?? 'admin');
define('ADMIN_PADRAO_SENHA', $appConfig['admin_padrao']['senha'] ?? '1234');

define('DB_HOST', $dbConfig['host'] ?? 'localhost');
define('DB_NAME', $dbConfig['database'] ?? 'flappy_marcio');
define('DB_USER', $dbConfig['user'] ?? 'root');
define('DB_PASS', $dbConfig['password'] ?? '');
define('DB_CHARSET', $dbConfig['charset'] ?? 'utf8mb4');
