<?php

declare(strict_types=1);

ob_start();

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Sessao;
use App\Middleware\AuthMiddleware;
use App\Services\UsuarioService;

$aprovados = 0;
$falhados = 0;

function verificarAcesso(bool $condicao, string $descricao): void
{
    global $aprovados, $falhados;

    if ($condicao) {
        $aprovados++;
        echo "[OK] {$descricao}" . PHP_EOL;
    } else {
        $falhados++;
        echo "[FALHOU] {$descricao}" . PHP_EOL;
    }
}

try {
    $service = new UsuarioService();

    // Usa a conta real criada para os testes locais.
    $usuarioBanco = $service->buscarPorEmail(
        'teste.api@glicolife.local'
    );

    if ($usuarioBanco === null || empty($usuarioBanco['ativo'])) {
        throw new RuntimeException(
            'A conta de teste não existe ou está inativa.'
        );
    }

    // 1. Visitante não autenticado.
    Sessao::iniciar();
    $_SESSION = [];

    verificarAcesso(
        AuthMiddleware::verificar() === false,
        'Bloqueia visitante não autenticado'
    );

    verificarAcesso(
        AuthMiddleware::exigirRole('admin') === false,
        'Visitante não recebe permissão de administrador'
    );

    // 2. Usuário real autenticado.
    Sessao::login([
        'id' => $usuarioBanco['id'],
        'nome' => $usuarioBanco['nome'],
        'email' => $usuarioBanco['email'],
        'role' => $usuarioBanco['role'],
    ]);

    verificarAcesso(
        AuthMiddleware::verificar() === true,
        'Permite usuário existente e ativo'
    );

    verificarAcesso(
        AuthMiddleware::exigirRole('user') === true,
        'Permite o papel real do usuário'
    );

    verificarAcesso(
        AuthMiddleware::exigirRole('admin') === false,
        'Bloqueia usuário comum na área administrativa'
    );

    // 3. Verifica que um usuário comum não pode se promover
    // apenas alterando o papel armazenado na sessão.
    $_SESSION['usuario']['role'] = 'admin';

    verificarAcesso(
        AuthMiddleware::verificar() === false,
        'Invalida sessão com papel divergente do banco'
    );

} catch (Throwable $e) {
    $falhados++;

    echo '[ERRO] ' . get_class($e) . ': '
        . $e->getMessage() . PHP_EOL;

} finally {
    try {
        Sessao::logout();
    } catch (Throwable $e) {
        $falhados++;

        echo '[ERRO AO ENCERRAR SESSÃO] '
            . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL;
echo "Testes aprovados: {$aprovados}" . PHP_EOL;
echo "Testes falhados: {$falhados}" . PHP_EOL;

$resultado = $falhados === 0 ? 0 : 1;

if (ob_get_level() > 0) {
    ob_end_flush();
}

exit($resultado);
