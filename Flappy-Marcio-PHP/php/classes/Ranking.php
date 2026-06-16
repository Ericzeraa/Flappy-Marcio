<?php

use App\Dados\JogadorDados;
use App\Dados\RankingDados;
use App\Regras\Gamificacao;
use App\Regras\JogadorRegras;
use App\Regras\RankingRegras;
use App\Jogo\Partida;

class Ranking
{
    private RankingRegras $servico;

    public function __construct()
    {
        $pdo = pdo();
        $ranking = new RankingDados($pdo);
        $jogadores = new JogadorDados($pdo);
        $jogadorService = new JogadorRegras($jogadores, $ranking);
        $gamificacao = new Gamificacao($ranking, $jogadorService);
        $this->servico = new RankingRegras($ranking, $jogadores, $gamificacao);
    }

    public function listar(string $dificuldade = 'geral', string $periodo = 'geral', int $limite = 10): array
    {
        return $this->servico->listar($dificuldade, $periodo, $limite);
    }

    public function salvarPartida(int $jogadorId, int $pontos, int $fase, float $duracao, float $velocidade, string $dificuldade, string $skin): array
    {
        return $this->servico->salvarPartida($jogadorId, $pontos, $fase, $duracao, $velocidade, $dificuldade, $skin);
    }

    public function salvar(Partida $partida): array
    {
        return $this->servico->salvar($partida);
    }
}
