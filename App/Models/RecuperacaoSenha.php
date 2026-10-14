<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class RecuperacaoSenha extends BaseModel
{
    /**
     * Invalida tokens anteriores ainda não utilizados.
     */
    public function invalidarTokensAnteriores(int $usuarioId): void
    {
        $sql = "
            UPDATE recuperacao_senha
            SET usado = 1
            WHERE usuario_id = :usuario_id
              AND usado = 0
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Salva somente o hash do token.
     */
    public function criar(
        int $usuarioId,
        string $tokenHash,
        string $expiraEm
    ): int {
        $sql = "
            INSERT INTO recuperacao_senha
                (usuario_id, token_hash, expira_em)
            VALUES
                (:usuario_id, :token_hash, :expira_em)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'token_hash' => $tokenHash,
            'expira_em' => $expiraEm,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Procura um token válido, não utilizado e não expirado.
     */
    public function buscarTokenValido(string $tokenHash): ?array
    {
        $sql = "
            SELECT id, usuario_id, token_hash, expira_em
            FROM recuperacao_senha
            WHERE token_hash = :token_hash
              AND usado = 0
              AND expira_em > NOW()
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'token_hash' => $tokenHash,
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }

    /**
     * Marca o token como utilizado.
     * A condição impede reutilização.
     */
    public function marcarComoUsado(int $id): bool
    {
        $sql = "
            UPDATE recuperacao_senha
            SET usado = 1
            WHERE id = :id
              AND usado = 0
              AND expira_em > NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
        ]);

        return $stmt->rowCount() === 1;
    }
}
