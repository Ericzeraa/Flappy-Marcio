<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/catalogo.php';
require_once __DIR__ . '/repositorios.php';
require_once __DIR__ . '/servicos.php';
require_once __DIR__ . '/api_jogo.php';

function resposta_json(array $dados, int $codigo = 200): void
{
    \App\Base\RespostaJson::enviar($dados, $codigo);
}

function limpar_texto(?string $valor): string
{
    return \App\Base\Sanitizador::texto($valor);
}


function token_csrf(): string
{
    return \App\Base\Csrf::token();
}

function validar_csrf(?string $token): bool
{
    return \App\Base\Csrf::validar($token);
}

function campo_csrf(): string
{
    return \App\Base\Csrf::campo();
}

function nome_chave(string $nome): string
{
    $nome = mb_strtolower(limpar_texto($nome), 'UTF-8');
    $trocas = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'];
    return strtr($nome, $trocas);
}

function pdo_sem_banco(): PDO
{
    return new PDO('mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}

function pdo(): PDO
{
    return \App\Base\Conexao::pdo('preparar_banco');
}

function coluna_existe(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([DB_NAME, $tabela, $coluna]);
    return (int) $stmt->fetchColumn() > 0;
}

function adicionar_coluna(PDO $pdo, string $tabela, string $coluna, string $sql): void
{
    if (!coluna_existe($pdo, $tabela, $coluna)) {
        $pdo->exec("ALTER TABLE {$tabela} ADD COLUMN {$sql}");
    }
}

function preparar_banco(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS jogadores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(50) NOT NULL,
        nome_chave VARCHAR(50) NOT NULL UNIQUE,
        senha_hash VARCHAR(255) NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        xp INT NOT NULL DEFAULT 0,
        skin VARCHAR(30) NOT NULL DEFAULT 'classica'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS partidas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jogador_id INT NOT NULL,
        pontos INT NOT NULL DEFAULT 0,
        fase INT NOT NULL DEFAULT 1,
        duracao_seg DECIMAL(8,2) NOT NULL DEFAULT 0,
        velocidade_final DECIMAL(5,2) NOT NULL DEFAULT 1,
        dificuldade VARCHAR(20) NOT NULL DEFAULT 'normal',
        skin VARCHAR(30) NOT NULL DEFAULT 'classica',
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pontuacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jogador_id INT NOT NULL,
        dificuldade VARCHAR(20) NOT NULL DEFAULT 'normal',
        pontos INT NOT NULL DEFAULT 0,
        fase INT NOT NULL DEFAULT 1,
        duracao_seg DECIMAL(8,2) NOT NULL DEFAULT 0,
        velocidade_final DECIMAL(5,2) NOT NULL DEFAULT 1,
        atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY jogador_dificuldade (jogador_id, dificuldade),
        FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS conquistas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jogador_id INT NOT NULL,
        codigo VARCHAR(60) NOT NULL,
        titulo VARCHAR(80) NOT NULL,
        descricao VARCHAR(160) NOT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY jogador_conquista (jogador_id, codigo),
        FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS missoes_concluidas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jogador_id INT NOT NULL,
        codigo VARCHAR(60) NOT NULL,
        titulo VARCHAR(80) NOT NULL,
        data_ref DATE NOT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY jogador_missao (jogador_id, codigo, data_ref),
        FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS administradores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(40) NOT NULL UNIQUE,
        senha_hash VARCHAR(255) NOT NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


    $pdo->exec("CREATE TABLE IF NOT EXISTS partidas_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jogador_id INT NOT NULL,
        token CHAR(48) NOT NULL UNIQUE,
        dificuldade VARCHAR(20) NOT NULL DEFAULT 'normal',
        skin VARCHAR(30) NOT NULL DEFAULT 'classica',
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        usado_em DATETIME NULL,
        INDEX idx_token_jogador (jogador_id, token),
        INDEX idx_token_expiracao (criado_em, usado_em),
        FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmtAdmin = $pdo->prepare('SELECT id FROM administradores WHERE usuario = ? LIMIT 1');
    $stmtAdmin->execute([ADMIN_PADRAO_USUARIO]);
    if (!$stmtAdmin->fetch()) {
        $stmtAdmin = $pdo->prepare('INSERT INTO administradores (usuario, senha_hash) VALUES (?, ?)');
        $stmtAdmin->execute([ADMIN_PADRAO_USUARIO, \App\Base\Auth::gerarSenha(ADMIN_PADRAO_SENHA)]);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(60) NOT NULL UNIQUE,
        valor VARCHAR(120) NOT NULL,
        descricao VARCHAR(160) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    adicionar_coluna($pdo, 'jogadores', 'senha_hash', "senha_hash VARCHAR(255) NULL");
    adicionar_coluna($pdo, 'jogadores', 'xp', "xp INT NOT NULL DEFAULT 0");
    adicionar_coluna($pdo, 'jogadores', 'skin', "skin VARCHAR(30) NOT NULL DEFAULT 'classica'");
    adicionar_coluna($pdo, 'partidas', 'dificuldade', "dificuldade VARCHAR(20) NOT NULL DEFAULT 'normal'");
    adicionar_coluna($pdo, 'partidas', 'skin', "skin VARCHAR(30) NOT NULL DEFAULT 'classica'");
    adicionar_coluna($pdo, 'pontuacoes', 'dificuldade', "dificuldade VARCHAR(20) NOT NULL DEFAULT 'normal'");
    try { $pdo->exec('ALTER TABLE pontuacoes ADD INDEX jogador_fk_idx (jogador_id)'); } catch (Throwable $e) {}
    try { $pdo->exec('ALTER TABLE pontuacoes DROP INDEX jogador_id'); } catch (Throwable $e) {}
    try { $pdo->exec('ALTER TABLE pontuacoes ADD UNIQUE KEY jogador_dificuldade (jogador_id, dificuldade)'); } catch (Throwable $e) {}
    try { $pdo->exec('CREATE INDEX idx_partidas_data ON partidas (criado_em)'); } catch (Throwable $e) {}
    try { $pdo->exec('CREATE INDEX idx_partidas_dificuldade ON partidas (dificuldade)'); } catch (Throwable $e) {}
    try { $pdo->exec('CREATE INDEX idx_pontuacoes_ranking ON pontuacoes (pontos, atualizado_em)'); } catch (Throwable $e) {}

    $configs = [
        ['velocidade_inicial', '3.85', 'Velocidade inicial dos canos'],
        ['aumento_fase', '0.34', 'Aumento de velocidade a cada fase'],
        ['velocidade_maxima', '6.40', 'Limite de velocidade'],
        ['pontos_por_fase', '6', 'Pontos para mudar de fase'],
        ['abertura_inicial', '218', 'Espaço inicial entre canos'],
        ['abertura_minima', '178', 'Menor espaço entre canos'],
        ['gravidade', '0.46', 'Força da gravidade'],
        ['pulo', '-8.25', 'Força do pulo']
    ];

    $stmt = $pdo->prepare('INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES (?, ?, ?)');
    foreach ($configs as $config) {
        $stmt->execute($config);
    }
}

function configuracoes_jogo(): array
{
    $config = [
        'velocidade_inicial' => 3.85,
        'aumento_fase' => 0.34,
        'velocidade_maxima' => 6.4,
        'pontos_por_fase' => 6,
        'abertura_inicial' => 218,
        'abertura_minima' => 178,
        'gravidade' => 0.46,
        'pulo' => -8.25
    ];

    try {
        foreach (pdo()->query('SELECT chave, valor FROM configuracoes')->fetchAll() as $linha) {
            if (array_key_exists($linha['chave'], $config)) {
                $config[$linha['chave']] = is_numeric($linha['valor']) ? (float) $linha['valor'] : $linha['valor'];
            }
        }
    } catch (Throwable $erro) {}

    return $config;
}

function dificuldades_jogo(): array
{
    return \App\Jogo\CatalogoJogo::dificuldades();
}

function dificuldade_valida(string $dificuldade): string
{
    return \App\Jogo\CatalogoJogo::dificuldadeValida($dificuldade);
}

function skins_jogo(): array
{
    return \App\Jogo\CatalogoJogo::skins();
}

function skin_valida(string $skin): string
{
    return \App\Jogo\CatalogoJogo::skinValida($skin);
}


function h(mixed $valor): string
{
    return \App\Base\Sanitizador::html($valor);
}

function asset_v(string $caminho): string
{
    $arquivo = dirname(__DIR__) . '/' . ltrim($caminho, '/');
    $versao = is_file($arquivo) ? filemtime($arquivo) : time();
    return $caminho . '?v=' . $versao;
}

function nivel_jogador(int $xp): array
{
    return \App\Jogo\Nivel::calcular($xp);
}

function missoes_do_dia(): array
{
    return \App\Jogo\CatalogoJogo::missoesDoDia();
}

function estatisticas_gerais(): array
{
    try {
        return (new \App\Regras\Metricas(pdo()))->gerais();
    } catch (Throwable $erro) {
        return ['partidas' => 0, 'jogadores' => 0, 'media' => 0, 'maior' => 0, 'fase' => 1, 'campeao' => 'MySQL offline', 'pontos_campeao' => 0, 'dificuldade' => 'normal', 'partidas_hoje' => 0, 'maior_hoje' => 0, 'jogadores_ativos' => 0, 'mais_ativo' => 'Aguardando', 'tempo_medio' => 0];
    }
}


function texto_jogador(string $texto, int $maximo = 50): string
{
    $texto = limpar_texto($texto);
    if (mb_strlen($texto, 'UTF-8') > $maximo) {
        $texto = mb_substr($texto, 0, $maximo, 'UTF-8');
    }
    return $texto;
}

function usuario_limpo(string $usuario): string
{
    return \App\Base\Sanitizador::usuario($usuario);
}

function validar_usuario_senha(string $usuario, string $senha): ?string
{
    try {
        \App\Base\Validador::usuarioSenha($usuario, $senha);
        return null;
    } catch (InvalidArgumentException $erro) {
        return $erro->getMessage();
    }
}

function criar_jogador_com_senha(string $usuario, string $senha): array
{
    $usuario = usuario_limpo($usuario);
    $erro = validar_usuario_senha($usuario, $senha);
    if ($erro) throw new InvalidArgumentException($erro);

    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT id FROM jogadores WHERE nome_chave = ? LIMIT 1');
    $stmt->execute([$usuario]);
    if ($stmt->fetch()) throw new InvalidArgumentException('Esse usuário já existe.');

    $stmt = $pdo->prepare('INSERT INTO jogadores (nome, nome_chave, senha_hash) VALUES (?, ?, ?)');
    $stmt->execute([$usuario, $usuario, \App\Base\Auth::gerarSenha($senha)]);
    return buscar_jogador_por_id((int) $pdo->lastInsertId());
}

function login_jogador(string $usuario, string $senha): array
{
    $usuario = usuario_limpo($usuario);
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT * FROM jogadores WHERE nome_chave = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $jogador = $stmt->fetch();
    if (!$jogador || !\App\Base\Auth::conferirSenha($senha, $jogador['senha_hash'] ?? null)) {
        throw new InvalidArgumentException('Usuário ou senha inválidos.');
    }
    return $jogador;
}

function buscar_jogador_por_id(int $id): array
{
    $stmt = pdo()->prepare('SELECT * FROM jogadores WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $jogador = $stmt->fetch();
    if (!$jogador) throw new RuntimeException('Jogador não encontrado.');
    return $jogador;
}

function jogador_sessao(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $id = (int) ($_SESSION['jogador_id'] ?? 0);
    if (!$id) return null;
    try { return buscar_jogador_por_id($id); } catch (Throwable $e) { unset($_SESSION['jogador_id']); return null; }
}

function posicao_jogador(int $jogadorId, string $dificuldade = 'geral'): ?int
{
    $pdo = pdo();
    if ($dificuldade !== 'geral') {
        $stmt = $pdo->prepare('SELECT jogador_id FROM pontuacoes WHERE dificuldade = ? ORDER BY pontos DESC, atualizado_em ASC');
        $stmt->execute([$dificuldade]);
    } else {
        $stmt = $pdo->query('SELECT jogador_id FROM pontuacoes ORDER BY pontos DESC, atualizado_em ASC');
    }

    $posicao = 1;
    while ($linha = $stmt->fetch()) {
        if ((int) $linha['jogador_id'] === $jogadorId) {
            return $posicao;
        }
        $posicao++;
    }
    return null;
}

function conquistas_catalogo(): array
{
    return \App\Jogo\CatalogoJogo::conquistas();
}

function liberar_conquistas(int $jogadorId, int $pontos, int $fase, float $duracao, string $dificuldade, ?int $posicao): array
{
    $lista = [
        ['primeiro_voo', 'Primeira voada', 'Começou a jogar e salvou a primeira partida.', true],
        ['fase_3', 'Pegou o jeito', 'Chegou até a fase 3.', $fase >= 3],
        ['fase_5', 'Foi longe', 'Chegou até a fase 5.', $fase >= 5],
        ['dez_pontos', 'Dez já dá jogo', 'Fez pelo menos 10 pontos.', $pontos >= 10],
        ['vinte_pontos', 'Passou raspando', 'Fez pelo menos 20 pontos.', $pontos >= 20],
        ['um_minuto', 'Segurou bem', 'Ficou 60 segundos sem cair.', $duracao >= 60],
        ['modo_insano', 'Sem medo do insano', 'Jogou uma partida no modo insano.', $dificuldade === 'insano'],
        ['podio', 'Entrou no pódio', 'Ficou entre os três melhores do ranking.', $posicao !== null && $posicao <= 3],
        ['skin_azul', 'Azul desbloqueada', 'Fez 8 pontos e liberou uma skin nova.', $pontos >= 8],
        ['skin_dourada', 'Dourada na conta', 'Chegou na fase 4 e liberou a dourada.', $fase >= 4],
        ['skin_neon', 'Neon liberado', 'Jogou 8 partidas e liberou o neon.', perfil_jogador($jogadorId)['partidas'] >= 8]
    ];

    $novas = [];
    $pdo = pdo();
    $stmt = $pdo->prepare('INSERT IGNORE INTO conquistas (jogador_id, codigo, titulo, descricao) VALUES (?, ?, ?, ?)');

    foreach ($lista as $item) {
        if (!$item[3]) continue;
        $stmt->execute([$jogadorId, $item[0], $item[1], $item[2]]);
        if ($stmt->rowCount() > 0) {
            $novas[] = ['codigo' => $item[0], 'titulo' => $item[1], 'descricao' => $item[2]];
        }
    }

    return $novas;
}

function concluir_missoes(int $jogadorId, int $pontos, int $fase, float $duracao, string $dificuldade): array
{
    $novas = [];
    $hoje = date('Y-m-d');
    $pdo = pdo();
    $stmt = $pdo->prepare('INSERT IGNORE INTO missoes_concluidas (jogador_id, codigo, titulo, data_ref) VALUES (?, ?, ?, ?)');

    foreach (missoes_do_dia() as $missao) {
        $ok = false;
        if ($missao['tipo'] === 'pontos') $ok = $pontos >= (int) $missao['meta'];
        if ($missao['tipo'] === 'fase') $ok = $fase >= (int) $missao['meta'];
        if ($missao['tipo'] === 'tempo') $ok = $duracao >= (float) $missao['meta'];
        if ($missao['tipo'] === 'dificuldade') $ok = in_array($dificuldade, ['dificil', 'insano'], true);

        if ($ok) {
            $stmt->execute([$jogadorId, $missao['codigo'], $missao['titulo'], $hoje]);
            if ($stmt->rowCount() > 0) {
                $novas[] = ['codigo' => $missao['codigo'], 'titulo' => $missao['titulo']];
            }
        }
    }

    return $novas;
}


function conquistas_jogador(int $jogadorId): array
{
    $stmt = pdo()->prepare('SELECT codigo, titulo, descricao, criado_em FROM conquistas WHERE jogador_id = ? ORDER BY criado_em DESC');
    $stmt->execute([$jogadorId]);
    return $stmt->fetchAll();
}

function perfil_jogador(int $jogadorId): array
{
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT COUNT(*) partidas, COALESCE(MAX(pontos),0) melhor, COALESCE(MAX(fase),1) fase, COALESCE(AVG(pontos),0) media, COALESCE(SUM(duracao_seg),0) tempo, COALESCE(SUM(pontos),0) pontos_total FROM partidas WHERE jogador_id = ?');
    $stmt->execute([$jogadorId]);
    $dados = $stmt->fetch() ?: [];

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM conquistas WHERE jogador_id = ?');
    $stmt->execute([$jogadorId]);
    $conquistas = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM missoes_concluidas WHERE jogador_id = ? AND data_ref = CURDATE()');
    $stmt->execute([$jogadorId]);
    $missoesHoje = (int) $stmt->fetchColumn();

    $partidas = (int) ($dados['partidas'] ?? 0);
    $melhor = (int) ($dados['melhor'] ?? 0);
    $fase = (int) ($dados['fase'] ?? 1);
    $pontosTotal = (int) ($dados['pontos_total'] ?? 0);
    $xp = ($partidas * 12) + ($pontosTotal * 4) + ($fase * 15) + ($conquistas * 30) + ($missoesHoje * 10);
    $nivel = nivel_jogador($xp);

    return [
        'partidas' => $partidas,
        'melhor' => $melhor,
        'fase' => $fase,
        'media' => round((float) ($dados['media'] ?? 0), 1),
        'tempo' => round((float) ($dados['tempo'] ?? 0), 1),
        'conquistas' => $conquistas,
        'missoesHoje' => $missoesHoje,
        'xp' => $nivel['xp'],
        'nivel' => $nivel['nivel'],
        'tituloNivel' => $nivel['titulo'],
        'xpAtual' => $nivel['atual'],
        'xpProximo' => $nivel['proximo']
    ];
}

function ranking_por_dificuldade(): array
{
    $stmt = pdo()->query('SELECT dificuldade, COUNT(*) partidas, COALESCE(MAX(pontos),0) maior, COALESCE(AVG(pontos),0) media FROM partidas GROUP BY dificuldade ORDER BY partidas DESC');
    return $stmt->fetchAll();
}


function login_admin(string $usuario, string $senha): bool
{
    $usuario = usuario_limpo($usuario);
    $stmt = pdo()->prepare('SELECT senha_hash FROM administradores WHERE usuario = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $admin = $stmt->fetch();

    return $admin && \App\Base\Auth::conferirSenha($senha, $admin['senha_hash'] ?? null);
}

require_once __DIR__ . '/classes/Jogador.php';
require_once __DIR__ . '/classes/Ranking.php';
require_once __DIR__ . '/classes/PainelAdmin.php';

function data_br(?string $data): string
{
    return $data ? date('d/m/Y H:i', strtotime($data)) : '-';
}
