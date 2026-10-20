<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Sessao;
use App\Services\UsuarioService;
use InvalidArgumentException;
use Throwable;

final class AuthController
{
    public function __construct(
        private ?UsuarioService $usuarioService = null
    ) {
        $this->usuarioService ??= new UsuarioService();
    }

    /**
     * POST /api/login
     */
    
    public function login(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $dados = $this->lerJson();

            $email = $dados['email'] ?? '';
            $senha = $dados['senha'] ?? '';

            if (!is_string($email) || !is_string($senha)) {
                $this->responder(422, [
                    'erro' => 'Informe um e-mail e uma senha válidos.',
                ]);
                return;
            }

            if (trim($email) === '' || trim($senha) === '') {
                $this->responder(422, [
                    'erro' => 'Informe um e-mail e uma senha.',
                ]);
                return;
            }

            $usuario = $this->usuarioService->autenticar($email, $senha);

            Sessao::login($usuario);

            $this->responder(200, [
                'sucesso' => true,
                'mensagem' => 'Login realizado com sucesso.',
                'usuario' => Sessao::usuarioAtual(),
            ]);
        } catch (\UnexpectedValueException $e) {
            $this->responder(400, [
                'erro' => 'O corpo da requisição contém JSON inválido ou está vazio.',
            ]);
        } catch (InvalidArgumentException $e) {
            $this->responder(401, [
                'erro' => 'E-mail ou senha inválidos.',
            ]);
        } catch (Throwable $e) {
            error_log('Falha no login do GlicoLife: ' . $e->getMessage());

            $this->responder(500, [
                'erro' => 'Não foi possível realizar o login.',
            ]);
        }
    }


    /**
     * POST /api/logout
     */
    public function logout(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            Sessao::logout();

            $this->responder(200, [
                'sucesso' => true,
                'mensagem' => 'Sessão encerrada.',
            ]);
        } catch (Throwable $e) {
            error_log(
                'Falha no logout do GlicoLife: ' . $e->getMessage()
            );

            $this->responder(500, [
                'erro' => 'Não foi possível encerrar a sessão.',
            ]);
        }
    }

    /**
     * GET /api/me
     */
    public function me(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $usuario = Sessao::usuarioAtual();

        if ($usuario === null) {
            $this->responder(401, [
                'erro' => 'Autenticação necessária.',
            ]);
            return;
        }

        $this->responder(200, [
            'usuario' => $usuario,
        ]);
    }

    
    private function lerJson(): array
    {
        $conteudo = file_get_contents('php://input');

        if ($conteudo === false || trim($conteudo) === '') {
            throw new \UnexpectedValueException(
                'O corpo da requisição está vazio.'
            );
        }

        $dados = json_decode($conteudo, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dados)) {
            throw new \UnexpectedValueException(
                'O corpo da requisição não contém JSON válido.'
            );
        }

        return $dados;
    }


    private function responder(int $status, array $dados): void
    {
        http_response_code($status);

        echo json_encode(
            $dados,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
