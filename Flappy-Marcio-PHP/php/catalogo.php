<?php


namespace App\Jogo;

class CatalogoJogo
{
    public static function dificuldades(): array
    {
        return [
            'facil' => ['nome' => 'Fácil', 'vel' => 0.88, 'abertura' => 28, 'max' => -0.35, 'texto' => 'Mais espaço para respirar.'],
            'normal' => ['nome' => 'Normal', 'vel' => 1.00, 'abertura' => 0, 'max' => 0, 'texto' => 'O modo de sempre, sem desculpa.'],
            'dificil' => ['nome' => 'Difícil', 'vel' => 1.12, 'abertura' => -18, 'max' => 0.35, 'texto' => 'Mais rápido e com menos folga.'],
            'insano' => ['nome' => 'Insano', 'vel' => 1.23, 'abertura' => -30, 'max' => 0.65, 'texto' => 'Para quem confia demais no próprio reflexo.'],
        ];
    }

    public static function dificuldadeValida(string $dificuldade): string
    {
        return array_key_exists($dificuldade, self::dificuldades()) ? $dificuldade : 'normal';
    }

    public static function skins(): array
    {
        return [
            'classica' => ['nome' => 'Clássica', 'texto' => 'A roupa de trabalho', 'regra' => 'Liberada desde o começo', 'pontos' => 0, 'fase' => 1, 'partidas' => 0],
            'azul' => ['nome' => 'Azul', 'texto' => 'Azul para dar moral', 'regra' => 'Faça 8 pontos', 'pontos' => 8, 'fase' => 1, 'partidas' => 0],
            'dourada' => ['nome' => 'Dourada', 'texto' => 'Dourada, porque sofrer também merece brilho', 'regra' => 'Chegue até a fase 4', 'pontos' => 0, 'fase' => 4, 'partidas' => 0],
            'neon' => ['nome' => 'Neon', 'texto' => 'Neon para aparecer no rolê', 'regra' => 'Jogue 8 partidas', 'pontos' => 0, 'fase' => 1, 'partidas' => 8],
        ];
    }

    public static function skinValida(string $skin): string
    {
        return array_key_exists($skin, self::skins()) ? $skin : 'classica';
    }

    public static function conquistas(): array
    {
        return [
            ['codigo' => 'primeiro_voo', 'titulo' => 'Primeira voada', 'descricao' => 'Salvou a primeira partida. Agora não tem mais volta.'],
            ['codigo' => 'fase_3', 'titulo' => 'Pegou o jeito', 'descricao' => 'Chegou na fase 3 sem o Márcio pedir demissão.'],
            ['codigo' => 'fase_5', 'titulo' => 'Foi longe', 'descricao' => 'Chegou na fase 5. Já dá para contar vantagem.'],
            ['codigo' => 'dez_pontos', 'titulo' => 'Dez já dá jogo', 'descricao' => 'Fez 10 pontos. O treino começou a aparecer.'],
            ['codigo' => 'vinte_pontos', 'titulo' => 'Passou raspando', 'descricao' => 'Fez 20 pontos. Alguns canos ainda estão sem entender.'],
            ['codigo' => 'um_minuto', 'titulo' => 'Segurou bem', 'descricao' => 'Ficou 60 segundos sem cair. Respeitável.'],
            ['codigo' => 'modo_insano', 'titulo' => 'Sem medo do insano', 'descricao' => 'Jogou no insano e aceitou o caos.'],
            ['codigo' => 'podio', 'titulo' => 'Entrou no pódio', 'descricao' => 'Entrou no top 3. Agora tem gente olhando torto.'],
            ['codigo' => 'skin_azul', 'titulo' => 'Azul desbloqueada', 'descricao' => 'Fez 8 pontos e liberou a skin azul.'],
            ['codigo' => 'skin_dourada', 'titulo' => 'Dourada na conta', 'descricao' => 'Chegou na fase 4 e liberou a dourada.'],
            ['codigo' => 'skin_neon', 'titulo' => 'Neon liberado', 'descricao' => 'Jogou 8 partidas e liberou o neon.'],
        ];
    }

    public static function missoesDoDia(?int $dia = null): array
    {
        $dia = $dia ?? (int) date('z');
        $opcoes = [
            ['codigo' => 'dez_pontos', 'titulo' => 'Faça 10 pontos', 'descricao' => 'Tente fazer 10 pontos sem negociar com os canos.', 'tipo' => 'pontos', 'meta' => 10],
            ['codigo' => 'fase_tres', 'titulo' => 'Chegue na fase 3', 'descricao' => 'Passe da fase 3 e finja que estava tudo sob controle.', 'tipo' => 'fase', 'meta' => 3],
            ['codigo' => 'trinta_segundos', 'titulo' => 'Aguente 30s', 'descricao' => 'Fique 30 segundos sem beijar nenhum cano.', 'tipo' => 'tempo', 'meta' => 30],
            ['codigo' => 'modo_dificil', 'titulo' => 'Jogue no difícil', 'descricao' => 'Jogue no difícil ou no insano, por sua conta e risco.', 'tipo' => 'dificuldade', 'meta' => 'dificil'],
            ['codigo' => 'quinze_pontos', 'titulo' => 'Faça 15 pontos', 'descricao' => 'Faça 15 pontos e comemore sem tirar a mão do teclado.', 'tipo' => 'pontos', 'meta' => 15],
        ];

        return [$opcoes[$dia % count($opcoes)], $opcoes[($dia + 2) % count($opcoes)]];
    }
}

namespace App\Jogo;

class Nivel
{
    public static function calcular(int $xp): array
    {
        $nivel = max(1, intdiv(max(0, $xp), 100) + 1);
        $titulos = [1 => 'Iniciante', 2 => 'Treinando voo', 3 => 'Voador', 4 => 'Sobrevivente', 5 => 'Piloto', 7 => 'Mestre dos canos', 10 => 'Lenda'];
        $titulo = 'Lenda';
        foreach ($titulos as $minimo => $nome) {
            if ($nivel >= $minimo) {
                $titulo = $nome;
            }
        }

        return ['xp' => $xp, 'nivel' => $nivel, 'titulo' => $titulo, 'atual' => $xp % 100, 'proximo' => 100];
    }
}

namespace App\Jogo;

use App\Base\Validador;
use InvalidArgumentException;

class Partida
{
    public function __construct(
        public readonly int $jogadorId,
        public readonly int $pontos,
        public readonly int $fase,
        public readonly float $duracao,
        public readonly float $velocidade,
        public readonly string $dificuldade,
        public readonly string $skin,
        public readonly ?string $token = null
    ) {
        Validador::partida($pontos, $fase, $duracao, $velocidade);
        $this->validarCoerencia();
    }

    public static function criar(
        int $jogadorId,
        int $pontos,
        int $fase,
        float $duracao,
        float $velocidade,
        string $dificuldade,
        string $skin,
        ?string $token = null
    ): self {
        return new self(
            $jogadorId,
            $pontos,
            max(1, $fase),
            max(0, $duracao),
            max(1, $velocidade),
            CatalogoJogo::dificuldadeValida($dificuldade),
            CatalogoJogo::skinValida($skin),
            $token !== null ? trim($token) : null
        );
    }

    private function validarCoerencia(): void
    {
        if ($this->pontos > 0 && $this->duracao < 0.3) {
            throw new InvalidArgumentException('A duração da partida não combina com a pontuação enviada.');
        }

        $limiteFase = max(10, $this->pontos + 5);
        if ($this->fase > $limiteFase) {
            throw new InvalidArgumentException('A fase enviada não combina com a pontuação da partida.');
        }
    }
}
