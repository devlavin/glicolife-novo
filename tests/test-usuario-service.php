<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Services\UsuarioService;
use InvalidArgumentException;
use Throwable;

$db = Database::getConnection();
$service = new UsuarioService();

$aprovados = 0;
$falhas = 0;

function verificar(string $nome, callable $teste): void
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

$emailTeste = 'service_' . bin2hex(random_bytes(6))
    . '@glicolife.local';

$db->beginTransaction();

try {
    verificar('Rejeitar nome vazio', function () use ($service): bool {
        try {
            $service->cadastrar([
                'nome' => '',
                'email' => 'nome.vazio@example.com',
                'senha' => 'SenhaTeste#2026',
            ]);

            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage() === 'O nome é obrigatório.';
        }
    });

    verificar('Rejeitar e-mail inválido', function () use ($service): bool {
        try {
            $service->cadastrar([
                'nome' => 'Teste',
                'email' => 'email-invalido',
                'senha' => 'SenhaTeste#2026',
            ]);

            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage() === 'Informe um e-mail válido.';
        }
    });

    verificar('Rejeitar senha curta', function () use ($service): bool {
        try {
            $service->cadastrar([
                'nome' => 'Teste',
                'email' => 'senha.curta@example.com',
                'senha' => '123',
            ]);

            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage()
                === 'A senha deve ter pelo menos 8 caracteres.';
        }
    });

    $id = $service->cadastrar([
        'nome' => 'Usuário Service',
        'email' => '  ' . strtoupper($emailTeste) . '  ',
        'senha' => 'SenhaTeste#2026',
    ]);

    
    verificar('Rejeitar senha sem maiúscula', function () use ($service): bool {
        try {
            $service->cadastrar([
                'nome' => 'Teste',
                'email' => 'sem.maiuscula@example.com',
                'senha' => 'senha123!',
            ]);

            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage()
                === 'A senha deve conter pelo menos uma letra maiúscula.';
        }
    });

    verificar('Rejeitar senha sem número', function () use ($service): bool {
        try {
            $service->cadastrar([
                'nome' => 'Teste',
                'email' => 'sem.numero@example.com',
                'senha' => 'SenhaTeste!',
            ]);

            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage()
                === 'A senha deve conter pelo menos um número.';
        }
    });

    verificar('Rejeitar senha sem caractere especial', function () use ($service): bool {
        try {
            $service->cadastrar([
                'nome' => 'Teste',
                'email' => 'sem.especial@example.com',
                'senha' => 'SenhaTeste123',
            ]);

            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage()
                === 'A senha deve conter pelo menos um caractere especial.';
        }
    });


    verificar('Cadastrar usuário válido', fn (): bool => $id > 0);

    verificar('Normalizar e-mail', function () use ($service, $emailTeste): bool {
        $usuario = $service->buscarPorEmail($emailTeste);

        return $usuario !== null
            && $usuario['email'] === $emailTeste;
    });

    verificar('Rejeitar e-mail duplicado', function () use (
        $service,
        $emailTeste
    ): bool {
        try {
            $service->cadastrar([
                'nome' => 'Outro Usuário',
                'email' => $emailTeste,
                'senha' => 'OutraSenha#2026',
            ]);

            return false;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage() === 'Este e-mail já está cadastrado.';
        }
    });

    verificar('Buscar usuário cadastrado', function () use ($service, $id): bool {
        $usuario = $service->buscarPorId($id);

        return $usuario !== null
            && (int) $usuario['id'] === $id;
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
