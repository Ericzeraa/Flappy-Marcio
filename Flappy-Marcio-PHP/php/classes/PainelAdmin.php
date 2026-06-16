<?php

use App\Dados\AdminDados;
use App\Dados\ConfiguracaoDados;
use App\Dados\JogadorDados;
use App\Regras\AdminRegras;

class PainelAdmin
{
    private AdminRegras $servico;

    public function __construct()
    {
        $pdo = pdo();
        $this->servico = new AdminRegras(new AdminDados($pdo), new ConfiguracaoDados($pdo), new JogadorDados($pdo), $pdo);
    }

    public function autenticar(string $usuario, string $senha): bool
    {
        return $this->servico->autenticar($usuario, $senha);
    }

    public function salvarConfiguracoes(array $dados): void
    {
        $this->servico->salvarConfiguracoes($dados);
    }

    public function limparRanking(): void
    {
        $this->servico->limparRanking();
    }

    public function excluirJogador(int $id): void
    {
        $this->servico->excluirJogador($id);
    }

    public function dadosPainel(): array
    {
        return $this->servico->dadosPainel();
    }
}
