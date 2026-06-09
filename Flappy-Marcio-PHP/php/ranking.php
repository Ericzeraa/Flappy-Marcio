<?php
session_start();
require_once __DIR__ . '/funcoes.php';

$acao = $_GET['acao'] ?? $_POST['acao'] ?? 'listar';

try {

    if ($acao === 'sessao') {
        $jogador = jogador_sessao();
        if (!$jogador) {
            resposta_json(['ok' => true, 'logado' => false, 'jogador' => null, 'perfil' => null, 'conquistas' => [], 'missoes' => missoes_do_dia(), 'skins' => skins_jogo()]);
        }
        resposta_json([
            'ok' => true,
            'logado' => true,
            'jogador' => ['id' => (int) $jogador['id'], 'nome' => $jogador['nome'], 'skin' => $jogador['skin']],
            'recorde' => perfil_jogador((int) $jogador['id'])['melhor'],
            'posicao' => posicao_jogador((int) $jogador['id']),
            'perfil' => perfil_jogador((int) $jogador['id']),
            'conquistas' => conquistas_jogador((int) $jogador['id']),
            'missoes' => missoes_do_dia(),
            'skins' => skins_jogo()
        ]);
    }

    if ($acao === 'registrar') {
        $usuario = $_POST['usuario'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $jogador = criar_jogador_com_senha($usuario, $senha);
        $_SESSION['jogador_id'] = (int) $jogador['id'];
        resposta_json(['ok' => true, 'mensagem' => 'Conta criada.', 'jogador' => ['id' => (int) $jogador['id'], 'nome' => $jogador['nome']], 'perfil' => perfil_jogador((int) $jogador['id'])]);
    }

    if ($acao === 'login') {
        $jogador = login_jogador($_POST['usuario'] ?? '', $_POST['senha'] ?? '');
        $_SESSION['jogador_id'] = (int) $jogador['id'];
        resposta_json(['ok' => true, 'mensagem' => 'Login realizado.', 'jogador' => ['id' => (int) $jogador['id'], 'nome' => $jogador['nome'], 'skin' => $jogador['skin']], 'perfil' => perfil_jogador((int) $jogador['id']), 'conquistas' => conquistas_jogador((int) $jogador['id']), 'posicao' => posicao_jogador((int) $jogador['id'])]);
    }

    if ($acao === 'sair') {
        unset($_SESSION['jogador_id']);
        resposta_json(['ok' => true]);
    }

    if ($acao === 'config') {
        resposta_json([
            'ok' => true,
            'config' => configuracoes_jogo(),
            'dificuldades' => dificuldades_jogo(),
            'missoes' => missoes_do_dia(),
            'estatisticas' => estatisticas_gerais(),
            'skins' => skins_jogo()
        ]);
    }

    if ($acao === 'listar') {
        $dificuldade = $_GET['dificuldade'] ?? 'geral';
        $periodo = $_GET['periodo'] ?? 'geral';
        $where = [];
        $params = [];

        if ($dificuldade !== 'geral') {
            $where[] = 'p.dificuldade = ?';
            $params[] = dificuldade_valida($dificuldade);
        }

        if ($periodo === 'hoje') $where[] = 'DATE(p.atualizado_em) = CURDATE()';
        if ($periodo === 'semana') $where[] = 'p.atualizado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = pdo()->prepare("SELECT j.nome, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.dificuldade, p.atualizado_em data
            FROM pontuacoes p
            JOIN jogadores j ON j.id = p.jogador_id
            {$sqlWhere}
            ORDER BY p.pontos DESC, p.atualizado_em ASC
            LIMIT 10");
        $stmt->execute($params);
        resposta_json(['ok' => true, 'ranking' => $stmt->fetchAll()]);
    }

    if ($acao === 'historico') {
        $stmt = pdo()->query('SELECT j.nome, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.dificuldade, p.criado_em data
            FROM partidas p
            JOIN jogadores j ON j.id = p.jogador_id
            ORDER BY p.criado_em DESC
            LIMIT 8');
        resposta_json(['ok' => true, 'historico' => $stmt->fetchAll()]);
    }

    if ($acao === 'estatisticas') {
        resposta_json(['ok' => true, 'estatisticas' => estatisticas_gerais(),
            'skins' => skins_jogo()]);
    }

    if ($acao === 'jogador') {
        $jogador = jogador_sessao();
        if (!$jogador) {
            resposta_json(['ok' => true, 'logado' => false, 'recorde' => 0, 'posicao' => null, 'conquistas' => [], 'perfil' => null, 'missoes' => missoes_do_dia(), 'skins' => skins_jogo()]);
        }
        $jogadorId = (int) $jogador['id'];
        resposta_json([
            'ok' => true,
            'logado' => true,
            'jogador' => ['id' => $jogadorId, 'nome' => $jogador['nome'], 'skin' => $jogador['skin']],
            'recorde' => perfil_jogador($jogadorId)['melhor'],
            'posicao' => posicao_jogador($jogadorId),
            'conquistas' => conquistas_jogador($jogadorId),
            'perfil' => perfil_jogador($jogadorId),
            'missoes' => missoes_do_dia(),
            'skins' => skins_jogo()
        ]);
    }

    if ($acao === 'salvar') {
        $jogador = jogador_sessao();
        if (!$jogador) {
            resposta_json(['ok' => false, 'mensagem' => 'Faça login para salvar a partida.'], 401);
        }
        $pontos = filter_input(INPUT_POST, 'pontos', FILTER_VALIDATE_INT);
        $fase = filter_input(INPUT_POST, 'fase', FILTER_VALIDATE_INT);
        $duracao = filter_input(INPUT_POST, 'duracao', FILTER_VALIDATE_FLOAT);
        $velocidade = filter_input(INPUT_POST, 'velocidade', FILTER_VALIDATE_FLOAT);
        $dificuldade = dificuldade_valida($_POST['dificuldade'] ?? 'normal');
        $skin = skin_valida($_POST['skin'] ?? 'classica');

        if ($pontos === false || $pontos === null || $pontos < 0) {
            resposta_json(['ok' => false, 'mensagem' => 'Pontuação inválida.'], 400);
        }

        $fase = max(1, (int) ($fase ?: 1));
        $duracao = max(0, (float) ($duracao ?: 0));
        $velocidade = max(1, (float) ($velocidade ?: 1));
        $pdo = pdo();
        $jogadorId = (int) $jogador['id'];

        $stmt = $pdo->prepare('UPDATE jogadores SET skin = ? WHERE id = ?');
        $stmt->execute([$skin, $jogadorId]);

        $stmt = $pdo->prepare('INSERT INTO partidas (jogador_id, pontos, fase, duracao_seg, velocidade_final, dificuldade, skin) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$jogadorId, $pontos, $fase, $duracao, $velocidade, $dificuldade, $skin]);

        $stmt = $pdo->prepare('SELECT pontos FROM pontuacoes WHERE jogador_id = ? AND dificuldade = ? LIMIT 1');
        $stmt->execute([$jogadorId, $dificuldade]);
        $atual = $stmt->fetch();
        $melhorou = !$atual || $pontos > (int) $atual['pontos'];

        if ($melhorou) {
            $stmt = $pdo->prepare('INSERT INTO pontuacoes (jogador_id, dificuldade, pontos, fase, duracao_seg, velocidade_final, atualizado_em)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE pontos = VALUES(pontos), fase = VALUES(fase), duracao_seg = VALUES(duracao_seg), velocidade_final = VALUES(velocidade_final), atualizado_em = NOW()');
            $stmt->execute([$jogadorId, $dificuldade, $pontos, $fase, $duracao, $velocidade]);
        }

        $posicao = posicao_jogador($jogadorId);
        $novas = liberar_conquistas($jogadorId, $pontos, $fase, $duracao, $dificuldade, $posicao);
        $missoes = concluir_missoes($jogadorId, $pontos, $fase, $duracao, $dificuldade);

        $stmt = $pdo->prepare('SELECT pontos, fase FROM pontuacoes WHERE jogador_id = ? ORDER BY pontos DESC LIMIT 1');
        $stmt->execute([$jogadorId]);
        $recorde = $stmt->fetch() ?: ['pontos' => $pontos, 'fase' => $fase];

        resposta_json([
            'ok' => true,
            'recorde' => (int) $recorde['pontos'],
            'fase' => (int) $recorde['fase'],
            'melhorou' => $melhorou,
            'posicao' => $posicao,
            'podio' => $posicao !== null && $posicao <= 3,
            'conquistas' => $novas,
            'missoesConcluidas' => $missoes,
            'perfil' => perfil_jogador($jogadorId),
            'estatisticas' => estatisticas_gerais(),
            'skins' => skins_jogo()
        ]);
    }

    resposta_json(['ok' => false, 'mensagem' => 'Ação inválida.'], 400);
} catch (Throwable $erro) {
    $codigo = $erro instanceof InvalidArgumentException ? 400 : 500;
    resposta_json(['ok' => false, 'mensagem' => $erro->getMessage()], $codigo);
}
