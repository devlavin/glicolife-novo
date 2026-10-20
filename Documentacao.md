# GlicoLife v3 — Documentação de Desenvolvimento

**Tipo:** Registro técnico e acompanhamento do projeto  
**Status:** Em desenvolvimento local  
**Última atualização:** 20 de outubro de 2026

---

## 1. Objetivo do projeto

O GlicoLife é uma aplicação web voltada ao acompanhamento e gerenciamento de informações relacionadas à saúde, com funcionalidades para usuários, registros pessoais, medicamentos, monitoramento e administração.

A versão 3 está sendo desenvolvida com uma estrutura mais organizada, separação de responsabilidades, validações no servidor e atenção à segurança dos dados.

### Regra principal do desenvolvimento

**Desenvolver, testar e estabilizar tudo localmente antes de realizar qualquer alteração na Hostinger.**

O banco antigo `glicolife` deve permanecer intacto. A nova versão utiliza um banco separado, chamado `glicolife_novo`.

---

## 2. Ambiente de desenvolvimento

| Item | Configuração |
|---|---|
| Sistema de desenvolvimento | Windows |
| Editor | Visual Studio Code |
| Servidor local | XAMPP |
| Linguagem principal | PHP nativo |
| Versão do PHP | 8.2.12 |
| Banco de dados | MariaDB |
| Versão do MariaDB | 10.4.32 |
| Gerenciamento do banco | phpMyAdmin |
| Testes de API | Postman |
| Autenticação web | Sessões PHP |
| Acesso ao banco | PDO |
| Gerenciamento de dependências | Composer |
| Variáveis de ambiente | `vlucas/phpdotenv` |
| Fuso horário | `America/Sao_Paulo` |

### Caminho do projeto

`C:\xampp\htdocs\glicolife_v3`

### Banco utilizado no desenvolvimento

`glicolife_novo`

### Endereços locais utilizados

- Aplicação: `http://localhost/glicolife_v3/public/`
- Verificação da API: `http://localhost/glicolife_v3/public/api/health`
- Login: `POST http://localhost/glicolife_v3/public/api/login`
- Usuário autenticado: `GET http://localhost/glicolife_v3/public/api/me`
- Logout: `POST http://localhost/glicolife_v3/public/api/logout`

---

## 3. Arquitetura da aplicação

A aplicação está sendo organizada em camadas para separar as responsabilidades.

| Camada | Responsabilidade |
|---|---|
| `app/Core/` | Componentes fundamentais, como conexão com o banco e sessões |
| `app/Models/` | Acesso e operações sobre os dados |
| `app/Services/` | Regras de negócio e validações |
| `app/Controllers/` | Tratamento das requisições e respostas |
| `app/Middleware/` | Verificação de autenticação e permissões |
| `routes/` | Definição das rotas |
| `public/` | Ponto de entrada público da aplicação |
| `tests/` | Testes automatizados e verificações locais |
| `bootstrap.php` | Inicialização e preparação da aplicação |

O Composer utiliza autoload PSR-4 para o namespace `App\\`, associado ao diretório `app/`.

A aplicação também utiliza variáveis de ambiente para manter configurações fora do código-fonte.

**Observação:** a organização acima representa a estrutura arquitetural que estamos utilizando. A revisão completa de todos os arquivos e rotas ainda faz parte do desenvolvimento.

---

## 4. Banco de dados

O banco `glicolife_novo` foi criado separadamente do banco antigo e possui 16 tabelas.

| Nº | Tabela | Finalidade |
|---:|---|---|
| 1 | `usuarios` | Cadastro, autenticação e dados da conta |
| 2 | `perfis_registro` | Perfis associados ao usuário |
| 3 | `glicose` | Registros de glicemia |
| 4 | `alimentacao` | Registros alimentares |
| 5 | `medicamentos` | Cadastro de medicamentos |
| 6 | `medicamento_horarios` | Horários e organização dos medicamentos |
| 7 | `pressao` | Registros de pressão arterial |
| 8 | `hidratacao` | Registros de hidratação |
| 9 | `onboarding_respostas` | Respostas do processo inicial de configuração |
| 10 | `otp_verificacao` | Dados de verificação por código |
| 11 | `recuperacao_senha` | Solicitações de recuperação de senha |
| 12 | `push_subscriptions` | Inscrições para notificações push |
| 13 | `login_tentativas` | Controle de tentativas de login |
| 14 | `admin_logs` | Registros de atividades administrativas |
| 15 | `notificacoes_pendentes` | Notificações aguardando processamento |
| 16 | `configuracoes` | Configurações da aplicação |

### Decisões importantes do banco

- O campo `registrar_pressao` é independente de `tem_pressao_alta`. Uma pessoa pode precisar registrar a pressão sem necessariamente ter hipertensão.
- Os horários dos medicamentos são armazenados em `medicamento_horarios`, e não como campos de horário e frequência diretamente em `medicamentos`.
- Na hidratação, `perfil_id` pode ser `NULL` para representar o usuário principal.
- A tabela `hidratacao` utiliza a coluna gerada `perfil_chave`, com `IFNULL(perfil_id, 0)`, para permitir a unicidade dos registros por usuário, perfil e data.
- As tabelas utilizam relacionamentos e chaves estrangeiras quando aplicável.
- As tabelas relacionadas a senhas, verificações e recuperação devem trabalhar com hashes, expiração e controles de uso conforme a finalidade de cada fluxo.

### Atenção aos perfis

Uma chave estrangeira que verifica apenas a existência de `perfil_id` não garante que o perfil pertença ao usuário autenticado.

Portanto, os Services precisam verificar a propriedade do perfil antes de permitir operações de leitura, criação, alteração ou exclusão.

Essa validação é uma responsabilidade do backend.

---

## 5. Conexão com o banco e modelos

### `app/Models/BaseModel.php`

Foi implementada uma classe abstrata base para os Models.

Ela recebe a conexão PDO por meio de `Database::getConnection()` e disponibiliza a conexão às classes que a estendem.

Isso evita duplicação da lógica de conexão em cada Model.

### Model `Usuario`

O Model de usuário possui operações para:

- Buscar usuários por ID.
- Buscar usuários por e-mail.
- Buscar os dados necessários à autenticação.
- Criar usuários.
- Atualizar dados básicos.
- Atualizar o estado ativo da conta.
- Atualizar a senha.

As consultas de autenticação são separadas das consultas de dados públicos ou gerais. Os métodos destinados a exibir dados não devem expor a senha armazenada.

### Service `UsuarioService`

O Service centraliza as regras de negócio relacionadas aos usuários.

Entre as funcionalidades implementadas estão:

- Cadastro e normalização de dados.
- Validação do nome e do e-mail.
- Verificação de e-mail duplicado.
- Validação de senha.
- Hash de senha com `password_hash()`.
- Autenticação com verificação segura da senha.
- Busca de usuário por ID ou e-mail.
- Atualização de dados básicos.
- Tratamento de erros de duplicidade de cadastro.

### Regras atuais de senha

A validação implementada exige:

- Pelo menos 8 bytes.
- No máximo 4096 bytes.
- Pelo menos uma letra maiúscula.
- Pelo menos um número.
- Pelo menos um caractere especial.

Essas regras devem ser aplicadas pelo servidor, independentemente das validações presentes na interface.

---

## 6. Sessões e autenticação

### Arquivo `app/Core/Sessao.php`

Foi implementada uma classe centralizada para gerenciar sessões PHP.

Ela possui os métodos:

- `iniciar()`
- `login(array $usuario)`
- `usuarioAtual()`
- `autenticado()`
- `logout()`

### Medidas implementadas

- Verificação do estado da sessão antes de iniciá-la.
- Verificação de cabeçalhos já enviados.
- Ativação de `session.use_strict_mode`.
- Uso de cookies para sessão.
- Configuração de `HttpOnly`.
- Configuração de `SameSite=Lax`.
- Cookie seguro quando a requisição utiliza HTTPS.
- Regeneração do identificador da sessão após o login.
- Limpeza dos dados e encerramento da sessão no logout.

O identificador da sessão é regenerado para reduzir o risco de fixação de sessão.

### Atenção

A configuração de cookies deve ser revisada novamente quando a aplicação estiver em HTTPS na hospedagem, especialmente se houver mudanças de domínio, subdomínio ou política de cookies.

Também falta concluir a auditoria de CSRF nas operações que modificam dados.

---

## 7. Middleware de autenticação

### Arquivo `app/Middleware/AuthMiddleware.php`

O middleware verifica se o usuário pode acessar determinada rota.

Métodos principais:

- `verificar()`
- `exigirRole(string $role)`

### Fluxo implementado

1. Consulta a sessão atual.
2. Rejeita visitantes não autenticados.
3. Verifica se o ID do usuário é válido.
4. Consulta o usuário no banco.
5. Rejeita contas inexistentes ou inativas.
6. Compara o papel armazenado na sessão com o papel atual do banco.
7. Invalida a sessão se houver divergência.
8. Permite a verificação de um papel específico, como `admin`.

Essa verificação impede que uma alteração isolada do campo `role` na sessão conceda privilégios administrativos.

**Importante:** a autorização também precisa ser aplicada corretamente em cada rota protegida. O middleware não substitui as validações de propriedade de registros dentro dos Services.

---

## 8. API de autenticação

### Arquivo `app/Controllers/AuthController.php`

O controller foi implementado para tratar as operações de autenticação e responder em JSON.

Rotas configuradas em `routes/api.php`:

| Método | Rota | Finalidade | Proteção |
|---|---|---|---|
| GET | `/api/health` | Verificar funcionamento da API | Pública |
| POST | `/api/login` | Autenticar usuário | Pública |
| GET | `/api/me` | Consultar usuário da sessão atual | Autenticada |
| POST | `/api/logout` | Encerrar a sessão | Autenticada |

### Comportamento do login

O controller:

- Lê o corpo JSON.
- Rejeita corpo vazio ou JSON inválido.
- Valida os tipos e a presença dos campos.
- Encaminha a autenticação ao `UsuarioService`.
- Cria a sessão após autenticação bem-sucedida.
- Retorna os dados permitidos do usuário em JSON.
- Registra detalhes técnicos de erros internos no log, sem expor esses detalhes na resposta ao cliente.

### Códigos HTTP testados ou previstos

- `200`: operação concluída.
- `400`: corpo JSON inválido ou vazio.
- `401`: autenticação necessária ou credenciais inválidas.
- `422`: campos obrigatórios inválidos ou ausentes.
- `500`: falha interna.

O comportamento exato deve continuar sendo validado à medida que novas rotas forem implementadas.

---

## 9. Roteador

O roteador utiliza as definições de `routes/api.php` para associar métodos HTTP e caminhos aos respectivos handlers.

As rotas podem indicar se exigem autenticação, e as requisições à API recebem respostas em JSON quando ocorre um erro.

O ponto de entrada público é `public/index.php`.

Também foi verificado o funcionamento local da rota de saúde da API.

---

## 10. Testes realizados

### Testes automatizados

Os seguintes arquivos de teste foram executados durante o desenvolvimento:

- `tests/test-db.php`
- `tests/test-usuario.php`
- `tests/test-usuario-service.php`
- `tests/test-usuario-atualizacao.php`
- `tests/test-usuario-login.php`
- `tests/test-recuperacao-senha.php`
- `tests/test-sessao.php`
- `tests/test-middleware.php`

Os testes de cadastro e Service de usuário foram executados com sucesso; o conjunto de testes do Service de usuário registrou 10 aprovações.

O teste atual de middleware registrou **6 aprovações e nenhuma falha**.

### Verificações de middleware

1. Bloqueia visitante não autenticado.
2. Não concede permissão administrativa a visitante.
3. Permite usuário existente e ativo.
4. Reconhece o papel real do usuário.
5. Bloqueia usuário comum na área administrativa.
6. Invalida sessão quando o papel da sessão diverge do banco.

### Testes manuais no Postman

Foi utilizada a conta local de testes:

`teste.api@glicolife.local`

O login foi testado com a requisição:

`POST http://localhost/glicolife_v3/public/api/login`

Corpo JSON:

```json
{
  "email": "teste.api@glicolife.local",
  "senha": "GlicoTeste2026!"
}
```

Resultados observados:

- Login: HTTP 200.
- Consulta `/api/me` com a sessão autenticada: HTTP 200.
- Logout: HTTP 200.
- Consulta `/api/me` depois do logout: HTTP 401.

Os testes confirmaram o funcionamento básico do ciclo de autenticação com sessão PHP e cookie de sessão no Postman.

**Nota:** a senha acima pertence à conta de teste local. Não reutilizá-la em produção nem em uma conta real.

---

## 11. Pendências imediatas

Antes de avançar para novos módulos:
.
- [ ] Revisar os atributos dos cookies de sessão.
- [ ] Auditar a proteção CSRF das operações que alteram dados.
- [ ] Revisar expiração e encerramento das sessões.
- [ ] Conferir se todas as rotas protegidas aplicam o middleware correto.

---

## 12. Próximas etapas de desenvolvimento

### Etapa A — Consolidar autenticação e segurança

- Finalizar a limpeza dos testes e do código temporário.
- Revisar cookies e sessões.
- Implementar ou auditar CSRF.
- Verificar autorização por papel em todas as rotas.
- Revisar validação de propriedade dos perfis e registros.
- Conferir os limites de tentativas de login.

### Etapa B — Concluir os fluxos de conta

- Revisar cadastro e login.
- Finalizar verificação de conta por OTP.
- Revisar recuperação de senha com token armazenado em hash.
- Garantir expiração, uso único e limite de tentativas.
- Revisar ativação e desativação de contas.

### Etapa C — Desenvolver os módulos de saúde

- Perfis de registro.
- Glicemia.
- Alimentação.
- Medicamentos e seus horários.
- Pressão arterial.
- Hidratação.
- Onboarding e configurações pessoais.

Cada módulo deve ter validações no servidor e testes próprios, incluindo tentativas de acessar dados pertencentes a outro usuário.

### Etapa D — Recursos complementares

- Notificações pendentes.
- Inscrições de notificações push.
- Logs administrativos.
- Configurações.
- Revisão geral das respostas da API.

### Etapa E — Interface e integração

- Implementar ou integrar as telas ao backend.
- Testar os fluxos completos no navegador.
- Validar erros, estados vazios e permissões.
- Fazer testes de regressão.

### Etapa F — Preparar a publicação

Somente após a versão local estar estável:

- Revisar configurações de produção.
- Conferir HTTPS e cookies.
- Preparar backup e estratégia de migração.
- Validar compatibilidade com a hospedagem.
- Planejar a publicação na Hostinger sem comprometer a versão antiga.

---

## 13. Decisões que não devem ser esquecidas

1. **Não mexer na Hostinger durante o desenvolvimento local.**
2. Não apagar nem modificar o banco antigo `glicolife`.
3. Continuar trabalhando no banco separado `glicolife_novo`.
4. Não confiar apenas nos dados armazenados na sessão para decidir permissões.
5. Não confiar apenas em chaves estrangeiras para validar a propriedade de um perfil.
6. Não expor hashes de senha, tokens de recuperação ou informações internas em respostas da API.
7. Não adicionar JWT sem uma necessidade concreta: para a aplicação web atual, sessões PHP são suficientes como ponto de partida.
8. Não considerar uma funcionalidade pronta somente porque a tela funciona; testar também o backend e os limites de autorização.
9. Executar os testes novamente após alterações relevantes.

---

## 14. Ponto exato em que paramos

O GlicoLife v3 já possui uma base funcional de banco de dados, cadastro, autenticação, sessões, middleware e roteamento da API.

O ciclo de login, consulta da sessão e logout foi validado no Postman. O middleware também passou em seis verificações usando um usuário real do banco local.

**Próxima ação:** limpar os dois pontos temporários de diagnóstico, repetir o teste do middleware e iniciar a auditoria de cookies e CSRF.

A publicação continua fora de escopo até a conclusão e validação da versão local.
