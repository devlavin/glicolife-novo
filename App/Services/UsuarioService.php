<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

final class UsuarioService
{
    private Usuario $usuarioModel;

    public function __construct(?Usuario $usuarioModel = null)
    {
        $this->usuarioModel = $usuarioModel ?? new Usuario();
    }

    /**
     * Cadastra um usuário após validar os dados.
     */
    public function cadastrar(array $dados): int
    {
        $nome = trim((string) ($dados['nome'] ?? ''));
        $email = strtolower(trim((string) ($dados['email'] ?? '')));
        $senha = (string) ($dados['senha'] ?? '');

        if ($nome === '') {
            throw new InvalidArgumentException(
                'O nome é obrigatório.'
            );
        }

        if (mb_strlen($nome) > 150) {
            throw new InvalidArgumentException(
                'O nome deve ter no máximo 150 caracteres.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                'Informe um e-mail válido.'
            );
        }

        $this->validarSenha($senha);
        
        if ($this->usuarioModel->findByEmail($email) !== null) {
            throw new InvalidArgumentException(
                'Este e-mail já está cadastrado.'
            );
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        if ($senhaHash === false) {
            throw new RuntimeException(
                'Não foi possível proteger a senha.'
            );
        }

        try {
            return $this->usuarioModel->create([
                'nome' => $nome,
                'email' => $email,
                'celular' => $dados['celular'] ?? null,
                'data_nascimento' => $dados['data_nascimento'] ?? null,
                'sexo' => $dados['sexo'] ?? null,
                'tipo_diabetes' => $dados['tipo_diabetes'] ?? null,
                'senha_hash' => $senhaHash,
            ]);
        } catch (PDOException $e) {
            // Proteção adicional contra cadastros simultâneos.
            // O índice UNIQUE do banco continua sendo necessário.
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException(
                    'Não foi possível cadastrar: confira se o e-mail já existe.',
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    /**
     * Busca o perfil público de um usuário.
     */
    public function buscarPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'O ID do usuário deve ser positivo.'
            );
        }

        return $this->usuarioModel->findById($id);
    }

    /**
     * Busca um usuário pelo e-mail.
     */
    public function buscarPorEmail(string $email): ?array
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                'Informe um e-mail válido.'
            );
        }

        return $this->usuarioModel->findByEmail($email);
    }

    private function validarSenha(string $senha): void
    {
        if (strlen($senha) < 8) {
            throw new InvalidArgumentException(
                'A senha deve ter pelo menos 8 caracteres.'
            );
        }

        if (!preg_match('/[A-Z]/', $senha)) {
            throw new InvalidArgumentException(
                'A senha deve conter pelo menos uma letra maiúscula.'
            );
        }

        if (!preg_match('/[0-9]/', $senha)) {
            throw new InvalidArgumentException(
                'A senha deve conter pelo menos um número.'
            );
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $senha)) {
            throw new InvalidArgumentException(
                'A senha deve conter pelo menos um caractere especial.'
            );
        }

        if (strlen($senha) > 4096) {
            throw new InvalidArgumentException(
                'A senha ultrapassa o limite permitido.'
            );
        }
    }
       
    /**
     * Atualiza os dados básicos de um usuário.
     */
    public function atualizarDadosBasicos(
        int $id,
        array $dados
    ): bool {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'ID de usuário inválido.'
            );
        }

        $usuarioAtual = $this->usuarioModel->findById($id);

        if ($usuarioAtual === null) {
            throw new InvalidArgumentException(
                'Usuário não encontrado.'
            );
        }

        $nome = trim((string) ($dados['nome'] ?? ''));
        $email = strtolower(trim((string) ($dados['email'] ?? '')));

        if ($nome === '') {
            throw new InvalidArgumentException(
                'O nome é obrigatório.'
            );
        }

        if (mb_strlen($nome) > 150) {
            throw new InvalidArgumentException(
                'O nome deve ter no máximo 150 caracteres.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                'Informe um e-mail válido.'
            );
        }

        $outroUsuario = $this->usuarioModel->findByEmail($email);

        if (
            $outroUsuario !== null
            && (int) $outroUsuario['id'] !== $id
        ) {
            throw new InvalidArgumentException(
                'Este e-mail já está cadastrado por outro usuário.'
            );
        }

        return $this->usuarioModel->updateDadosBasicos($id, [
            'nome' => $nome,
            'email' => $email,
            'celular' => $dados['celular'] ?? null,
            'data_nascimento' => $dados['data_nascimento'] ?? null,
            'sexo' => $dados['sexo'] ?? null,
            'tipo_diabetes' => $dados['tipo_diabetes'] ?? null,
        ]);
    }

    /**
     * Autentica o usuário usando o e-mail e a senha.
     *
     * Retorna os dados públicos do usuário autenticado.
     */
    public function autenticar(string $email, string $senha): array
    {
        $email = strtolower(trim($email));

        if ($email === '' || $senha === '') {
            throw new InvalidArgumentException(
                'E-mail ou senha inválidos.'
            );
        }

        $usuario = $this->usuarioModel->findForAuthentication($email);

        // A mesma mensagem evita revelar se o e-mail existe.
        if (
            $usuario === null
            || (int) $usuario['ativo'] !== 1
            || !password_verify($senha, $usuario['senha'])
        ) {
            throw new InvalidArgumentException(
                'E-mail ou senha inválidos.'
            );
        }

        // Não devolvemos o hash da senha ao chamador.
        unset($usuario['senha']);

        return $usuario;
    }

}


