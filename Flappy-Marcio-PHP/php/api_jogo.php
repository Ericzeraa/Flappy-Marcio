<?php


namespace App\Api;

use App\Base\Auth;
use App\Jogo\Partida;
use App\Dados\PartidaTokenDados;
use App\Regras\ConferenciaPartida;
use App\Regras\PartidaToken;
use InvalidArgumentException;
use Throwable;

class ApiJogo
{
    private \Jogador $jogadores;
    private \Ranking $ranking;
    private PartidaToken $partidas;
    private ConferenciaPartida $integridade;

    public function __construct()
    {
        $this->jogadores = new \Jogador();
        $this->ranking = new \Ranking();
        $this->partidas = new PartidaToken(new PartidaTokenDados(pdo()));
        $this->integridade = new ConferenciaPartida();
    }

    public function executar(): void
    {
        $acao = $_GET['acao'] ?? $_POST['acao'] ?? 'listar';

        try {
            match ($acao) {
                'sessao' => $this->sessao(),
                'registrar' => $this->registrar(),
                'login' => $this->login(),
                'sair' => $this->sair(),
                'config' => $this->config(),
                'listar' => $this->listar(),
                'historico' => $this->historico(),
                'estatisticas' => $this->estatisticas(),
                'jogador' => $this->jogador(),
                'iniciar_partida' => $this->iniciarPartida(),
                'salvar' => $this->salvar(),
                default => resposta_json(['ok' => false, 'mensagem' => 'Ação inválida.'], 400),
            };
        } catch (Throwable $erro) {
            $codigo = $erro instanceof InvalidArgumentException ? 400 : 500;
            $mensagem = $erro instanceof InvalidArgumentException ? $erro->getMessage() : 'Não foi possível processar a solicitação.';
            resposta_json(['ok' => false, 'mensagem' => $mensagem], $codigo);
        }
    }

    private function sessao(): void
    {
        $jogador = jogador_sessao();
        if (!$jogador) {
            resposta_json(['ok' => true, 'logado' => false, 'jogador' => null, 'perfil' => null, 'conquistas' => [], 'missoes' => missoes_do_dia(), 'skins' => skins_jogo()]);
        }
        $this->responderJogadorLogado($jogador);
    }

    private function registrar(): void
    {
        $jogador = $this->jogadores->criar($_POST['usuario'] ?? '', $_POST['senha'] ?? '');
        Auth::iniciarJogador((int) $jogador['id']);
        resposta_json(['ok' => true, 'mensagem' => 'Conta criada.', 'jogador' => ['id' => (int) $jogador['id'], 'nome' => $jogador['nome']], 'perfil' => perfil_jogador((int) $jogador['id'])]);
    }

    private function login(): void
    {
        $jogador = $this->jogadores->entrar($_POST['usuario'] ?? '', $_POST['senha'] ?? '');
        Auth::iniciarJogador((int) $jogador['id']);
        resposta_json(['ok' => true, 'mensagem' => 'Login realizado.', 'jogador' => ['id' => (int) $jogador['id'], 'nome' => $jogador['nome'], 'skin' => $jogador['skin']], 'perfil' => perfil_jogador((int) $jogador['id']), 'conquistas' => conquistas_jogador((int) $jogador['id']), 'posicao' => posicao_jogador((int) $jogador['id'])]);
    }

    private function sair(): void
    {
        Auth::sairJogador();
        resposta_json(['ok' => true]);
    }

    private function config(): void
    {
        resposta_json(['ok' => true, 'config' => configuracoes_jogo(), 'dificuldades' => dificuldades_jogo(), 'missoes' => missoes_do_dia(), 'estatisticas' => estatisticas_gerais(), 'skins' => skins_jogo()]);
    }

    private function listar(): void
    {
        resposta_json(['ok' => true, 'ranking' => $this->ranking->listar($_GET['dificuldade'] ?? 'geral', $_GET['periodo'] ?? 'geral', 10)]);
    }

    private function historico(): void
    {
        $stmt = pdo()->query('SELECT j.nome, p.pontos, p.fase, p.duracao_seg, p.velocidade_final, p.dificuldade, p.criado_em data
            FROM partidas p
            JOIN jogadores j ON j.id = p.jogador_id
            ORDER BY p.criado_em DESC
            LIMIT 8');
        resposta_json(['ok' => true, 'historico' => $stmt->fetchAll()]);
    }

    private function estatisticas(): void
    {
        resposta_json(['ok' => true, 'estatisticas' => estatisticas_gerais(), 'skins' => skins_jogo()]);
    }

    private function jogador(): void
    {
        $jogador = jogador_sessao();
        if (!$jogador) {
            resposta_json(['ok' => true, 'logado' => false, 'recorde' => 0, 'posicao' => null, 'conquistas' => [], 'perfil' => null, 'missoes' => missoes_do_dia(), 'skins' => skins_jogo()]);
        }
        $this->responderJogadorLogado($jogador);
    }


    private function iniciarPartida(): void
    {
        $jogador = jogador_sessao();
        if (!$jogador) {
            resposta_json(['ok' => false, 'mensagem' => 'Faça login para iniciar a partida.'], 401);
        }

        $partida = $this->partidas->iniciar(
            (int) $jogador['id'],
            $_POST['dificuldade'] ?? 'normal',
            $_POST['skin'] ?? 'classica'
        );

        resposta_json(['ok' => true, 'partida' => $partida]);
    }

    private function salvar(): void
    {
        $jogador = jogador_sessao();
        if (!$jogador) {
            resposta_json(['ok' => false, 'mensagem' => 'Faça login para salvar a partida.'], 401);
        }

        $pontos = filter_input(INPUT_POST, 'pontos', FILTER_VALIDATE_INT);
        $fase = filter_input(INPUT_POST, 'fase', FILTER_VALIDATE_INT);
        $duracao = filter_input(INPUT_POST, 'duracao', FILTER_VALIDATE_FLOAT);
        $velocidade = filter_input(INPUT_POST, 'velocidade', FILTER_VALIDATE_FLOAT);

        if ($pontos === false || $pontos === null || $pontos < 0) {
            resposta_json(['ok' => false, 'mensagem' => 'Pontuação inválida.'], 400);
        }

        $jogadorId = (int) $jogador['id'];
        $dificuldade = dificuldade_valida($_POST['dificuldade'] ?? 'normal');
        $skin = skin_valida($_POST['skin'] ?? 'classica');
        $registroToken = $this->partidas->validarUso($jogadorId, $_POST['token_partida'] ?? null, $dificuldade, $skin);

        $partida = Partida::criar(
            $jogadorId,
            (int) $pontos,
            max(1, (int) ($fase ?: 1)),
            max(0, (float) ($duracao ?: 0)),
            max(1, (float) ($velocidade ?: 1)),
            $dificuldade,
            $skin,
            $_POST['token_partida'] ?? null
        );
        $this->integridade->validar($partida, $registroToken, configuracoes_jogo());

        $resultado = $this->ranking->salvar($partida);
        $this->partidas->consumir($registroToken);

        $posicao = $resultado['posicao'];
        resposta_json([
            'ok' => true,
            'recorde' => $resultado['recorde'],
            'fase' => $resultado['fase'],
            'melhorou' => $resultado['melhorou'],
            'posicao' => $posicao,
            'podio' => $posicao !== null && $posicao <= 3,
            'conquistas' => $resultado['conquistas'],
            'missoesConcluidas' => $resultado['missoes'],
            'perfil' => perfil_jogador($jogadorId),
            'estatisticas' => estatisticas_gerais(),
            'skins' => skins_jogo(),
        ]);
    }

    private function responderJogadorLogado(array $jogador): void
    {
        $jogadorId = (int) $jogador['id'];
        resposta_json([
            'ok' => true,
            'logado' => true,
            'jogador' => ['id' => $jogadorId, 'nome' => $jogador['nome'], 'skin' => $jogador['skin']],
            'recorde' => perfil_jogador($jogadorId)['melhor'],
            'posicao' => posicao_jogador($jogadorId),
            'perfil' => perfil_jogador($jogadorId),
            'conquistas' => conquistas_jogador($jogadorId),
            'missoes' => missoes_do_dia(),
            'skins' => skins_jogo(),
        ]);
    }
}
