<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Sessao;

$resultados = [];

function verificarSessao(bool $condicao, string $descricao): void
{
    global $resultados;

    $resultados[] = [
        'passou' => $condicao,
        'descricao' => $descricao,
    ];
}

try {
    // Usuário fictício: não cria nem altera registros no banco.
    $usuarioTeste = [
        'id' => 999999,
        'nome' => 'Usuário de Teste',
        'email' => 'teste.sessao@example.com',
        'role' => 'usuario',
    ];

    // 1. Criar a sessão autenticada.
    Sessao::login($usuarioTeste);

    verificarSessao(
        Sessao::autenticado(),
        'Reconhece o usuário como autenticado'
    );

    // 2. Recuperar os dados da sessão.
    $usuarioAtual = Sessao::usuarioAtual();

    verificarSessao(
        is_array($usuarioAtual)
        && $usuarioAtual['id'] === 999999
        && $usuarioAtual['nome'] === 'Usuário de Teste'
        && $usuarioAtual['email'] === 'teste.sessao@example.com',
        'Recupera os dados básicos do usuário'
    );

    // 3. Confirmar que não guardamos dados desnecessários.
    verificarSessao(
        is_array($usuarioAtual)
        && !array_key_exists('senha', $usuarioAtual)
        && !array_key_exists('senha_hash', $usuarioAtual),
        'Não mantém senha nos dados da sessão'
    );

    // 4. Encerrar a sessão.
    Sessao::logout();

    verificarSessao(
        !Sessao::autenticado(),
        'Logout encerra a autenticação'
    );

    verificarSessao(
        Sessao::usuarioAtual() === null,
        'Não retorna usuário após o logout'
    );

} catch (Throwable $e) {
    $resultados[] = [
        'passou' => false,
        'descricao' => 'Erro inesperado: '
            . get_class($e) . ': ' . $e->getMessage(),
    ];

    // Evita deixar a sessão de teste aberta após uma falha.
    try {
        Sessao::logout();
    } catch (Throwable $erroLogout) {
        // Mantém o erro original no relatório.
    }
}

$aprovados = 0;
$falhados = 0;

foreach ($resultados as $resultado) {
    if ($resultado['passou']) {
        $aprovados++;
        echo '[OK] ' . $resultado['descricao'] . PHP_EOL;
    } else {
        $falhados++;
        echo '[FALHOU] ' . $resultado['descricao'] . PHP_EOL;
    }
}

echo PHP_EOL;
echo "Testes aprovados: {$aprovados}" . PHP_EOL;
echo "Testes falhados: {$falhados}" . PHP_EOL;

exit($falhados === 0 ? 0 : 1);
