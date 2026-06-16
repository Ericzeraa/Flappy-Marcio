<?php
require_once __DIR__ . '/php/funcoes.php';

$ok = false;
$erro = '';

try {
    pdo();
    $ok = true;
} catch (Throwable $e) {
    $erro = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Flappy Márcio</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body class="pagina-setup">
    <main class="setup-card">
        <span class="tag">Configuração</span>
        <h1>Flappy Márcio</h1>
        <?php if ($ok): ?>
            <p>Banco pronto. Abra o jogo e, se precisar, acesse o painel com admin / 1234.</p>
            <a class="link-setup" href="index.php">Abrir jogo</a>
            <a class="link-setup secundario" href="admin.php">Abrir painel</a>
        <?php else: ?>
            <p>Não consegui preparar o banco de dados.</p>
            <pre><?= htmlspecialchars($erro) ?></pre>
            <p>Veja se o MySQL do XAMPP está ligado e confira os dados em <strong>config/database.php</strong>.</p>
        <?php endif; ?>
    </main>
</body>
</html>
