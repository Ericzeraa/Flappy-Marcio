<?php
session_start();
require_once __DIR__ . '/php/funcoes.php';

$mensagem = '';
$erro = '';

if (isset($_GET['sair'])) {
    unset($_SESSION['admin_logado']);
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'login_admin') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($usuario === 'admin' && $senha === '1234') {
        $_SESSION['admin_logado'] = true;
        header('Location: admin.php');
        exit;
    }

    $erro = 'Usuário ou senha incorretos.';
}

if (empty($_SESSION['admin_logado'])):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar no painel</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body class="admin-body login-admin-body">
    <div class="cenario-fundo"><span class="lua"></span><span class="planeta planeta-1"></span><span class="nuvem nuvem-1"></span><span class="brilho brilho-1"></span></div>
    <main class="login-admin-page">
        <form class="login-admin-card" method="post">
            <input type="hidden" name="acao" value="login_admin">
            <span class="tag">Acesso restrito</span>
            <h1>Painel Flappy Márcio</h1>
            <p>Usuário <strong>admin</strong> e senha <strong>1234</strong>.</p>
            <?php if ($erro): ?><div class="alerta erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <label><span>Usuário</span><input type="text" name="usuario" placeholder="admin" autocomplete="username" required></label>
            <label><span>Senha</span><input type="password" name="senha" placeholder="1234" autocomplete="current-password" required></label>
            <button class="btn-link cheio" type="submit">Entrar no painel</button>
            <a class="link-voltar" href="index.php">Voltar ao jogo</a>
        </form>
    </main>
</body>
</html>
<?php
exit;
endif;


if (!empty($_SESSION['admin_logado']) && isset($_GET['exportar'])) {
    $pdo = pdo();
    $tipo = $_GET['exportar'];
    $arquivo = $tipo === 'partidas' ? 'historico_partidas.csv' : 'ranking_flappy_marcio.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $arquivo . '"');
    $saida = fopen('php://output', 'w');
    fputcsv($saida, ['Flappy Márcio'], ';');
    fputcsv($saida, ['Desenvolvedores', 'Eric, Vinicius e Jhonatan'], ';');
    fputcsv($saida, [], ';');
    if ($tipo === 'partidas') {
        fputcsv($saida, ['Jogador', 'Dificuldade', 'Pontos', 'Fase', 'Tempo', 'Velocidade', 'Data'], ';');
        $linhas = $pdo->query('SELECT j.nome, p.dificuldade, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.criado_em data FROM partidas p JOIN jogadores j ON j.id = p.jogador_id ORDER BY p.criado_em DESC')->fetchAll();
    } else {
        fputcsv($saida, ['Jogador', 'Dificuldade', 'Pontos', 'Fase', 'Tempo', 'Velocidade', 'Data'], ';');
        $linhas = $pdo->query('SELECT j.nome, p.dificuldade, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.atualizado_em data FROM pontuacoes p JOIN jogadores j ON j.id = p.jogador_id ORDER BY p.pontos DESC, p.atualizado_em ASC')->fetchAll();
    }
    foreach ($linhas as $linha) {
        fputcsv($saida, [$linha['nome'], $linha['dificuldade'], $linha['pontos'], $linha['fase'], $linha['duracao_seg'], $linha['velocidade_final'], $linha['data']], ';');
    }
    exit;
}

try {
    $pdo = pdo();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'salvar_config') {
            $permitidas = ['velocidade_inicial', 'aumento_fase', 'velocidade_maxima', 'pontos_por_fase', 'abertura_inicial', 'abertura_minima', 'gravidade', 'pulo'];
            $stmt = $pdo->prepare('UPDATE configuracoes SET valor = ? WHERE chave = ?');
            foreach ($permitidas as $chave) {
                if (isset($_POST[$chave]) && is_numeric(str_replace(',', '.', $_POST[$chave]))) {
                    $stmt->execute([str_replace(',', '.', $_POST[$chave]), $chave]);
                }
            }
            $mensagem = 'Configurações salvas.';
        }

        if ($acao === 'limpar_ranking') {
            $pdo->exec('DELETE FROM missoes_concluidas');
            $pdo->exec('DELETE FROM conquistas');
            $pdo->exec('DELETE FROM pontuacoes');
            $pdo->exec('DELETE FROM partidas');
            $pdo->exec('DELETE FROM jogadores');
            $mensagem = 'Ranking e histórico limpos.';
        }

        if ($acao === 'excluir_jogador') {
            $id = filter_input(INPUT_POST, 'jogador_id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare('DELETE FROM jogadores WHERE id = ?');
                $stmt->execute([$id]);
                $mensagem = 'Jogador removido.';
            }
        }
    }

    $stats = estatisticas_gerais();
    $configs = configuracoes_jogo();
    $ranking = $pdo->query('SELECT j.id, j.nome, p.dificuldade, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.atualizado_em
        FROM pontuacoes p
        JOIN jogadores j ON j.id = p.jogador_id
        ORDER BY p.pontos DESC, p.atualizado_em ASC
        LIMIT 30')->fetchAll();
    $partidas = $pdo->query('SELECT j.nome, p.dificuldade, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.criado_em
        FROM partidas p
        JOIN jogadores j ON j.id = p.jogador_id
        ORDER BY p.criado_em DESC
        LIMIT 20')->fetchAll();
    $conquistas = $pdo->query('SELECT j.nome, c.titulo, c.descricao, c.criado_em
        FROM conquistas c
        JOIN jogadores j ON j.id = c.jogador_id
        ORDER BY c.criado_em DESC
        LIMIT 20')->fetchAll();
    $missoes = $pdo->query('SELECT j.nome, m.titulo, m.data_ref, m.criado_em
        FROM missoes_concluidas m
        JOIN jogadores j ON j.id = m.jogador_id
        ORDER BY m.criado_em DESC
        LIMIT 20')->fetchAll();
    $porDificuldade = ranking_por_dificuldade();
} catch (Throwable $e) {
    $erro = $e->getMessage();
    $stats = estatisticas_gerais();
    $configs = configuracoes_jogo();
    $ranking = $partidas = $conquistas = $missoes = $porDificuldade = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel - Flappy Márcio</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body class="admin-body">
    <div class="cenario-fundo"><span class="lua"></span><span class="planeta planeta-1"></span><span class="nuvem nuvem-1"></span><span class="brilho brilho-1"></span></div>
    <main class="admin-page">
        <header class="admin-hero">
            <div>
                <span class="tag">Painel do jogo</span>
                <h1>Controle da partida</h1>
                <p>Acompanhe ranking, histórico, missões, conquistas e ajuste a dificuldade sem mexer no código.</p>
            </div>
            <div class="acoes-admin-topo"><a class="btn-link" href="index.php">Voltar ao jogo</a><a class="btn-link" href="admin.php?exportar=ranking">CSV ranking</a><a class="btn-link" href="admin.php?exportar=partidas">CSV partidas</a><a class="btn-link claro" href="admin.php?sair=1">Sair</a></div>
        </header>

        <?php if ($mensagem): ?><div class="alerta ok"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="alerta erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

        <section class="admin-stats">
            <article><span>Partidas</span><strong><?= $stats['partidas'] ?></strong></article>
            <article><span>Jogadores</span><strong><?= $stats['jogadores'] ?></strong></article>
            <article><span>Maior pontuação</span><strong><?= $stats['maior'] ?></strong></article>
            <article><span>Média</span><strong><?= $stats['media'] ?></strong></article>
            <article><span>Fase máxima</span><strong><?= $stats['fase'] ?></strong></article>
            <article><span>Modo mais jogado</span><strong><?= htmlspecialchars($stats['dificuldade']) ?></strong></article>
        </section>

        <section class="admin-grid">
            <div class="admin-card grande">
                <div class="titulo-card"><div><span class="subtitulo">Banco de dados</span><h2>Ranking completo</h2></div></div>
                <div class="tabela-wrap">
                    <table>
                        <thead><tr><th>#</th><th>Jogador</th><th>Modo</th><th>Pontos</th><th>Fase</th><th>Tempo</th><th>Vel.</th><th>Data</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($ranking as $i => $item): ?>
                            <tr>
                                <td><?= $i + 1 ?></td><td><?= htmlspecialchars($item['nome']) ?></td><td><?= htmlspecialchars($item['dificuldade']) ?></td><td><?= (int) $item['pontos'] ?></td><td><?= (int) $item['fase'] ?></td><td><?= number_format((float) $item['duracao_seg'], 1, ',', '.') ?>s</td><td><?= number_format((float) $item['velocidade_final'], 1, ',', '.') ?>x</td><td><?= data_br($item['atualizado_em']) ?></td>
                                <td><form method="post" onsubmit="return confirm('Remover este jogador?')"><input type="hidden" name="acao" value="excluir_jogador"><input type="hidden" name="jogador_id" value="<?= (int) $item['id'] ?>"><button class="btn-mini" type="submit">Excluir</button></form></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$ranking): ?><tr><td colspan="9">Nenhuma pontuação registrada.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <form class="admin-card" method="post">
                <input type="hidden" name="acao" value="salvar_config">
                <span class="subtitulo">Dificuldade base</span><h2>Configurações do jogo</h2>
                <div class="config-grid">
                    <?php foreach ($configs as $chave => $valor): ?>
                        <label><span><?= ucwords(str_replace('_', ' ', $chave)) ?></span><input type="number" step="0.01" name="<?= htmlspecialchars($chave) ?>" value="<?= htmlspecialchars((string) $valor) ?>"></label>
                    <?php endforeach; ?>
                </div>
                <button class="btn-link cheio" type="submit">Salvar dificuldade</button>
            </form>

            <div class="admin-card">
                <span class="subtitulo">Modos</span><h2>Desempenho por dificuldade</h2>
                <div class="lista-admin">
                    <?php foreach ($porDificuldade as $d): ?><div><strong><?= htmlspecialchars($d['dificuldade']) ?></strong><span><?= (int) $d['partidas'] ?> partidas • maior <?= (int) $d['maior'] ?> pts • média <?= number_format((float) $d['media'], 1, ',', '.') ?></span></div><?php endforeach; ?>
                    <?php if (!$porDificuldade): ?><p>Nenhuma partida jogada ainda.</p><?php endif; ?>
                </div>
            </div>

            <div class="admin-card">
                <span class="subtitulo">Histórico</span><h2>Últimas partidas</h2>
                <div class="lista-admin">
                    <?php foreach ($partidas as $p): ?><div><strong><?= htmlspecialchars($p['nome']) ?></strong><span><?= htmlspecialchars($p['dificuldade']) ?> • <?= (int) $p['pontos'] ?> pts • fase <?= (int) $p['fase'] ?> • <?= data_br($p['criado_em']) ?></span></div><?php endforeach; ?>
                    <?php if (!$partidas): ?><p>Nenhuma partida jogada ainda.</p><?php endif; ?>
                </div>
            </div>

            <div class="admin-card">
                <span class="subtitulo">Conquistas</span><h2>Últimas conquistas</h2>
                <div class="lista-admin">
                    <?php foreach ($conquistas as $c): ?><div><strong><?= htmlspecialchars($c['titulo']) ?></strong><span><?= htmlspecialchars($c['nome']) ?> • <?= htmlspecialchars($c['descricao']) ?></span></div><?php endforeach; ?>
                    <?php if (!$conquistas): ?><p>Nenhuma conquista liberada ainda.</p><?php endif; ?>
                </div>
            </div>

            <div class="admin-card">
                <span class="subtitulo">Missões</span><h2>Missões concluídas</h2>
                <div class="lista-admin">
                    <?php foreach ($missoes as $m): ?><div><strong><?= htmlspecialchars($m['titulo']) ?></strong><span><?= htmlspecialchars($m['nome']) ?> • <?= data_br($m['criado_em']) ?></span></div><?php endforeach; ?>
                    <?php if (!$missoes): ?><p>Nenhuma missão concluída ainda.</p><?php endif; ?>
                </div>
            </div>
        </section>

        <form class="limpar-card" method="post" onsubmit="return confirm('Limpar ranking, histórico, conquistas e missões?')"><input type="hidden" name="acao" value="limpar_ranking"><button type="submit">Limpar ranking e histórico</button></form>
    </main>
</body>
</html>
