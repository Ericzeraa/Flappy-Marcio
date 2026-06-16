<?php
session_start();
require_once __DIR__ . '/php/funcoes.php';
\App\Base\Cabecalhos::segurancaBasica();

$mensagem = '';
$erro = '';
$painelAdmin = new PainelAdmin();

if (isset($_GET['sair'])) {
    unset($_SESSION['admin_logado']);
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'login_admin') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (!validar_csrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } elseif ($painelAdmin->autenticar($usuario, $senha)) {
        session_regenerate_id(true);
        $_SESSION['admin_logado'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $erro = 'Usuário ou senha incorretos.';
    }
}

if (empty($_SESSION['admin_logado'])):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body class="admin-body login-admin-body">
    <div class="fundo-decorativo"><span class="lua"></span><span class="planeta planeta-1"></span><span class="nuvem nuvem-1"></span><span class="brilho brilho-1"></span></div>
    <main class="login-admin-page">
        <form class="login-admin-card" method="post">
            <input type="hidden" name="acao" value="login_admin">
            <?= campo_csrf() ?>
            <span class="tag">Acesso restrito</span>
            <h1>Painel Flappy Márcio</h1>
            <p>Entre com o usuário do painel. Prometo que aqui não tem cano passando voando.</p>
            <?php if ($erro): ?><div class="alerta erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <label><span>Usuário</span><input type="text" name="usuario" placeholder="admin" autocomplete="username" required></label>
            <label><span>Senha</span><input type="password" name="senha" placeholder="Senha" autocomplete="current-password" required></label>
            <button class="btn-link cheio" type="submit">Entrar</button>
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
    fputcsv($saida, ['Feito por', 'Eric, Vinicius e Jhonatan'], ';');
    fputcsv($saida, ['Exportado em', date('d/m/Y H:i')], ';');
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
        if (!validar_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
        }

        $acao = $_POST['acao'] ?? '';

        if ($acao === 'salvar_config') {
            $painelAdmin->salvarConfiguracoes($_POST);
            $mensagem = 'Configurações salvas. Agora é testar para ver quem reclama primeiro.';
        }

        if ($acao === 'limpar_ranking') {
            $painelAdmin->limparRanking();
            $mensagem = 'Ranking e histórico limpos. Zerou tudo, até a vergonha das quedas.';
        }

        if ($acao === 'excluir_jogador') {
            $id = filter_input(INPUT_POST, 'jogador_id', FILTER_VALIDATE_INT);
            if ($id) {
                $painelAdmin->excluirJogador($id);
                $mensagem = 'Jogador removido do jogo.';
            }
        }
    }

    $stats = estatisticas_gerais();
    $configs = configuracoes_jogo();
    $dadosPainel = $painelAdmin->dadosPainel();
    $ranking = $dadosPainel['ranking'];
    $partidas = $dadosPainel['partidas'];
    $conquistas = $dadosPainel['conquistas'];
    $missoes = $dadosPainel['missoes'];
    $porDificuldade = $dadosPainel['porDificuldade'];
    $indicadoresTecnicos = $dadosPainel['indicadoresTecnicos'] ?? [];
} catch (Throwable $e) {
    $erro = $e->getMessage();
    $stats = estatisticas_gerais();
    $configs = configuracoes_jogo();
    $ranking = $partidas = $conquistas = $missoes = $porDificuldade = [];
    $indicadoresTecnicos = [];
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
    <div class="fundo-decorativo"><span class="lua"></span><span class="planeta planeta-1"></span><span class="nuvem nuvem-1"></span><span class="brilho brilho-1"></span></div>
    <main class="admin-page">
        <header class="admin-hero">
            <div>
                <span class="tag">Painel do jogo</span>
                <h1>Painel do jogo</h1>
                <p>Aqui dá para ver o ranking, conferir as últimas partidas e ajustar a dificuldade sem ficar caçando arquivo no projeto.</p>
            </div>
            <div class="acoes-admin-topo"><a class="btn-link" href="index.php">Voltar ao jogo</a><a class="btn-link" href="admin.php?exportar=ranking">CSV ranking</a><a class="btn-link" href="admin.php?exportar=partidas">CSV partidas</a><a class="btn-link claro" href="admin.php?sair=1">Sair</a></div>
        </header>

        <?php if ($mensagem): ?><div class="alerta ok"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="alerta erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

        <section class="admin-stats">
            <article><span>Partidas</span><strong><?= (int) $stats['partidas'] ?></strong></article>
            <article><span>Hoje</span><strong><?= (int) ($stats['partidas_hoje'] ?? 0) ?></strong></article>
            <article><span>Jogadores</span><strong><?= (int) $stats['jogadores'] ?></strong></article>
            <article><span>Ativos na semana</span><strong><?= (int) ($stats['jogadores_ativos'] ?? 0) ?></strong></article>
            <article><span>Maior ponto</span><strong><?= (int) $stats['maior'] ?></strong></article>
            <article><span>Maior hoje</span><strong><?= (int) ($stats['maior_hoje'] ?? 0) ?></strong></article>
            <article><span>Média</span><strong><?= number_format((float) $stats['media'], 1, ',', '.') ?></strong></article>
            <article><span>Tempo médio</span><strong><?= number_format((float) ($stats['tempo_medio'] ?? 0), 1, ',', '.') ?>s</strong></article>
            <article><span>Fase máxima</span><strong><?= (int) $stats['fase'] ?></strong></article>
            <article><span>Modo mais jogado</span><strong><?= htmlspecialchars($stats['dificuldade']) ?></strong></article>
            <article><span>Melhor jogador</span><strong><?= htmlspecialchars($stats['campeao'] ?? 'Aguardando') ?></strong></article>
            <article><span>Mais ativo</span><strong><?= htmlspecialchars($stats['mais_ativo'] ?? 'Aguardando') ?></strong></article>
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
                                <td><form method="post" onsubmit="return confirm('Remover este jogador?')"><input type="hidden" name="acao" value="excluir_jogador"><?= campo_csrf() ?><input type="hidden" name="jogador_id" value="<?= (int) $item['id'] ?>"><button class="btn-mini" type="submit">Excluir</button></form></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$ranking): ?><tr><td colspan="9">Nenhuma pontuação salva ainda. O ranking está em paz por enquanto.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="admin-card">
                <span class="subtitulo">Resumo do backend</span><h2>Indicadores do jogo</h2>
                <div class="lista-admin">
                    <div><strong>Ranking ativo</strong><span><?= (int) ($indicadoresTecnicos['totalRanking'] ?? 0) ?> jogadores no ranking</span></div>
                    <div><strong>Histórico recente</strong><span><?= (int) ($indicadoresTecnicos['ultimasPartidas'] ?? 0) ?> partidas recentes</span></div>
                    <div><strong>Modos utilizados</strong><span><?= (int) ($indicadoresTecnicos['modosUsados'] ?? 0) ?> modos já testados</span></div>
                    <div><strong>Tempo médio</strong><span><?= number_format((float) ($indicadoresTecnicos['mediaTempo'] ?? 0), 1, ',', '.') ?>s por partida</span></div>
                    <div><strong>Missões de hoje</strong><span><?= (int) ($indicadoresTecnicos['aproveitamentoMissoesHoje'] ?? 0) ?> missões concluídas</span></div>
                </div>
            </div>

            <form class="admin-card" method="post">
                <input type="hidden" name="acao" value="salvar_config">
                <?= campo_csrf() ?>
                <span class="subtitulo">Ajustes</span><h2>Configurações do jogo</h2>
                <div class="config-grid">
                    <?php foreach ($configs as $chave => $valor): ?>
                        <label><span><?= ucwords(str_replace('_', ' ', $chave)) ?></span><input type="number" step="0.01" name="<?= htmlspecialchars($chave) ?>" value="<?= htmlspecialchars((string) $valor) ?>"></label>
                    <?php endforeach; ?>
                </div>
                <button class="btn-link cheio" type="submit">Salvar ajustes</button>
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
                    <?php if (!$conquistas): ?><p>Nenhuma conquista liberada ainda. O Márcio está economizando medalha.</p><?php endif; ?>
                </div>
            </div>

            <div class="admin-card">
                <span class="subtitulo">Missões</span><h2>Missões concluídas</h2>
                <div class="lista-admin">
                    <?php foreach ($missoes as $m): ?><div><strong><?= htmlspecialchars($m['titulo']) ?></strong><span><?= htmlspecialchars($m['nome']) ?> • <?= data_br($m['criado_em']) ?></span></div><?php endforeach; ?>
                    <?php if (!$missoes): ?><p>Nenhuma missão concluída ainda. Está todo mundo aquecendo.</p><?php endif; ?>
                </div>
            </div>
        </section>

        <form class="limpar-card" method="post" onsubmit="return confirm('Limpar ranking, histórico, conquistas e missões?')"><input type="hidden" name="acao" value="limpar_ranking"><?= campo_csrf() ?><button type="submit">Limpar ranking e histórico</button></form>
    </main>
</body>
</html>
