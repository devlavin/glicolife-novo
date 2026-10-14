<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

try {
    $db = Database::getConnection();

    $stmt = $db->query('SELECT DATABASE() AS banco');
    $resultado = $stmt->fetch();

    echo "Conexão PDO estabelecida com sucesso!\n";
    echo "Banco conectado: " . $resultado['banco'] . "\n";

    $stmt = $db->query('SHOW TABLES');
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Quantidade de tabelas: " . count($tabelas) . "\n";
} catch (Throwable $exception) {
    echo "Falha no teste de conexão.\n";
    echo $exception->getMessage() . "\n";
    exit(1);
}