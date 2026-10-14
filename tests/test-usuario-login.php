<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Models\Usuario;
use App\Services\UsuarioService;
use InvalidArgumentException;
use Throwable;

$db = Database::getConnection();
$model = new Usuario();
$service = new UsuarioService($model);

$aprovados = 0;
$falhas = 0;

function testarLogin(string $nome, callable $teste): void
{
    global $aprovados, $falhas;

    try {
        if ($teste() !== true) {
            throw new RuntimeException('Resultado inesperado.');
        }

        $aprovados++;
        echo "[OK] {$nome}\n";
    } catch (Throwable $e) {
        $falhas++;
        echo "[FALHOU] {$nome}: {$e->getMessage()}\n";
    }
}

$db->beginTransaction();

try {
    $sufixo = bin2hex(random_bytes(6));
    $senha = 'SenhaTeste#2026';

    $id = $model->create([
        'nome' => 'Usuario Login',
        'email' => "login_{$sufixo}@glicolife.local",
        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
    ]);

    $email = "login_{$sufixo}@glicolife.local";

    testarLogin('Login com credenciais corretas', function () use (
        $service,
        $email,
        $senha
    ): bool {
        $usuario = $service->autenticar($email, $senha);

        return isset($usuario['id'], $usuario['email'])
            && !isset($usuario['senha']);
    });

    testarLogin('Rejeitar senha incorreta', function () use (
        $service,
        $email
    ): bool {
        try {
            $service->autenticar($email, 'SenhaErrada#2026');
            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage() === 'E-mail ou senha inválidos.';
        }
    });

    testarLogin('Rejeitar e-mail inexistente', function () use (
        $service
    ): bool {
        try {
            $service->autenticar(
                'inexistente_' . bin2hex(random_bytes(4)) . '@glicolife.local',
                'SenhaTeste#2026'
            );

            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage() === 'E-mail ou senha inválidos.';
        }
    });

    testarLogin('Rejeitar conta inativa', function () use (
        $service,
        $model,
        $id,
        $email,
        $senha
    ): bool {
        $model->atualizarAtivo($id, false);

        try {
            $service->autenticar($email, $senha);
            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage() === 'E-mail ou senha inválidos.';
        }
    });
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

echo "\n-----------------------------\n";
echo "Testes aprovados: {$aprovados}\n";
echo "Testes com falha: {$falhas}\n";
echo "-----------------------------\n";

if ($falhas > 0) {
    exit(1);
}

echo "Todos os testes passaram!\n";
