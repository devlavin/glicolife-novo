<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Services\UsuarioService;

$email = 'teste.api@glicolife.local';
$senha = 'GlicoTeste2026!';

$service = new UsuarioService();

try {
    $existente = $service->buscarPorEmail($email);

    if ($existente !== null) {
        echo "A conta de teste já existe.\n";
        echo "E-mail: {$email}\n";
        echo "Se não souber a senha, não altere a conta manualmente.\n";
        exit(0);
    }

    $id = $service->cadastrar([
        'nome' => 'Usuário Teste API',
        'email' => $email,
        'senha' => $senha,
    ]);

    echo "Conta de teste criada com sucesso!\n";
    echo "ID: {$id}\n";
    echo "E-mail: {$email}\n";
    echo "Senha: {$senha}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Não foi possível criar a conta: {$e->getMessage()}\n");
    exit(1);
}
