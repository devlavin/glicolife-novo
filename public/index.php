<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Router;

$rotasWeb = require dirname(__DIR__) . '/routes/web.php';
$rotasApi = require dirname(__DIR__) . '/routes/api.php';

$rotas = array_merge($rotasWeb, $rotasApi);

$router = new Router();
$router->dispatch($rotas);