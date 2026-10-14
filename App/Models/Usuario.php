<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Usuario extends BaseModel
{
    /**
     * Busca um usuário pelo ID.
     * Não retorna a senha.
     */
    public function findById(int $id): ?array
    {
        $sql = '
            SELECT
                id,
                nome,
                email,
                celular,
                data_nascimento,
                sexo,
                tipo_diabetes,
                foto,
                role,
                ativo,
                verificado,
                onboarding_feito,
                tem_pressao_alta,
                registrar_pressao,
                criado_em
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    /**
     * Busca um usuário pelo e-mail.
     * Não retorna a senha.
     */
    public function findByEmail(string $email): ?array
    {
        $sql = '
            SELECT
                id,
                nome,
                email,
                celular,
                role,
                ativo,
                verificado,
                onboarding_feito
            FROM usuarios
            WHERE email = :email
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => trim($email)]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    /**
     * Busca os dados necessários para autenticação.
     * A senha armazenada é o hash, nunca a senha original.
     */
    public function findForAuthentication(string $email): ?array
    {
        $sql = '
            SELECT
                id,
                nome,
                email,
                senha,
                role,
                ativo,
                verificado
            FROM usuarios
            WHERE email = :email
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => trim($email)]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    /**
     * Cadastra um usuário e retorna o ID criado.
     *
     * A senha recebida deve estar previamente protegida
     * com password_hash() pelo Service.
     */
    public function create(array $dados): int
    {
        $sql = '
            INSERT INTO usuarios (
                nome,
                email,
                celular,
                data_nascimento,
                sexo,
                tipo_diabetes,
                senha
            ) VALUES (
                :nome,
                :email,
                :celular,
                :data_nascimento,
                :sexo,
                :tipo_diabetes,
                :senha
            )
        ';

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'nome' => trim($dados['nome']),
            'email' => trim($dados['email']),
            'celular' => $dados['celular'] ?? null,
            'data_nascimento' => $dados['data_nascimento'] ?? null,
            'sexo' => $dados['sexo'] ?? null,
            'tipo_diabetes' => $dados['tipo_diabetes'] ?? null,
            'senha' => $dados['senha_hash'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza os dados básicos do usuário.
     * Retorna false se o ID não existir.
     */
    public function updateDadosBasicos(
        int $id,
        array $dados
    ): bool {
        $sql = '
            UPDATE usuarios
            SET
                nome = :nome,
                email = :email,
                celular = :celular,
                data_nascimento = :data_nascimento,
                sexo = :sexo,
                tipo_diabetes = :tipo_diabetes
            WHERE id = :id
        ';

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id,
            'nome' => trim($dados['nome']),
            'email' => trim($dados['email']),
            'celular' => $dados['celular'] ?? null,
            'data_nascimento' => $dados['data_nascimento'] ?? null,
            'sexo' => $dados['sexo'] ?? null,
            'tipo_diabetes' => $dados['tipo_diabetes'] ?? null,
        ]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        // rowCount() pode ser zero se nenhum valor mudou.
        return $this->findById($id) !== null;
    }

        /**
     * Atualiza o hash da senha de um usuário.
     */
    public function atualizarSenha(int $id, string $senhaHash): bool
    {
        $sql = "
            UPDATE usuarios
            SET senha = :senha
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'senha' => $senhaHash,
            'id' => $id,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Ativa ou desativa uma conta.
     */
    public function atualizarAtivo(int $id, bool $ativo): bool
    {
        $sql = '
            UPDATE usuarios
            SET ativo = :ativo
            WHERE id = :id
        ';

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'id' => $id,
            'ativo' => (int) $ativo,
        ]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        // Distingue um usuário inexistente de um valor inalterado.
        return $this->findById($id) !== null;
    }

    
}
