<?php
require_once __DIR__ . '/php/funcoes.php';
$configJogo = configuracoes_jogo();
$stats = estatisticas_gerais();
$dificuldades = dificuldades_jogo();
$missoes = missoes_do_dia();
$skins = skins_jogo();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flappy Márcio</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
    <div class="cenario-fundo">
        <span class="lua"></span>
        <span class="planeta planeta-1"></span>
        <span class="planeta planeta-2"></span>
        <span class="nuvem nuvem-1"></span>
        <span class="nuvem nuvem-2"></span>
        <span class="nuvem nuvem-3"></span>
        <span class="linha-luz linha-1"></span>
        <span class="linha-luz linha-2"></span>
        <span class="brilho brilho-1"></span>
        <span class="brilho brilho-2"></span>
    </div>

    <main class="pagina">
        <nav class="menu-principal">
            <div>
                <strong>Flappy Márcio</strong>
                <span>Jogo, ranking e desafios</span>
            </div>
            <button type="button" data-ir="jogo">Jogar</button>
            <button type="button" data-ir="ranking">Ranking</button>
            <button type="button" data-ir="perfil">Perfil</button>
            <button type="button" data-ir="manual">Manual</button>
            <button type="button" data-ir="creditos">Créditos</button>
            <a href="admin.php">Admin</a>
        </nav>
        <section class="painel-lateral">
            <header class="topo-jogo">
                <div class="logo">FM</div>
                <div>
                    <span class="tag">Desafio dos Canos</span>
                    <h1>Flappy Márcio</h1>
                    <p>Escolha o modo, domine os canos, desbloqueie visuais e tente ficar no topo do ranking.</p>
                </div>
            </header>

            <section class="card card-jogador" id="areaPerfil">
                <div class="titulo-card">
                    <div>
                        <span class="subtitulo">Jogador</span>
                        <h2>Perfil da partida</h2>
                    </div>
                    <span class="status" id="statusJogador">Visitante</span>
                </div>

                <div class="abas-acesso" id="abasAcesso">
                    <button class="ativo" type="button" data-aba="login">Entrar</button>
                    <button type="button" data-aba="cadastro">Criar conta</button>
                </div>

                <form class="form-jogador" id="formLoginJogador">
                    <label for="usuarioLogin">Usuário</label>
                    <div class="linha-nome">
                        <input type="text" id="usuarioLogin" maxlength="24" placeholder="Seu usuário" autocomplete="username">
                        <input type="password" id="senhaLogin" maxlength="40" placeholder="Senha" autocomplete="current-password">
                    </div>
                    <button class="btn-acesso" type="submit">Entrar</button>
                </form>

                <form class="form-jogador escondido" id="formCadastroJogador">
                    <label for="usuarioCadastro">Criar jogador</label>
                    <div class="linha-nome">
                        <input type="text" id="usuarioCadastro" maxlength="24" placeholder="Nome de usuário" autocomplete="username">
                        <input type="password" id="senhaCadastro" maxlength="40" placeholder="Criar senha" autocomplete="new-password">
                    </div>
                    <button class="btn-acesso" type="submit">Cadastrar</button>
                </form>

                <button class="btn-sair-jogador escondido" id="btnSairJogador" type="button">Sair do jogador</button>
                <p id="mensagemNome" class="mensagem">Entre ou crie uma conta para salvar ranking, XP e conquistas.</p>

                <div class="perfil-grid" id="perfilJogador">
                    <div><span>Recorde</span><strong id="recordeJogador">0</strong></div>
                    <div><span>Ranking</span><strong id="posicaoJogador">--</strong></div>
                    <div><span>Partidas</span><strong id="perfilPartidas">0</strong></div>
                    <div><span>Fase máx.</span><strong id="perfilFase">1</strong></div>
                    <div><span>Média</span><strong id="perfilMedia">0</strong></div>
                    <div><span>Conquistas</span><strong id="perfilConquistas">0</strong></div>
                </div>

                <div class="nivel-box">
                    <div><span id="tituloNivel">Iniciante</span><strong id="nivelJogador">Nível 1</strong></div>
                    <div class="barra-xp"><span id="barraXp"></span></div>
                    <small id="xpTexto">0 / 100 XP</small>
                </div>
            </section>

            <section class="card dificuldade-card">
                <div class="titulo-card">
                    <div>
                        <span class="subtitulo">Modo de jogo</span>
                        <h2>Dificuldade</h2>
                    </div>
                    <span class="status" id="dificuldadeAtual">Normal</span>
                </div>
                <div class="dificuldades" id="dificuldades">
                    <?php foreach ($dificuldades as $chave => $item): ?>
                        <button class="dificuldade-opcao <?= $chave === 'normal' ? 'ativa' : '' ?>" type="button" data-dificuldade="<?= htmlspecialchars($chave) ?>">
                            <strong><?= htmlspecialchars($item['nome']) ?></strong>
                            <span><?= htmlspecialchars($item['texto']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </section>


            <section class="card skins-card" id="areaSkins">
                <div class="titulo-card">
                    <div>
                        <span class="subtitulo">Visuais</span>
                        <h2>Skins</h2>
                    </div>
                    <span class="status" id="skinAtual">Clássica</span>
                </div>
                <div class="skins-lista" id="skinsLista">
                    <?php foreach ($skins as $chave => $skin): ?>
                        <button class="skin-opcao <?= $chave === 'classica' ? 'ativa' : '' ?>" type="button" data-skin="<?= htmlspecialchars($chave) ?>">
                            <i class="skin-bolinha skin-<?= htmlspecialchars($chave) ?>"></i>
                            <strong><?= htmlspecialchars($skin['nome']) ?></strong>
                            <span><?= htmlspecialchars($skin['regra']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card missoes-card">
                <div class="titulo-card">
                    <div>
                        <span class="subtitulo">Objetivos</span>
                        <h2>Missões do dia</h2>
                    </div>
                    <span class="status">Hoje</span>
                </div>
                <div class="missoes-lista" id="missoesLista">
                    <?php foreach ($missoes as $missao): ?>
                        <div>
                            <strong><?= htmlspecialchars($missao['titulo']) ?></strong>
                            <span><?= htmlspecialchars($missao['descricao']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card manual-card fechado" id="manualCard">
                <button class="btn-manual" id="btnManual" type="button"><span>Manual do jogo</span><strong id="manualIcone">+</strong></button>
                <div class="conteudo-manual" id="conteudoManual">
                    <div><strong>Espaço</strong><span>Inicia, pula e reinicia após o fim da partida.</span></div>
                    <div><strong>P</strong><span>Pausa ou continua a partida.</span></div>
                    <div><strong>Escudo</strong><span>Protege de uma batida quando estiver ativo.</span></div>
                    <div><strong>Foco</strong><span>Reduz a velocidade por alguns segundos.</span></div>
                </div>
            </section>

            <section class="card creditos-card" id="areaCreditos">
                <div class="titulo-card">
                    <div>
                        <span class="subtitulo">Equipe</span>
                        <h2>Desenvolvedores</h2>
                    </div>
                </div>
                <div class="devs"><span>Eric</span><span>Vinicius</span><span>Jhonatan</span></div>
                <p>Jogo desenvolvido com páginas, ranking, perfil, missões e painel administrativo.</p>
            </section>
        </section>

        <section class="painel-jogo" id="painelJogo">
            <div class="hud-site">
                <div><span>Jogador</span><strong id="nomeAtual">Visitante</strong></div>
                <div><span>Dificuldade</span><strong id="hudDificuldade">Normal</strong></div>
                <div><span>Fase</span><strong id="faseAtual">1</strong></div>
                <div><span>Velocidade</span><strong id="velocidadeHud">1.0x</strong></div>
                <button id="btnApresentacao" class="btn-secundario" type="button">Modo apresentação</button>
                <button id="btnTelaCheia" class="btn-secundario btn-full" type="button">⛶ Tela cheia</button>
            </div>

            <div class="moldura-jogo">
                <canvas id="jogo" width="720" height="820"></canvas>
                <div class="dica-flutuante">Pressione <strong>Espaço</strong> para começar</div>
            </div>

            <section class="painel-baixo">
                <div class="card ranking-card">
                    <div class="titulo-card">
                        <div>
                            <span class="subtitulo">Recordes</span>
                            <h2>Ranking</h2>
                        </div>
                        <button class="btn-secundario" id="btnAtualizarRanking" type="button">Atualizar</button>
                    </div>
                    <div class="filtros-ranking">
                        <button class="ativo" data-rank="geral">Geral</button>
                        <button data-rank="hoje">Hoje</button>
                        <button data-rank="semana">Semana</button>
                        <button data-rank="dif:facil">Fácil</button>
                        <button data-rank="dif:normal">Normal</button>
                        <button data-rank="dif:dificil">Difícil</button>
                        <button data-rank="dif:insano">Insano</button>
                    </div>
                    <ol class="ranking" id="ranking"></ol>
                </div>

                <div class="card resumo-card">
                    <div class="titulo-card">
                        <div>
                            <span class="subtitulo">Pós-partida</span>
                            <h2>Resumo</h2>
                        </div>
                    </div>
                    <div class="resumo-final" id="resumoFinal">
                        <span>Jogue uma partida para ver pontos, fase, tempo, velocidade e missões concluídas.</span>
                    </div>
                    <div class="conquistas-lista" id="conquistasJogador"></div>
                </div>
            </section>

            <section class="card estatisticas-card">
                <div class="mini-estatisticas">
                    <div><span>Partidas</span><strong id="statPartidas"><?= (int) $stats['partidas'] ?></strong></div>
                    <div><span>Jogadores</span><strong id="statJogadores"><?= (int) $stats['jogadores'] ?></strong></div>
                    <div><span>Maior pontuação</span><strong id="statMaior"><?= (int) $stats['maior'] ?></strong></div>
                    <div><span>Campeão</span><strong id="statCampeao"><?= htmlspecialchars($stats['campeao']) ?></strong></div>
                </div>
            </section>
        </section>
    </main>

    <a href="admin.php" class="atalho-admin">Painel admin</a>

    <script>
        window.CONFIG_JOGO = <?= json_encode($configJogo, JSON_UNESCAPED_UNICODE) ?>;
        window.DIFICULDADES_JOGO = <?= json_encode($dificuldades, JSON_UNESCAPED_UNICODE) ?>;
        window.MISSOES_DIA = <?= json_encode($missoes, JSON_UNESCAPED_UNICODE) ?>;
        window.SKINS_JOGO = <?= json_encode($skins, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="assets/js/jogo.js"></script>
</body>
</html>
