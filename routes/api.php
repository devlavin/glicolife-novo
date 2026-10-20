<?php

declare(strict_types=1);

use App\Controllers\AuthController;

$auth = new AuthController();

return [
    'GET /api/health' => [
        'handler' => static function (): void {
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'API GlicoLife funcionando!',
            ], JSON_UNESCAPED_UNICODE);
        },
        'auth' => false,
    ],

    'POST /api/login' => [
        'handler' => static fn() => $auth->login(),
        'auth' => false,
    ],

    'POST /api/logout' => [
        'handler' => static fn() => $auth->logout(),
        'auth' => true,
    ],

    'GET /api/me' => [
        'handler' => static fn() => $auth->me(),
        'auth' => true,
    ],
];
