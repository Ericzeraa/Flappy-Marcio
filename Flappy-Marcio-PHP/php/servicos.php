<?php


namespace App\Regras;

use App\Base\Auth;
use App\Base\Sanitizador;
use App\Base\Validador;
use App\Dados\AdminDados;
use App\Dados\ConfiguracaoDados;
use App\Dados\JogadorDados;
use PDO;

class AdminRegras
{
    private const LIMITES_CONFIG = [
        'velocidade_inicial' => [1, 10],
        'aumento_fase' => [0, 2],
        'velocidade_maxima' => [1, 15],
        'pontos_por_fase' => [1, 30],
        'abertura_inicial' => [80, 400],
        'abertura_minima' => [60, 300],
        'gravidade' => [0.1, 2],
        'pulo' => [-20, -1],
    ];

    public function __construct(private AdminDados $admin, private ConfiguracaoDados $config, private JogadorDados $jogadores, private ?PDO $pdo = null) {}

    public function autenticar(string $usuario, string $senha): bool
    {
        $usuario = Sanitizador::usuario($usuario);
        $hash = $this->admin->senhaHash($usuario);
        return Auth::conferirSenha($senha, $hash);
    }

    public function salvarConfiguracoes(array $dados): void
    {
        foreach ($this->config->chavesPermitidas() as $chave) {
            if (!isset($dados[$chave], self::LIMITES_CONFIG[$chave])) {
                continue;
            }
            [$min, $max] = self::LIMITES_CONFIG[$chave];
            $valor = Validador::numeroConfig((string) $dados[$chave], $min, $max);
            $this->config->salvar($chave, $valor);
        }
    }

    public function limparRanking(): void
    {
        $this->admin->limparDadosDoJogo();
    }

    public function excluirJogador(int $id): void
    {
        $this->jogadores->excluir($id);
    }

    public function dadosPainel(): array
    {
        return [
            'ranking' => $this->admin->rankingCompleto(),
            'partidas' => $this->admin->ultimasPartidas(),
            'conquistas' => $this->admin->ultimasConquistas(),
            'missoes' => $this->admin->ultimasMissoes(),
            'porDificuldade' => $this->admin->desempenhoPorDificuldade(),
            'indicadoresTecnicos' => $this->pdo ? (new RelatorioProjeto($this->pdo, $this->admin))->indicadores() : [],
        ];
    }
}

namespace App\Regras;

use App\Jogo\CatalogoJogo;
use App\Dados\RankingDados;

class Gamificacao
{
    public function __construct(private RankingDados $ranking, private JogadorRegras $jogadores) {}

    public function liberarConquistas(int $jogadorId, int $pontos, int $fase, float $duracao, string $dificuldade, ?int $posicao): array
    {
        $perfil = $this->jogadores->perfil($jogadorId);
        $regras = [
            ['primeiro_voo', 'Primeiro voo', 'Jogou pela primeira vez', true],
            ['fase_3', 'Pegou ritmo', 'Chegou na fase 3', $fase >= 3],
            ['fase_5', 'Pegou prática', 'Chegou na fase 5', $fase >= 5],
            ['dez_pontos', 'Fez 10 pontos', 'Fez pelo menos 10 pontos', $pontos >= 10],
            ['vinte_pontos', 'Mandou bem nos canos', 'Fez pelo menos 20 pontos', $pontos >= 20],
            ['um_minuto', 'Aguentou bem', 'Sobreviveu por 60 segundos', $duracao >= 60],
            ['modo_insano', 'Foi no insano', 'Jogou no modo insano', $dificuldade === 'insano'],
            ['podio', 'Pegou pódio', 'Ficou no top 3', $posicao !== null && $posicao <= 3],
            ['skin_azul', 'Azul liberada', 'Fez 8 pontos e liberou uma nova skin', $pontos >= 8],
            ['skin_dourada', 'Dourada liberada', 'Chegou na fase 4', $fase >= 4],
            ['skin_neon', 'Neon liberada', 'Jogou o suficiente para liberar o neon', $perfil['partidas'] >= 8],
        ];

        $novas = [];
        foreach ($regras as $regra) {
            if (!$regra[3]) {
                continue;
            }
            if ($this->ranking->inserirConquista($jogadorId, $regra[0], $regra[1], $regra[2])) {
                $novas[] = ['codigo' => $regra[0], 'titulo' => $regra[1], 'descricao' => $regra[2]];
            }
        }
        return $novas;
    }

    public function concluirMissoes(int $jogadorId, int $pontos, int $fase, float $duracao, string $dificuldade): array
    {
        $novas = [];
        $hoje = date('Y-m-d');
        foreach (CatalogoJogo::missoesDoDia() as $missao) {
            $ok = false;
            if ($missao['tipo'] === 'pontos') $ok = $pontos >= (int) $missao['meta'];
            if ($missao['tipo'] === 'fase') $ok = $fase >= (int) $missao['meta'];
            if ($missao['tipo'] === 'tempo') $ok = $duracao >= (float) $missao['meta'];
            if ($missao['tipo'] === 'dificuldade') $ok = in_array($dificuldade, ['dificil', 'insano'], true);

            if ($ok && $this->ranking->inserirMissao($jogadorId, $missao['codigo'], $missao['titulo'], $hoje)) {
                $novas[] = ['codigo' => $missao['codigo'], 'titulo' => $missao['titulo']];
            }
        }
        return $novas;
    }
}

namespace App\Regras;

use App\Jogo\Partida;
use DateTimeImmutable;
use InvalidArgumentException;

class ConferenciaPartida
{
    public function validar(Partida $partida, array $token, array $config): void
    {
        $this->validarRelogioDaPartida($partida, $token);
        $this->validarRitmoDaPontuacao($partida);
        $this->validarFase($partida, $config);
    }

    private function validarRelogioDaPartida(Partida $partida, array $token): void
    {
        $inicio = new DateTimeImmutable((string) $token['criado_em']);
        $agora = new DateTimeImmutable();
        $tempoReal = max(0, $agora->getTimestamp() - $inicio->getTimestamp());

        if ($partida->duracao > $tempoReal + 12) {
            throw new InvalidArgumentException('O tempo da partida não confere com o início registrado.');
        }
    }

    private function validarRitmoDaPontuacao(Partida $partida): void
    {
        if ($partida->pontos === 0) {
            return;
        }

        if ($partida->duracao < 0.4) {
            throw new InvalidArgumentException('A partida terminou rápido demais para a pontuação enviada.');
        }

        $pontosPorSegundo = $partida->pontos / max(1, $partida->duracao);
        if ($pontosPorSegundo > 3.8) {
            throw new InvalidArgumentException('A pontuação enviada passou do ritmo esperado do jogo.');
        }
    }

    private function validarFase(Partida $partida, array $config): void
    {
        $pontosPorFase = max(1, (int) round((float) ($config['pontos_por_fase'] ?? 6)));
        $faseEsperada = intdiv($partida->pontos, $pontosPorFase) + 1;

        if ($partida->fase > $faseEsperada + 1) {
            throw new InvalidArgumentException('A fase informada não acompanha a pontuação da partida.');
        }
    }
}

namespace App\Regras;

use App\Base\Auth;
use App\Base\Sanitizador;
use App\Base\Validador;
use App\Jogo\Nivel;
use App\Dados\JogadorDados;
use App\Dados\RankingDados;
use InvalidArgumentException;
use RuntimeException;

class JogadorRegras
{
    public function __construct(private JogadorDados $jogadores, private RankingDados $ranking) {}

    public function criar(string $usuario, string $senha): array
    {
        $usuario = Sanitizador::usuario($usuario);
        Validador::usuarioSenha($usuario, $senha);

        if ($this->jogadores->existeUsuario($usuario)) {
            throw new InvalidArgumentException('Esse usuário já existe.');
        }

        return $this->jogadores->criar($usuario, Auth::gerarSenha($senha));
    }

    public function entrar(string $usuario, string $senha): array
    {
        $usuario = Sanitizador::usuario($usuario);
        $jogador = $this->jogadores->buscarPorUsuario($usuario);

        if (!$jogador || !Auth::conferirSenha($senha, $jogador['senha_hash'] ?? null)) {
            throw new InvalidArgumentException('Usuário ou senha inválidos.');
        }

        return $jogador;
    }

    public function buscarPorId(int $id): array
    {
        $jogador = $this->jogadores->buscarPorId($id);
        if (!$jogador) {
            throw new RuntimeException('Jogador não encontrado.');
        }
        return $jogador;
    }

    public function perfil(int $jogadorId): array
    {
        $dados = $this->ranking->perfilBruto($jogadorId);
        $conquistas = $this->ranking->totalConquistas($jogadorId);
        $missoesHoje = $this->ranking->missoesHoje($jogadorId);

        $partidas = (int) ($dados['partidas'] ?? 0);
        $melhor = (int) ($dados['melhor'] ?? 0);
        $fase = (int) ($dados['fase'] ?? 1);
        $pontosTotal = (int) ($dados['pontos_total'] ?? 0);
        $xp = ($partidas * 12) + ($pontosTotal * 4) + ($fase * 15) + ($conquistas * 30) + ($missoesHoje * 10);
        $nivel = Nivel::calcular($xp);

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
            'xpProximo' => $nivel['proximo'],
        ];
    }
}

namespace App\Regras;

use PDO;
use Throwable;

class Metricas
{
    public function __construct(private PDO $pdo) {}

    public function gerais(): array
    {
        try {
            $resumo = $this->pdo->query('SELECT COUNT(*) total, COALESCE(AVG(pontos), 0) media, COALESCE(AVG(duracao_seg), 0) tempo_medio, COALESCE(MAX(pontos), 0) maior, COALESCE(MAX(fase), 1) fase FROM partidas')->fetch();
            $jogadores = $this->pdo->query('SELECT COUNT(*) total FROM jogadores')->fetch();
            $campeao = $this->pdo->query('SELECT j.nome, p.pontos FROM pontuacoes p JOIN jogadores j ON j.id = p.jogador_id ORDER BY p.pontos DESC, p.atualizado_em ASC LIMIT 1')->fetch();
            $dif = $this->pdo->query('SELECT dificuldade, COUNT(*) total FROM partidas GROUP BY dificuldade ORDER BY total DESC LIMIT 1')->fetch();
            $hoje = $this->pdo->query('SELECT COUNT(*) partidas, COALESCE(MAX(pontos), 0) maior FROM partidas WHERE DATE(criado_em) = CURDATE()')->fetch();
            $ativos = $this->pdo->query('SELECT COUNT(DISTINCT jogador_id) total FROM partidas WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetch();
            $maisAtivo = $this->pdo->query('SELECT j.nome, COUNT(*) total FROM partidas p JOIN jogadores j ON j.id = p.jogador_id GROUP BY j.id, j.nome ORDER BY total DESC, j.nome ASC LIMIT 1')->fetch();

            return [
                'partidas' => (int) ($resumo['total'] ?? 0),
                'jogadores' => (int) ($jogadores['total'] ?? 0),
                'media' => round((float) ($resumo['media'] ?? 0), 1),
                'maior' => (int) ($resumo['maior'] ?? 0),
                'fase' => (int) ($resumo['fase'] ?? 1),
                'campeao' => $campeao['nome'] ?? 'Aguardando',
                'pontos_campeao' => (int) ($campeao['pontos'] ?? 0),
                'dificuldade' => $dif['dificuldade'] ?? 'normal',
                'partidas_hoje' => (int) ($hoje['partidas'] ?? 0),
                'maior_hoje' => (int) ($hoje['maior'] ?? 0),
                'jogadores_ativos' => (int) ($ativos['total'] ?? 0),
                'mais_ativo' => $maisAtivo['nome'] ?? 'Aguardando',
                'tempo_medio' => round((float) ($resumo['tempo_medio'] ?? 0), 1),
            ];
        } catch (Throwable) {
            return [
                'partidas' => 0,
                'jogadores' => 0,
                'media' => 0,
                'maior' => 0,
                'fase' => 1,
                'campeao' => 'MySQL offline',
                'pontos_campeao' => 0,
                'dificuldade' => 'normal',
                'partidas_hoje' => 0,
                'maior_hoje' => 0,
                'jogadores_ativos' => 0,
                'mais_ativo' => 'Aguardando',
                'tempo_medio' => 0,
            ];
        }
    }
}

namespace App\Regras;

use App\Jogo\CatalogoJogo;
use App\Dados\PartidaTokenDados;
use InvalidArgumentException;

class PartidaToken
{
    public function __construct(private PartidaTokenDados $tokens) {}

    public function iniciar(int $jogadorId, string $dificuldade, string $skin): array
    {
        $dificuldade = CatalogoJogo::dificuldadeValida($dificuldade);
        $skin = CatalogoJogo::skinValida($skin);
        $token = bin2hex(random_bytes(24));
        $this->tokens->criar($jogadorId, $token, $dificuldade, $skin);

        return [
            'token' => $token,
            'dificuldade' => $dificuldade,
            'skin' => $skin,
            'expiraEmMinutos' => 30,
        ];
    }

    public function validarUso(int $jogadorId, ?string $token, string $dificuldade, string $skin): array
    {
        $token = trim((string) $token);
        if ($token === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) {
            throw new InvalidArgumentException('Token da partida inválido. Reinicie o jogo e tente novamente.');
        }

        $registro = $this->tokens->buscarValido($jogadorId, $token);
        if (!$registro) {
            throw new InvalidArgumentException('Essa partida expirou ou já foi registrada.');
        }

        if ($registro['dificuldade'] !== CatalogoJogo::dificuldadeValida($dificuldade) || $registro['skin'] !== CatalogoJogo::skinValida($skin)) {
            throw new InvalidArgumentException('Os dados da partida não conferem com o início registrado.');
        }

        return $registro;
    }

    public function consumir(array $registro): void
    {
        $this->tokens->marcarUsado((int) $registro['id']);
    }
}

namespace App\Regras;

use App\Jogo\CatalogoJogo;
use App\Jogo\Partida;
use App\Dados\JogadorDados;
use App\Dados\RankingDados;

class RankingRegras
{
    public function __construct(private RankingDados $ranking, private JogadorDados $jogadores, private Gamificacao $gamificacao) {}

    public function listar(string $dificuldade = 'geral', string $periodo = 'geral', int $limite = 10): array
    {
        $dificuldade = $dificuldade === 'geral' ? 'geral' : CatalogoJogo::dificuldadeValida($dificuldade);
        $periodo = in_array($periodo, ['geral', 'hoje', 'semana'], true) ? $periodo : 'geral';
        return $this->ranking->listar($dificuldade, $periodo, $limite);
    }

    public function salvarPartida(int $jogadorId, int $pontos, int $fase, float $duracao, float $velocidade, string $dificuldade, string $skin): array
    {
        return $this->salvar(Partida::criar($jogadorId, $pontos, $fase, $duracao, $velocidade, $dificuldade, $skin));
    }

    public function salvar(Partida $partida): array
    {
        return $this->ranking->transacao(function () use ($partida): array {
            $this->jogadores->atualizarSkin($partida->jogadorId, $partida->skin);
            $this->ranking->registrarPartida($partida->jogadorId, $partida->pontos, $partida->fase, $partida->duracao, $partida->velocidade, $partida->dificuldade, $partida->skin);

            $atual = $this->ranking->recordeDaDificuldade($partida->jogadorId, $partida->dificuldade);
            $melhorou = !$atual || $partida->pontos > (int) $atual['pontos'];
            if ($melhorou) {
                $this->ranking->salvarRecorde($partida->jogadorId, $partida->dificuldade, $partida->pontos, $partida->fase, $partida->duracao, $partida->velocidade);
            }

            $posicao = $this->ranking->posicao($partida->jogadorId);
            $novas = $this->gamificacao->liberarConquistas($partida->jogadorId, $partida->pontos, $partida->fase, $partida->duracao, $partida->dificuldade, $posicao);
            $missoes = $this->gamificacao->concluirMissoes($partida->jogadorId, $partida->pontos, $partida->fase, $partida->duracao, $partida->dificuldade);
            $recorde = $this->ranking->melhorRecorde($partida->jogadorId);

            return [
                'recorde' => (int) $recorde['pontos'],
                'fase' => (int) $recorde['fase'],
                'melhorou' => $melhorou,
                'posicao' => $posicao,
                'conquistas' => $novas,
                'missoes' => $missoes,
            ];
        });
    }
}

namespace App\Regras;

use App\Dados\AdminDados;
use PDO;

class RelatorioProjeto
{
    public function __construct(private PDO $pdo, private AdminDados $admin) {}

    public function indicadores(): array
    {
        $ranking = $this->admin->rankingCompleto();
        $partidas = $this->admin->ultimasPartidas();
        $porDificuldade = $this->admin->desempenhoPorDificuldade();

        return [
            'totalRanking' => count($ranking),
            'ultimasPartidas' => count($partidas),
            'modosUsados' => count($porDificuldade),
            'mediaTempo' => $this->mediaTempo(),
            'aproveitamentoMissoesHoje' => $this->missoesHoje(),
        ];
    }

    private function mediaTempo(): float
    {
        $valor = $this->pdo->query('SELECT COALESCE(AVG(duracao_seg), 0) FROM partidas')->fetchColumn();
        return round((float) $valor, 1);
    }

    private function missoesHoje(): int
    {
        $valor = $this->pdo->query('SELECT COUNT(*) FROM missoes_concluidas WHERE data_ref = CURDATE()')->fetchColumn();
        return (int) $valor;
    }
}
