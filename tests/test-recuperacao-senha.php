<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Models\Usuario;
use App\Services\RecuperacaoSenhaService;
use InvalidArgumentException;
use Throwable;

$db = Database::getConnection();
$usuarioModel = new Usuario();
$servico = new RecuperacaoSenhaService($usuarioModel);

$email = 'teste.recuperacao.' . bin2hex(random_bytes(5))
    . '@exemplo.com';

$usuarioId = 0;
$testesPassaram = 0;
$testesFalharam = 0;

function verificar(bool $condicao, string $descricao): void
{
    global $testesPassaram, $testesFalharam;

    if ($condicao) {
        $testesPassaram++;
        echo "[OK] {$descricao}" . PHP_EOL;
    } else {
        $testesFalharam++;
        echo "[FALHOU] {$descricao}" . PHP_EOL;
    }
}

function esperarErro(
    callable $acao,
    string $descricao
): void {
    try {
        $acao();
        verificar(false, $descricao);
    } catch (InvalidArgumentException $e) {
        verificar(true, $descricao);
    }
}

try {
    // Criar um usuário exclusivo para o teste.
    $usuarioId = $usuarioModel->create([
        'nome' => 'Usuário Teste Recuperação',
        'email' => $email,
        'celular' => null,
        'data_nascimento' => null,
        'sexo' => null,
        'tipo_diabetes' => null,
        'senha_hash' => password_hash(
            'SenhaAntiga1!',
            PASSWORD_DEFAULT
        ),
    ]);

    // 1. Solicitar recuperação e capturar o token no teste.
    $token = null;

    $servico->solicitarRecuperacao(
    $email,
    function (string $destino, string $tokenEnviado) use (&$token): void {
        $token = $tokenEnviado;
    }
    );

    if (!is_string($token)) {
        throw new RuntimeException('Token de recuperação não foi gerado.');
    }

    verificar(
        strlen($token) === 64,
        'Gera token de recuperação válido'
    );

    // 2. Confirmar que o banco guarda o hash, não o token original.
    $stmt = $db->prepare(
        'SELECT token_hash FROM recuperacao_senha
         WHERE usuario_id = :usuario_id
         ORDER BY id DESC LIMIT 1'
    );

    $stmt->execute(['usuario_id' => $usuarioId]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    verificar(
        is_array($registro)
        && hash_equals(
            hash('sha256', $token),
            $registro['token_hash']
        ),
        'Armazena somente o hash do token'
    );

    // 3. Redefinir a senha usando o token válido.
    $servico->redefinirSenha($token, 'SenhaNova2!');

    $usuario = $usuarioModel->findForAuthentication($email);

    verificar(
        is_array($usuario)
        && password_verify('SenhaNova2!', $usuario['senha']),
        'Atualiza a senha corretamente'
    );

    // 4. O token não pode ser utilizado novamente.
    esperarErro(
        fn() => $servico->redefinirSenha(
            $token,
            'OutraSenha3!'
        ),
        'Rejeita token reutilizado'
    );

    // 5. Solicitar outro token e forçar seu vencimento.
    $tokenExpirado = null;

    $servico->solicitarRecuperacao(
        $email,
        function (string $destino, string $tokenEnviado)
            use (&$tokenExpirado): void {
                $tokenExpirado = $tokenEnviado;
            }
    );
    if (!is_string($tokenExpirado)) {
        throw new RuntimeException('Token expirado de teste não foi gerado.');
    }
    $hashExpirado = hash('sha256', $tokenExpirado);

    $stmt = $db->prepare(
        "UPDATE recuperacao_senha
         SET expira_em = '2000-01-01 00:00:00'
         WHERE usuario_id = :usuario_id
           AND token_hash = :token_hash"
    );

    $stmt->execute([
        'usuario_id' => $usuarioId,
        'token_hash' => $hashExpirado,
    ]);

    esperarErro(
        fn() => $servico->redefinirSenha(
            $tokenExpirado,
            'SenhaNova3!'
        ),
        'Rejeita token expirado'
    );

    // 6. Senha fraca não deve ser aceita.
    $tokenFraco = null;

    $servico->solicitarRecuperacao(
        $email,
        function (string $destino, string $tokenEnviado)
            use (&$tokenFraco): void {
                $tokenFraco = $tokenEnviado;
            }
    );

    if (!is_string($tokenFraco)) {
        throw new RuntimeException('Token fraco de teste não foi gerado.');
    }

    esperarErro(
        fn() => $servico->redefinirSenha(
            $tokenFraco,
            'senha123'
        ),
        'Rejeita senha sem os requisitos mínimos'
    );

} catch (Throwable $e) {
    echo '[ERRO INESPERADO] '
        . get_class($e) . ': '
        . $e->getMessage()
        . PHP_EOL;

} finally {
    // Limpar os registros de teste sem apagar contas reais.
    if ($usuarioId > 0) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                'DELETE FROM recuperacao_senha
                 WHERE usuario_id = :usuario_id'
            );
            $stmt->execute(['usuario_id' => $usuarioId]);

            $stmt = $db->prepare(
                'DELETE FROM usuarios WHERE id = :id'
            );
            $stmt->execute(['id' => $usuarioId]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            echo '[AVISO] Não foi possível limpar os dados de teste: '
                . $e->getMessage() . PHP_EOL;
        }
    }
}

echo PHP_EOL;
echo "Testes aprovados: {$testesPassaram}" . PHP_EOL;
echo "Testes falhados: {$testesFalharam}" . PHP_EOL;
