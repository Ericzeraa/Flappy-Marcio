<?php

use App\Dados\JogadorDados;
use App\Dados\RankingDados;
use App\Regras\JogadorRegras;

class Jogador
{
    private JogadorRegras $servico;

    public function __construct()
    {
        $pdo = pdo();
        $this->servico = new JogadorRegras(new JogadorDados($pdo), new RankingDados($pdo));
    }

    public function criar(string $usuario, string $senha): array
    {
        return $this->servico->criar($usuario, $senha);
    }

    public function entrar(string $usuario, string $senha): array
    {
        return $this->servico->entrar($usuario, $senha);
    }

    public function buscarPorId(int $id): array
    {
        return $this->servico->buscarPorId($id);
    }

    public function perfil(int $id): array
    {
        return $this->servico->perfil($id);
    }
}
