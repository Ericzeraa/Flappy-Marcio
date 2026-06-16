<?php


namespace App\Dados;

use PDO;

class AdminDados
{
    public function __construct(private PDO $pdo) {}

    public function senhaHash(string $usuario): ?string
    {
        $stmt = $this->pdo->prepare('SELECT senha_hash FROM administradores WHERE usuario = ? LIMIT 1');
        $stmt->execute([$usuario]);
        $admin = $stmt->fetch();
        return $admin['senha_hash'] ?? null;
    }

    public function limparDadosDoJogo(): void
    {
        $this->pdo->exec('DELETE FROM missoes_concluidas');
        $this->pdo->exec('DELETE FROM conquistas');
        $this->pdo->exec('DELETE FROM pontuacoes');
        $this->pdo->exec('DELETE FROM partidas');
        $this->pdo->exec('DELETE FROM jogadores');
    }

    public function rankingCompleto(int $limite = 30): array
    {
        return $this->pdo->query('SELECT j.id, j.nome, p.dificuldade, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.atualizado_em
            FROM pontuacoes p
            JOIN jogadores j ON j.id = p.jogador_id
            ORDER BY p.pontos DESC, p.atualizado_em ASC
            LIMIT ' . max(1, min(100, $limite)))->fetchAll();
    }

    public function ultimasPartidas(int $limite = 20): array
    {
        return $this->pdo->query('SELECT j.nome, p.dificuldade, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.criado_em
            FROM partidas p
            JOIN jogadores j ON j.id = p.jogador_id
            ORDER BY p.criado_em DESC
            LIMIT ' . max(1, min(100, $limite)))->fetchAll();
    }

    public function ultimasConquistas(int $limite = 20): array
    {
        return $this->pdo->query('SELECT j.nome, c.titulo, c.descricao, c.criado_em
            FROM conquistas c
            JOIN jogadores j ON j.id = c.jogador_id
            ORDER BY c.criado_em DESC
            LIMIT ' . max(1, min(100, $limite)))->fetchAll();
    }

    public function ultimasMissoes(int $limite = 20): array
    {
        return $this->pdo->query('SELECT j.nome, m.titulo, m.data_ref, m.criado_em
            FROM missoes_concluidas m
            JOIN jogadores j ON j.id = m.jogador_id
            ORDER BY m.criado_em DESC
            LIMIT ' . max(1, min(100, $limite)))->fetchAll();
    }

    public function desempenhoPorDificuldade(): array
    {
        return $this->pdo->query('SELECT dificuldade, COUNT(*) partidas, COALESCE(MAX(pontos),0) maior, COALESCE(AVG(pontos),0) media FROM partidas GROUP BY dificuldade ORDER BY partidas DESC')->fetchAll();
    }
}

namespace App\Dados;

use PDO;

class ConfiguracaoDados
{
    private const PADRAO = [
        'velocidade_inicial' => 3.85,
        'aumento_fase' => 0.34,
        'velocidade_maxima' => 6.40,
        'pontos_por_fase' => 6,
        'abertura_inicial' => 218,
        'abertura_minima' => 178,
        'gravidade' => 0.46,
        'pulo' => -8.25,
    ];

    public function __construct(private PDO $pdo) {}

    public function listar(): array
    {
        $config = self::PADRAO;
        foreach ($this->pdo->query('SELECT chave, valor FROM configuracoes')->fetchAll() as $linha) {
            if (array_key_exists($linha['chave'], $config)) {
                $config[$linha['chave']] = is_numeric($linha['valor']) ? (float) $linha['valor'] : $linha['valor'];
            }
        }
        return $config;
    }

    public function salvar(string $chave, float $valor): void
    {
        if (!array_key_exists($chave, self::PADRAO)) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE configuracoes SET valor = ? WHERE chave = ?');
        $stmt->execute([(string) $valor, $chave]);
    }

    public function chavesPermitidas(): array
    {
        return array_keys(self::PADRAO);
    }
}

namespace App\Dados;

use PDO;

class JogadorDados
{
    public function __construct(private PDO $pdo) {}

    public function existeUsuario(string $usuario): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM jogadores WHERE nome_chave = ? LIMIT 1');
        $stmt->execute([$usuario]);
        return (bool) $stmt->fetch();
    }

    public function criar(string $usuario, string $senhaHash): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO jogadores (nome, nome_chave, senha_hash) VALUES (?, ?, ?)');
        $stmt->execute([$usuario, $usuario, $senhaHash]);
        return $this->buscarPorId((int) $this->pdo->lastInsertId());
    }

    public function buscarPorUsuario(string $usuario): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jogadores WHERE nome_chave = ? LIMIT 1');
        $stmt->execute([$usuario]);
        return $stmt->fetch() ?: null;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jogadores WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function atualizarSkin(int $id, string $skin): void
    {
        $stmt = $this->pdo->prepare('UPDATE jogadores SET skin = ? WHERE id = ?');
        $stmt->execute([$skin, $id]);
    }

    public function excluir(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM jogadores WHERE id = ?');
        $stmt->execute([$id]);
    }
}

namespace App\Dados;

use PDO;

class PartidaTokenDados
{
    public function __construct(private PDO $pdo) {}

    public function criar(int $jogadorId, string $token, string $dificuldade, string $skin): void
    {
        $this->removerExpirados();
        $stmt = $this->pdo->prepare('INSERT INTO partidas_tokens (jogador_id, token, dificuldade, skin, criado_em) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$jogadorId, $token, $dificuldade, $skin]);
    }

    public function buscarValido(int $jogadorId, string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM partidas_tokens WHERE jogador_id = ? AND token = ? AND usado_em IS NULL AND criado_em >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1');
        $stmt->execute([$jogadorId, $token]);
        return $stmt->fetch() ?: null;
    }

    public function marcarUsado(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE partidas_tokens SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL');
        $stmt->execute([$id]);
    }

    public function removerExpirados(): void
    {
        $this->pdo->exec('DELETE FROM partidas_tokens WHERE criado_em < DATE_SUB(NOW(), INTERVAL 2 HOUR) OR usado_em < DATE_SUB(NOW(), INTERVAL 2 HOUR)');
    }
}

namespace App\Dados;

use PDO;

class RankingDados
{
    public function __construct(private PDO $pdo) {}


    public function transacao(callable $rotina): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $resultado = $rotina();
            $this->pdo->commit();
            return $resultado;
        } catch (\Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
    }

    public function listar(string $dificuldade = 'geral', string $periodo = 'geral', int $limite = 10): array
    {
        $where = [];
        $params = [];

        if ($dificuldade !== 'geral') {
            $where[] = 'p.dificuldade = ?';
            $params[] = $dificuldade;
        }
        if ($periodo === 'hoje') {
            $where[] = 'DATE(p.atualizado_em) = CURDATE()';
        } elseif ($periodo === 'semana') {
            $where[] = 'p.atualizado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        }

        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT j.nome, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.dificuldade, p.atualizado_em data
            FROM pontuacoes p
            JOIN jogadores j ON j.id = p.jogador_id
            {$sqlWhere}
            ORDER BY p.pontos DESC, p.atualizado_em ASC
            LIMIT " . max(1, min(50, $limite)));
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function registrarPartida(int $jogadorId, int $pontos, int $fase, float $duracao, float $velocidade, string $dificuldade, string $skin): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO partidas (jogador_id, pontos, fase, duracao_seg, velocidade_final, dificuldade, skin) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$jogadorId, $pontos, $fase, $duracao, $velocidade, $dificuldade, $skin]);
    }

    public function recordeDaDificuldade(int $jogadorId, string $dificuldade): ?array
    {
        $stmt = $this->pdo->prepare('SELECT pontos FROM pontuacoes WHERE jogador_id = ? AND dificuldade = ? LIMIT 1');
        $stmt->execute([$jogadorId, $dificuldade]);
        return $stmt->fetch() ?: null;
    }

    public function salvarRecorde(int $jogadorId, string $dificuldade, int $pontos, int $fase, float $duracao, float $velocidade): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO pontuacoes (jogador_id, dificuldade, pontos, fase, duracao_seg, velocidade_final, atualizado_em)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE pontos = VALUES(pontos), fase = VALUES(fase), duracao_seg = VALUES(duracao_seg), velocidade_final = VALUES(velocidade_final), atualizado_em = NOW()');
        $stmt->execute([$jogadorId, $dificuldade, $pontos, $fase, $duracao, $velocidade]);
    }

    public function melhorRecorde(int $jogadorId): array
    {
        $stmt = $this->pdo->prepare('SELECT pontos, fase FROM pontuacoes WHERE jogador_id = ? ORDER BY pontos DESC LIMIT 1');
        $stmt->execute([$jogadorId]);
        return $stmt->fetch() ?: ['pontos' => 0, 'fase' => 1];
    }

    public function posicao(int $jogadorId, string $dificuldade = 'geral'): ?int
    {
        if ($dificuldade !== 'geral') {
            $stmt = $this->pdo->prepare('SELECT jogador_id FROM pontuacoes WHERE dificuldade = ? ORDER BY pontos DESC, atualizado_em ASC');
            $stmt->execute([$dificuldade]);
        } else {
            $stmt = $this->pdo->query('SELECT jogador_id FROM pontuacoes ORDER BY pontos DESC, atualizado_em ASC');
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

    public function conquistasDoJogador(int $jogadorId): array
    {
        $stmt = $this->pdo->prepare('SELECT codigo, titulo, descricao, criado_em FROM conquistas WHERE jogador_id = ? ORDER BY criado_em DESC');
        $stmt->execute([$jogadorId]);
        return $stmt->fetchAll();
    }

    public function inserirConquista(int $jogadorId, string $codigo, string $titulo, string $descricao): bool
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO conquistas (jogador_id, codigo, titulo, descricao) VALUES (?, ?, ?, ?)');
        $stmt->execute([$jogadorId, $codigo, $titulo, $descricao]);
        return $stmt->rowCount() > 0;
    }

    public function inserirMissao(int $jogadorId, string $codigo, string $titulo, string $data): bool
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO missoes_concluidas (jogador_id, codigo, titulo, data_ref) VALUES (?, ?, ?, ?)');
        $stmt->execute([$jogadorId, $codigo, $titulo, $data]);
        return $stmt->rowCount() > 0;
    }

    public function perfilBruto(int $jogadorId): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) partidas, COALESCE(MAX(pontos),0) melhor, COALESCE(MAX(fase),1) fase, COALESCE(AVG(pontos),0) media, COALESCE(SUM(duracao_seg),0) tempo, COALESCE(SUM(pontos),0) pontos_total FROM partidas WHERE jogador_id = ?');
        $stmt->execute([$jogadorId]);
        return $stmt->fetch() ?: [];
    }

    public function totalConquistas(int $jogadorId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM conquistas WHERE jogador_id = ?');
        $stmt->execute([$jogadorId]);
        return (int) $stmt->fetchColumn();
    }

    public function missoesHoje(int $jogadorId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM missoes_concluidas WHERE jogador_id = ? AND data_ref = CURDATE()');
        $stmt->execute([$jogadorId]);
        return (int) $stmt->fetchColumn();
    }
}
