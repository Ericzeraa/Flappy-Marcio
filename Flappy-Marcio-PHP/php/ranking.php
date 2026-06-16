<?php
session_start();
require_once __DIR__ . '/funcoes.php';
\App\Base\Cabecalhos::segurancaBasica();

$controller = new \App\Api\ApiJogo();
$controller->executar();
