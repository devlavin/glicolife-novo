<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;

final class Router
{
    public function dispatch(array $rotas): void
    {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if (!is_string($uri)) {
            $this->responder404();
            return;
        }

        $caminho = $this->normalizarCaminho($uri);

        $chave = $metodo . ' ' . $caminho;
        $rota = $rotas[$chave] ?? null;

        if (!is_array($rota)) {
            $this->responder404();
            return;
        }

        if (($rota['auth'] ?? false) === true) {
            if (!AuthMiddleware::verificar()) {
                http_response_code(401);
                $this->responderErro(
                    'Acesso não autorizado.',
                    str_starts_with($caminho, '/api/')
                );
                return;
            }
        }

        if (isset($rota['role'])) {
            if (!AuthMiddleware::exigirRole((string) $rota['role'])) {
                http_response_code(403);
                $this->responderErro(
                    'Você não tem permissão para acessar este recurso.',
                    str_starts_with($caminho, '/api/')
                );
                return;
            }
        }

        $handler = $rota['handler'] ?? null;

        if (!is_callable($handler)) {
            http_response_code(500);
            $this->responderErro(
                'Handler da rota não configurado.',
                str_starts_with($caminho, '/api/')
            );
            return;
        }

        $handler();
    }

    private function normalizarCaminho(string $uri): string
    {
        $caminho = rawurldecode($uri);

        // Remove o diretório-base do projeto quando acessado
        // por uma URL como /glicolife_v3/public/.
        $script = str_replace(
            '\\',
            '/',
            $_SERVER['SCRIPT_NAME'] ?? ''
        );

        $base = rtrim(dirname($script), '/');

        if (
            $base !== ''
            && $base !== '.'
            && ($caminho === $base || str_starts_with($caminho, $base . '/'))
        ) {
            $caminho = substr($caminho, strlen($base));
        }

        $caminho = '/' . trim($caminho, '/');

        return $caminho === '/' ? '/' : rtrim($caminho, '/');
    }

    private function responder404(): void
    {
        http_response_code(404);
        echo 'Página não encontrada.';
    }

    private function responderErro(string $mensagem, bool $json): void
    {
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode(
                ['erro' => $mensagem],
                JSON_UNESCAPED_UNICODE
            );

            return;
        }

        echo htmlspecialchars(
            $mensagem,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
