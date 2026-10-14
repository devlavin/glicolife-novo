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

function verificarAtualizacao(
    string $nome,
    callable $teste
): void {
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

    $id1 = $model->create([
        'nome' => 'Usuario Original',
        'email' => "original_{$sufixo}@glicolife.local",
        'senha_hash' => password_hash(
            'SenhaTeste#2026',
            PASSWORD_DEFAULT
        ),
    ]);

    $id2 = $model->create([
        'nome' => 'Outro Usuario',
        'email' => "outro_{$sufixo}@glicolife.local",
        'senha_hash' => password_hash(
            'SenhaTeste#2026',
            PASSWORD_DEFAULT
        ),
    ]);

    verificarAtualizacao(
        'Atualizar nome e e-mail',
        function () use ($service, $model, $id1, $sufixo): bool {
            $resultado = $service->atualizarDadosBasicos($id1, [
                'nome' => 'Nome Atualizado',
                'email' => "NOVO_{$sufixo}@glicolife.local",
            ]);

            $usuario = $model->findById($id1);

            return $resultado
                && $usuario !== null
                && $usuario['nome'] === 'Nome Atualizado'
                && $usuario['email'] === "novo_{$sufixo}@glicolife.local";
        }
    );

    verificarAtualizacao(
        'Rejeitar e-mail de outra conta',
        function () use ($service, $id1, $id2, $sufixo): bool {
            try {
                $service->atualizarDadosBasicos($id1, [
                    'nome' => 'Tentativa de Alteração',
                    'email' => "outro_{$sufixo}@glicolife.local",
                ]);

                return false;
            } catch (InvalidArgumentException $e) {
                return $e->getMessage()
                    === 'Este e-mail já está cadastrado por outro usuário.';
            }
        }
    );

    verificarAtualizacao(
        'Rejeitar nome vazio',
        function () use ($service, $id1): bool {
            try {
                $service->atualizarDadosBasicos($id1, [
                    'nome' => '',
                    'email' => 'valido@example.com',
                ]);

                return false;
            } catch (InvalidArgumentException $e) {
                return $e->getMessage() === 'O nome é obrigatório.';
            }
        }
    );

    verificarAtualizacao(
        'Rejeitar ID inexistente',
        function () use ($service): bool {
            try {
                $service->atualizarDadosBasicos(-999999, [
                    'nome' => 'Teste',
                    'email' => 'teste@example.com',
                ]);

                return false;
            } catch (InvalidArgumentException $e) {
                return $e->getMessage() === 'ID de usuário inválido.';
            }
        }
    );

    verificarAtualizacao(
        'Não permitir alteração de role por este método',
        function () use ($model, $id1): bool {
            $antes = $model->findById($id1);

            // O método de atualização recebe apenas os campos permitidos.
            $model->updateDadosBasicos($id1, [
                'nome' => 'Nome Seguro',
                'email' => $antes['email'],
                'celular' => null,
                'data_nascimento' => null,
                'sexo' => null,
                'tipo_diabetes' => null,
                'role' => 'admin',
            ]);

            $depois = $model->findById($id1);

            return $antes !== null
                && $depois !== null
                && $antes['role'] === $depois['role'];
        }
    );
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
