<?php

declare(strict_types=1);

return [
    'GET /' => [
        'handler' => static function (): void {
            header('Content-Type: text/html; charset=utf-8');

            echo '<h1>GlicoLife</h1>';
            echo '<p>Roteador funcionando!</p>';
        },
        'auth' => false,
    ],
];
