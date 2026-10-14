<?php

declare(strict_types=1);

namespace App\Core;

final class Sessao
{
    private function __construct()
    {
    }

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            throw new \RuntimeException(
                'Não é possível iniciar a sessão: os cabeçalhos já foram enviados.'
            );
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new \RuntimeException(
                'Não foi possível iniciar a sessão.'
            );
        }
    }

    public static function login(array $usuario): void
    {
        if (
            !isset($usuario['id'], $usuario['nome'], $usuario['email'])
            || (int) $usuario['id'] <= 0
        ) {
            throw new \InvalidArgumentException(
                'Dados de usuário inválidos para a sessão.'
            );
        }

        self::iniciar();

        // Impede a reutilização do identificador anterior.
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id' => (int) $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'role' => $usuario['role'] ?? 'usuario',
        ];
    }

    public static function usuarioAtual(): ?array
    {
        self::iniciar();

        $usuario = $_SESSION['usuario'] ?? null;

        return is_array($usuario) ? $usuario : null;
    }

    public static function autenticado(): bool
    {
        return self::usuarioAtual() !== null;
    }

    public static function logout(): void
    {
        self::iniciar();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
