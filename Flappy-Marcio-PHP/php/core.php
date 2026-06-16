<?php


namespace App\Base;

class Auth
{
    public static function gerarSenha(string $senha): string
    {
        return password_hash($senha, PASSWORD_DEFAULT);
    }

    public static function conferirSenha(string $senha, ?string $hash): bool
    {
        return $hash !== null && $hash !== '' && password_verify($senha, $hash);
    }

    public static function iniciarJogador(int $id): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['jogador_id'] = $id;
    }

    public static function sairJogador(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['jogador_id']);
    }
}

namespace App\Base;

class Cabecalhos
{
    public static function segurancaBasica(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

namespace App\Base;

use PDO;
use PDOException;
use RuntimeException;

class Conexao
{
    private static ?PDO $pdo = null;

    public static function pdo(callable $prepararBanco): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        try {
            self::$pdo = self::novaConexao(true);
            $prepararBanco(self::$pdo);
            return self::$pdo;
        } catch (PDOException $erro) {
            try {
                $base = self::novaConexao(false);
                $base->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                self::$pdo = self::novaConexao(true);
                $prepararBanco(self::$pdo);
                return self::$pdo;
            } catch (PDOException $novoErro) {
                throw new RuntimeException('MySQL indisponível. Confira Apache e MySQL no XAMPP.');
            }
        }
    }

    private static function novaConexao(bool $comBanco): PDO
    {
        $dsn = 'mysql:host=' . DB_HOST . ($comBanco ? ';dbname=' . DB_NAME : '') . ';charset=' . DB_CHARSET;
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

namespace App\Base;

class Csrf
{
    public static function token(): string
    {
        self::sessao();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validar(?string $token): bool
    {
        self::sessao();
        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . Sanitizador::html(self::token()) . '">';
    }

    private static function sessao(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

namespace App\Base;

class RespostaJson
{
    public static function enviar(array $dados, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

namespace App\Base;

class Sanitizador
{
    public static function texto(?string $valor): string
    {
        $valor = trim((string) $valor);
        return preg_replace('/\s+/', ' ', $valor) ?? '';
    }

    public static function usuario(string $usuario): string
    {
        $usuario = mb_strtolower(self::texto($usuario), 'UTF-8');
        return preg_replace('/[^a-z0-9_.-]/', '', $usuario) ?? '';
    }

    public static function html(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

namespace App\Base;

use InvalidArgumentException;

class Validador
{
    public static function usuarioSenha(string $usuario, string $senha): void
    {
        if (mb_strlen($usuario, 'UTF-8') < 3) {
            throw new InvalidArgumentException('O usuário precisa ter pelo menos 3 caracteres.');
        }
        if (mb_strlen($usuario, 'UTF-8') > 24) {
            throw new InvalidArgumentException('O usuário pode ter no máximo 24 caracteres.');
        }
        if (!preg_match('/^[a-z0-9_.-]+$/', $usuario)) {
            throw new InvalidArgumentException('Use apenas letras, números, ponto, hífen ou underline.');
        }
        if (strlen($senha) < 4) {
            throw new InvalidArgumentException('A senha precisa ter pelo menos 4 caracteres.');
        }
        if (strlen($senha) > 40) {
            throw new InvalidArgumentException('A senha pode ter no máximo 40 caracteres.');
        }
        if (in_array($usuario, ['admin', 'administrador', 'root'], true)) {
            throw new InvalidArgumentException('Escolha outro nome de usuário.');
        }
    }

    public static function partida(int $pontos, int $fase, float $duracao, float $velocidade): void
    {
        if ($pontos < 0 || $fase < 1 || $duracao < 0 || $velocidade < 1) {
            throw new InvalidArgumentException('Dados da partida inválidos.');
        }
        if ($pontos > 99999 || $fase > 999 || $duracao > 7200 || $velocidade > 100) {
            throw new InvalidArgumentException('Dados da partida fora do limite permitido.');
        }
    }

    public static function numeroConfig(string $valor, float $min, float $max): float
    {
        $valor = str_replace(',', '.', $valor);
        if (!is_numeric($valor)) {
            throw new InvalidArgumentException('Configuração numérica inválida.');
        }
        $numero = (float) $valor;
        if ($numero < $min || $numero > $max) {
            throw new InvalidArgumentException('Configuração fora da faixa segura.');
        }
        return $numero;
    }
}
