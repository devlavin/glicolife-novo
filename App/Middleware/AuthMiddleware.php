<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Sessao;
use App\Services\UsuarioService;

final class AuthMiddleware
{
    /**
     * Confere a sessão e valida o estado atual da conta no banco.
     */
    public static function verificar(): bool
    {
        $usuarioSessao = Sessao::usuarioAtual();

        if ($usuarioSessao === null) {
            return false;
        }

        $id = (int) ($usuarioSessao['id'] ?? 0);

        if ($id <= 0) {
            Sessao::logout();
            return false;
        }

        $usuarioBanco = (new UsuarioService())->buscarPorId($id);

        if (
            $usuarioBanco === null
            || empty($usuarioBanco['ativo'])
        ) {
            Sessao::logout();
            return false;
        }

        $roleSessao = $usuarioSessao['role'] ?? 'usuario';
        $roleBanco = $usuarioBanco['role'] ?? 'usuario';

        // Se a permissão mudou, invalida a sessão antiga.
        if ($roleSessao !== $roleBanco) {
            Sessao::logout();
            return false;
        }

        return true;
    }

    /**
     * Exige uma sessão autenticada com o papel solicitado.
     */
    public static function exigirRole(string $role): bool
    {
        if (!self::verificar()) {
            return false;
        }

        $usuario = Sessao::usuarioAtual();

        return $usuario !== null
            && ($usuario['role'] ?? 'usuario') === $role;
    }
}
