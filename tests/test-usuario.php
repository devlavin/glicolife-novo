<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Models\Usuario;

$db = Database::getConnection();
$usuarioModel = new Usuario();

$testesPassaram = 0;
$testesFalharam = 0;

function testar(string $nome, callable $teste): void
{
    global $testesPassaram, $testesFalharam;

    try {
        $resultado = $teste();

        if ($resultado !== true) {
            throw new RuntimeException(
                'O resultado não foi o esperado.'
            );
        }

        $testesPassaram++;
        echo "[OK] {$nome}\n";
    } catch (Throwable $e) {
        $testesFalharam++;
        echo "[FALHOU] {$nome}: {$e->getMessage()}\n";
    }
}

$db->beginTransaction();

try {
    $email = 'teste_' . bin2hex(random_bytes(6))
        . '@glicolife.local';

    $id = 0;

    testar('Cadastrar usuário', function () use (
        $usuarioModel,
        $email,
        &$id
    ): bool {
        $id = $usuarioModel->create([
            'nome' => 'Usuário de Teste',
            'email' => $email,
            'celular' => null,
            'data_nascimento' => null,
            'sexo' => null,
            'tipo_diabetes' => null,
            'senha_hash' => password_hash(
                'SenhaTeste#2026',
                PASSWORD_DEFAULT
            ),
        ]);

        return $id > 0;
    });

    testar('Buscar usuário por ID', function () use (
        $usuarioModel,
        &$id,
        $email
    ): bool {
        if ($id === null) {
            return false;
        }
        $usuario = $usuarioModel->findById($id);

        return $usuario !== null
            && $usuario['email'] === $email
            && $usuario['nome'] === 'Usuário de Teste';
    });

    testar('Buscar usuário por e-mail', function () use (
        $usuarioModel,
        $email
    ): bool {
        $usuario = $usuarioModel->findByEmail($email);

        return $usuario !== null
            && $usuario['email'] === $email;
    });

    testar('Buscar credenciais de autenticação', function () use (
        $usuarioModel,
        $email
    ): bool {
        $usuario = $usuarioModel->findForAuthentication($email);

        return $usuario !== null
            && isset($usuario['senha'])
            && password_verify(
                'SenhaTeste#2026',
                $usuario['senha']
            );
    });

    testar('Atualizar dados básicos', function () use (
        $usuarioModel,
        &$id,
        $email
    ): bool {
        if ($id === null) {
            return false;
        }
        $novoEmail = 'atualizado_' . bin2hex(random_bytes(6))
            . '@glicolife.local';

        $resultado = $usuarioModel->updateDadosBasicos($id, [
            'nome' => 'Nome Atualizado',
            'email' => $novoEmail,
            'celular' => null,
            'data_nascimento' => null,
            'sexo' => null,
            'tipo_diabetes' => null,
        ]);

        if (!$resultado) {
            return false;
        }

        $usuario = $usuarioModel->findById($id);

        return $usuario !== null
            && $usuario['nome'] === 'Nome Atualizado'
            && $usuario['email'] === $novoEmail;
    });

    testar('Desativar usuário', function () use (
        $usuarioModel,
        &$id
    ): bool {
        if ($id === null) {
            return false;
        }
        if (!$usuarioModel->atualizarAtivo($id, false)) {
            return false;
        }

        $usuario = $usuarioModel->findById($id);

        return $usuario !== null
            && (int) $usuario['ativo'] === 0;
    });

    testar('ID inexistente retorna null', function () use (
        $usuarioModel
    ): bool {
        return $usuarioModel->findById(-999999) === null;
    });
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

echo "\n-----------------------------\n";
echo "Testes aprovados: {$testesPassaram}\n";
echo "Testes com falha: {$testesFalharam}\n";
echo "-----------------------------\n";

if ($testesFalharam > 0) {
    exit(1);
}

echo "Todos os testes passaram!\n";
