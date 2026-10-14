<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\RecuperacaoSenha;
use App\Models\Usuario;
use InvalidArgumentException;
use PDO;
use Throwable;

class RecuperacaoSenhaService
{
    private PDO $db;
    private Usuario $usuarioModel;
    private RecuperacaoSenha $recuperacaoModel;

    public function __construct(
        ?Usuario $usuarioModel = null,
        ?RecuperacaoSenha $recuperacaoModel = null
    ) {
        $this->db = Database::getConnection();
        $this->usuarioModel = $usuarioModel ?? new Usuario();
        $this->recuperacaoModel =
            $recuperacaoModel ?? new RecuperacaoSenha();
    }

    /**
     * Solicita a recuperação sem revelar se o e-mail existe.
     *
     * O callback recebe o e-mail e o token original.
     * Ele deverá enviar o link por um canal confiável.
     */
    public function solicitarRecuperacao(
        string $email,
        callable $enviarToken
    ): void {
        $email = mb_strtolower(trim($email), 'UTF-8');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $usuario = $this->usuarioModel->findByEmail($email);

        if (!$usuario || empty($usuario['id'])) {
            return;
        }

        $usuarioId = (int) $usuario['id'];

        // Token original: somente para compor o link enviado ao usuário.
        $token = bin2hex(random_bytes(32));

        // No banco, armazenamos apenas o SHA-256 do token.
        $tokenHash = hash('sha256', $token);

        $expiraEm = date(
            'Y-m-d H:i:s',
            time() + (30 * 60)
        );

        $this->db->beginTransaction();

        try {
            $this->recuperacaoModel
                ->invalidarTokensAnteriores($usuarioId);

            $this->recuperacaoModel->criar(
                $usuarioId,
                $tokenHash,
                $expiraEm
            );

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }

        // O token não deve ser retornado na resposta HTTP.
        // O callback deve encaminhá-lo por e-mail de forma segura.
        $enviarToken($email, $token);
    }

    /**
     * Troca a senha usando um token válido e de uso único.
     */
    public function redefinirSenha(
        string $token,
        string $novaSenha
    ): void {
        $token = trim($token);

        if (
            strlen($token) !== 64
            || !ctype_xdigit($token)
        ) {
            throw new InvalidArgumentException(
                'Solicitação de recuperação inválida ou expirada.'
            );
        }

        $this->validarSenha($novaSenha);

        $tokenHash = hash('sha256', $token);

        $this->db->beginTransaction();

        try {
            // A consulta FOR UPDATE precisa ocorrer nesta transação.
            $registro = $this->recuperacaoModel
                ->buscarTokenValido($tokenHash);

            if (!$registro) {
                throw new InvalidArgumentException(
                    'Solicitação de recuperação inválida ou expirada.'
                );
            }

            $usuarioId = (int) $registro['usuario_id'];
            $recuperacaoId = (int) $registro['id'];

            $senhaHash = password_hash(
                $novaSenha,
                PASSWORD_DEFAULT
            );

            if ($senhaHash === false) {
                throw new \RuntimeException(
                    'Não foi possível proteger a nova senha.'
                );
            }

            if (!$this->usuarioModel->atualizarSenha(
                $usuarioId,
                $senhaHash
            )) {
                throw new \RuntimeException(
                    'Não foi possível atualizar a senha.'
                );
            }

            if (!$this->recuperacaoModel
                ->marcarComoUsado($recuperacaoId)) {
                throw new InvalidArgumentException(
                    'Solicitação de recuperação inválida ou expirada.'
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function validarSenha(string $senha): void
    {
        if (strlen($senha) < 8) {
            throw new InvalidArgumentException(
                'A senha deve ter pelo menos 8 caracteres.'
            );
        }

        if (strlen($senha) > 4096) {
            throw new InvalidArgumentException(
                'A senha informada é muito longa.'
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
    }
}
