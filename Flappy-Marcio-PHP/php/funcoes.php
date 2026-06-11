<?php
require_once __DIR__ . '/config.php';

function resposta_json(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function limpar_texto(?string $valor): string
{
    $valor = trim((string) $valor);
    return preg_replace('/\s+/', ' ', $valor) ?? '';
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
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        preparar_banco($pdo);
        return $pdo;
    } catch (PDOException $erro) {
        try {
            $base = pdo_sem_banco();
            $base->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $base = null;
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            preparar_banco($pdo);
            return $pdo;
        } catch (PDOException $novoErro) {
            throw new RuntimeException('MySQL indisponível. Confira Apache e MySQL no XAMPP.');
        }
    }
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

    $configs = [
        ['velocidade_inicial', '3.85', 'Velocidade inicial dos canos'],
        ['aumento_fase', '0.34', 'Aumento de velocidade a cada fase'],
        ['velocidade_maxima', '6.40', 'Limite de velocidade'],
        ['pontos_por_fase', '6', 'Pontos necessários para avançar de fase'],
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
    return [
        'facil' => ['nome' => 'Fácil', 'vel' => 0.88, 'abertura' => 28, 'max' => -0.35, 'texto' => 'Mais espaço e ritmo tranquilo.'],
        'normal' => ['nome' => 'Normal', 'vel' => 1.00, 'abertura' => 0, 'max' => 0, 'texto' => 'Equilibrado para disputa de ranking.'],
        'dificil' => ['nome' => 'Difícil', 'vel' => 1.12, 'abertura' => -18, 'max' => 0.35, 'texto' => 'Mais rápido e com menos espaço.'],
        'insano' => ['nome' => 'Insano', 'vel' => 1.23, 'abertura' => -30, 'max' => 0.65, 'texto' => 'Para quem quer sofrer bonito.']
    ];
}

function dificuldade_valida(string $dificuldade): string
{
    return array_key_exists($dificuldade, dificuldades_jogo()) ? $dificuldade : 'normal';
}

function skins_jogo(): array
{
    return [
        'classica' => ['nome' => 'Clássica', 'texto' => 'Visual padrão', 'regra' => 'Liberada desde o início', 'pontos' => 0, 'fase' => 1, 'partidas' => 0],
        'azul' => ['nome' => 'Azul', 'texto' => 'Rastro gelado', 'regra' => 'Faça 8 pontos', 'pontos' => 8, 'fase' => 1, 'partidas' => 0],
        'dourada' => ['nome' => 'Dourada', 'texto' => 'Brilho campeão', 'regra' => 'Chegue na fase 4', 'pontos' => 0, 'fase' => 4, 'partidas' => 0],
        'neon' => ['nome' => 'Neon', 'texto' => 'Efeito arcade', 'regra' => 'Jogue 8 partidas', 'pontos' => 0, 'fase' => 1, 'partidas' => 8]
    ];
}

function skin_valida(string $skin): string
{
    return array_key_exists($skin, skins_jogo()) ? $skin : 'classica';
}


function h(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function asset_v(string $caminho): string
{
    $arquivo = dirname(__DIR__) . '/' . ltrim($caminho, '/');
    $versao = is_file($arquivo) ? filemtime($arquivo) : time();
    return $caminho . '?v=' . $versao;
}

function nivel_jogador(int $xp): array
{
    $nivel = max(1, intdiv(max(0, $xp), 100) + 1);
    $titulos = [1 => 'Iniciante', 2 => 'Treinando voo', 3 => 'Voador', 4 => 'Sobrevivente', 5 => 'Piloto', 7 => 'Mestre dos canos', 10 => 'Lenda'];
    $titulo = 'Lenda';
    foreach ($titulos as $min => $nome) {
        if ($nivel >= $min) $titulo = $nome;
    }
    return [
        'xp' => $xp,
        'nivel' => $nivel,
        'titulo' => $titulo,
        'atual' => $xp % 100,
        'proximo' => 100
    ];
}

function missoes_do_dia(): array
{
    $dia = (int) date('z');
    $opcoes = [
        ['codigo' => 'dez_pontos', 'titulo' => 'Faça 10 pontos', 'descricao' => 'Marque pelo menos 10 pontos em uma partida.', 'tipo' => 'pontos', 'meta' => 10],
        ['codigo' => 'fase_tres', 'titulo' => 'Chegue na fase 3', 'descricao' => 'Alcance a terceira fase.', 'tipo' => 'fase', 'meta' => 3],
        ['codigo' => 'trinta_segundos', 'titulo' => 'Sobreviva 30s', 'descricao' => 'Fique pelo menos 30 segundos voando.', 'tipo' => 'tempo', 'meta' => 30],
        ['codigo' => 'modo_dificil', 'titulo' => 'Jogue no difícil', 'descricao' => 'Faça uma partida na dificuldade difícil ou insano.', 'tipo' => 'dificuldade', 'meta' => 'dificil'],
        ['codigo' => 'quinze_pontos', 'titulo' => 'Faça 15 pontos', 'descricao' => 'Marque pelo menos 15 pontos.', 'tipo' => 'pontos', 'meta' => 15]
    ];

    return [$opcoes[$dia % count($opcoes)], $opcoes[($dia + 2) % count($opcoes)]];
}

function estatisticas_gerais(): array
{
    try {
        $pdo = pdo();
        $resumo = $pdo->query('SELECT COUNT(*) total, COALESCE(AVG(pontos), 0) media, COALESCE(MAX(pontos), 0) maior, COALESCE(MAX(fase), 1) fase FROM partidas')->fetch();
        $jogadores = $pdo->query('SELECT COUNT(*) total FROM jogadores')->fetch();
        $campeao = $pdo->query('SELECT j.nome, p.pontos FROM pontuacoes p JOIN jogadores j ON j.id = p.jogador_id ORDER BY p.pontos DESC, p.atualizado_em ASC LIMIT 1')->fetch();
        $dif = $pdo->query('SELECT dificuldade, COUNT(*) total FROM partidas GROUP BY dificuldade ORDER BY total DESC LIMIT 1')->fetch();

        return [
            'partidas' => (int) ($resumo['total'] ?? 0),
            'jogadores' => (int) ($jogadores['total'] ?? 0),
            'media' => round((float) ($resumo['media'] ?? 0), 1),
            'maior' => (int) ($resumo['maior'] ?? 0),
            'fase' => (int) ($resumo['fase'] ?? 1),
            'campeao' => $campeao['nome'] ?? 'Aguardando',
            'pontos_campeao' => (int) ($campeao['pontos'] ?? 0),
            'dificuldade' => $dif['dificuldade'] ?? 'normal'
        ];
    } catch (Throwable $erro) {
        return ['partidas' => 0, 'jogadores' => 0, 'media' => 0, 'maior' => 0, 'fase' => 1, 'campeao' => 'MySQL offline', 'pontos_campeao' => 0, 'dificuldade' => 'normal'];
    }
}

function usuario_limpo(string $usuario): string
{
    $usuario = mb_strtolower(limpar_texto($usuario), 'UTF-8');
    return preg_replace('/[^a-z0-9_.-]/', '', $usuario) ?? '';
}

function validar_usuario_senha(string $usuario, string $senha): ?string
{
    if (mb_strlen($usuario, 'UTF-8') < 3) return 'O usuário precisa ter pelo menos 3 caracteres.';
    if (mb_strlen($usuario, 'UTF-8') > 24) return 'O usuário pode ter no máximo 24 caracteres.';
    if (!preg_match('/^[a-z0-9_.-]+$/', $usuario)) return 'Use apenas letras, números, ponto, hífen ou underline.';
    if (strlen($senha) < 4) return 'A senha precisa ter pelo menos 4 caracteres.';
    return null;
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
    $stmt->execute([$usuario, $usuario, password_hash($senha, PASSWORD_DEFAULT)]);
    return buscar_jogador_por_id((int) $pdo->lastInsertId());
}

function login_jogador(string $usuario, string $senha): array
{
    $usuario = usuario_limpo($usuario);
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT * FROM jogadores WHERE nome_chave = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $jogador = $stmt->fetch();
    if (!$jogador || empty($jogador['senha_hash']) || !password_verify($senha, $jogador['senha_hash'])) {
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

function buscar_ou_criar_jogador(string $nome): array
{
    $nome = usuario_limpo($nome);
    if (mb_strlen($nome, 'UTF-8') < 3) throw new InvalidArgumentException('Informe um usuário válido.');
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT * FROM jogadores WHERE nome_chave = ? LIMIT 1');
    $stmt->execute([$nome]);
    $jogador = $stmt->fetch();
    if ($jogador) return $jogador;
    $stmt = $pdo->prepare('INSERT INTO jogadores (nome, nome_chave) VALUES (?, ?)');
    $stmt->execute([$nome, $nome]);
    return buscar_jogador_por_id((int) $pdo->lastInsertId());
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
    return [
        ['codigo' => 'primeiro_voo', 'titulo' => 'Primeiro voo', 'descricao' => 'Jogou a primeira partida'],
        ['codigo' => 'fase_3', 'titulo' => 'Pegou ritmo', 'descricao' => 'Chegou na fase 3'],
        ['codigo' => 'fase_5', 'titulo' => 'Piloto avançado', 'descricao' => 'Chegou na fase 5'],
        ['codigo' => 'dez_pontos', 'titulo' => 'Dezena feita', 'descricao' => 'Fez pelo menos 10 pontos'],
        ['codigo' => 'vinte_pontos', 'titulo' => 'Lenda dos canos', 'descricao' => 'Fez pelo menos 20 pontos'],
        ['codigo' => 'um_minuto', 'titulo' => 'Fôlego de sobra', 'descricao' => 'Sobreviveu por 60 segundos'],
        ['codigo' => 'modo_insano', 'titulo' => 'Coragem pura', 'descricao' => 'Jogou no modo insano'],
        ['codigo' => 'podio', 'titulo' => 'Entrou no pódio', 'descricao' => 'Ficou entre os três melhores'],
        ['codigo' => 'skin_azul', 'titulo' => 'Skin Azul liberada', 'descricao' => 'Fez 8 pontos e liberou uma nova skin'],
        ['codigo' => 'skin_dourada', 'titulo' => 'Skin Dourada liberada', 'descricao' => 'Chegou na fase 4'],
        ['codigo' => 'skin_neon', 'titulo' => 'Skin Neon liberada', 'descricao' => 'Jogou partidas suficientes para liberar o visual neon']
    ];
}

function liberar_conquistas(int $jogadorId, int $pontos, int $fase, float $duracao, string $dificuldade, ?int $posicao): array
{
    $lista = [
        ['primeiro_voo', 'Primeiro voo', 'Jogou a primeira partida', true],
        ['fase_3', 'Pegou ritmo', 'Chegou na fase 3', $fase >= 3],
        ['fase_5', 'Piloto avançado', 'Chegou na fase 5', $fase >= 5],
        ['dez_pontos', 'Dezena feita', 'Fez pelo menos 10 pontos', $pontos >= 10],
        ['vinte_pontos', 'Lenda dos canos', 'Fez pelo menos 20 pontos', $pontos >= 20],
        ['um_minuto', 'Fôlego de sobra', 'Sobreviveu por 60 segundos', $duracao >= 60],
        ['modo_insano', 'Coragem pura', 'Jogou no modo insano', $dificuldade === 'insano'],
        ['podio', 'Entrou no pódio', 'Ficou entre os três melhores', $posicao !== null && $posicao <= 3],
        ['skin_azul', 'Skin Azul liberada', 'Fez 8 pontos e liberou uma nova skin', $pontos >= 8],
        ['skin_dourada', 'Skin Dourada liberada', 'Chegou na fase 4', $fase >= 4],
        ['skin_neon', 'Skin Neon liberada', 'Jogou partidas suficientes para liberar o visual neon', perfil_jogador($jogadorId)['partidas'] >= 8]
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

function data_br(?string $data): string
{
    return $data ? date('d/m/Y H:i', strtotime($data)) : '-';
}
