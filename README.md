# Mary Modas — Sistema E-commerce (TCC UniALFA)

Sistema web de vendas e gestão da loja **Mary Modas**, feito em **PHP 8.2 + Laravel 12 + MySQL/MariaDB** para rodar no **XAMPP**.

Equipe: Isabely Novais Dalava, Alexandre Aizono e Felipe do Nascimento · Orientador: Prof. Cristiano

> ⚠️ **Use o Laravel 12, não o 13.** O XAMPP vem com PHP 8.2, e o Laravel 13 exige PHP 8.3 ou superior.
> Por isso o `composer.json` está travado em `platform.php = 8.2.12`. Não usem `laravel new`.

---

## 1. O que instalar (uma vez só)

| Programa | Para quê |
|---|---|
| [XAMPP 8.2.x](https://www.apachefriends.org/) | PHP 8.2 + MariaDB/MySQL + phpMyAdmin |
| [Composer](https://getcomposer.org/download/) | Baixa o Laravel e as bibliotecas (aponte para `C:\xampp\php\php.exe` na instalação) |
| [Git](https://git-scm.com/) | Versionamento |
| VS Code (recomendado) | Extensões: *Laravel* e *PHP Intelephense* |

O **PHP já vem no XAMPP** e o **Laravel não se instala no computador**: o Composer baixa ele dentro do projeto.

## 2. Preparar o PHP do XAMPP

1. Adicione `C:\xampp\php` na variável de ambiente **PATH** do Windows e abra um terminal novo.
   ```
   php -v
   ```
   **Esperado:** `PHP 8.2.12 (cli) ...`

2. Abra `C:\xampp\php\php.ini` e confira se estas linhas **não** começam com `;` (se tiver, apague o `;` e salve):
   ```
   extension=zip
   extension=fileinfo
   extension=pdo_mysql
   extension=mbstring
   extension=openssl
   extension=gd
   ```
   **Esperado:** `php -m` lista `zip`, `fileinfo`, `pdo_mysql` e `gd` (o `gd` é usado pelos testes de imagem de produto).

## 3. Rodar o projeto

1. **Clonar o repositório**
   ```
   git clone https://github.com/bel1020304050/TCC26.git
   cd TCC26
   ```

2. **Instalar as dependências**
   ```
   composer install
   ```
   **Esperado:** a pasta `vendor/` é criada e aparece `Discovering packages ... DONE`.

3. **Criar o arquivo de configuração e a chave**
   ```
   copy .env.example .env
   php artisan key:generate
   ```
   **Esperado:** `Application key set successfully.`

4. **Criar o banco**: inicie **Apache** e **MySQL** no painel do XAMPP, abra `http://localhost/phpmyadmin`, clique em **Novo** e crie o banco `mary_modas` com agrupamento `utf8mb4_unicode_ci`.

5. **Criar as tabelas e os usuários iniciais**
   ```
   php artisan migrate --seed
   ```
   **Esperado:** as migrations aparecem como `DONE` e os seeders `AdminSeeder` e `UsuariosTesteSeeder` rodam.

6. **Liberar a pasta de imagens dos produtos** (só uma vez)
   ```
   php artisan storage:link
   ```
   **Esperado:** `The [public/storage] link has been connected to [storage/app/public].`
   Sem isso, as imagens enviadas no cadastro de produtos não aparecem na vitrine.

7. **Subir o servidor**
   ```
   php artisan serve
   ```
   **Esperado:** `Server running on [http://127.0.0.1:8000]`. Abra esse endereço no navegador.

> Prefere usar o Apache do XAMPP em vez do `artisan serve`? Aponte o navegador para
> `http://localhost/TCC26/public` (com o projeto dentro de `C:\xampp\htdocs`) e ajuste `APP_URL` no `.env`.

## 4. Usuários de teste

Criados pelo `php artisan migrate --seed` (os de teste só em `APP_ENV=local`):

| Perfil | E-mail | Senha | Observação |
|---|---|---|---|
| Administrador | mary@marymodas.com.br | `admin12345` | Dona da loja (vem do `SEED_ADMIN_PASSWORD` do `.env`) |
| Administrador | bruno@marymodas.com.br | `admin12345` | Gerente |
| Funcionário | rafael@marymodas.com.br | `func12345` | Subgerente |
| Funcionário inativo | juliana@marymodas.com.br | `func12345` | Não consegue entrar (teste) |
| Cliente | ana@email.com | `cliente123` | Endereço completo em Umuarama/PR |

O seeder também cria o **catálogo de exemplo** dos wireframes: categorias (Vestidos, Blusas, Saias, Acessórios e Jaquetas inativa),
4 fornecedores com CNPJ válido e 7 produtos. Dica de teste: tente inativar **Vestidos** (tem produtos ativos → bloqueia)
e depois **Acessórios** (sem produtos → inativa).

## 5. O que já funciona

### Fases 0 e 1 — base, login e perfis

- Layouts da **loja** (topo rosa) e do **painel** (menu lateral), com Bootstrap 5 via CDN — sem precisar de Node.
- **Login e logout** únicos para os 3 perfis, com redirecionamento por perfil (story 3.13).
  - Mensagem genérica de erro, bloqueio de contas inativas/encerradas e bloqueio por 1 minuto após 5 tentativas erradas.
- **Criar conta do cliente** com endereço completo e **ViaCEP** (story 3.9).
  - Se a ViaCEP não responder em 5 s, os campos ficam livres para preenchimento manual.
  - Um campo `perfil` enviado pelo navegador é ignorado: o cadastro público sempre cria **Cliente**.
- **Controle de acesso por perfil** (seção 2.2): Cliente não entra no painel; Funcionário não vê nem acessa **Usuários** (nem pela URL).
- Quem é **inativado durante a sessão** é desconectado na próxima página.
- Mensagens de validação em **português**.

### Fase 2 — Categorias e Fornecedores (stories 3.14–3.17 e 3.26–3.29)
- Listagem com **pesquisa** (fornecedor também por CPF/CNPJ, com ou sem pontuação), filtro de status e paginação.
- **Cadastrar e editar** com validação: nome de categoria único (sem diferenciar maiúsculas e incluindo inativas); CPF/CNPJ opcional, com dígitos verificadores e único.
- **Inativar/Reativar** com janela de confirmação (exclusão lógica). Categoria ou fornecedor com **produtos ativos não pode ser inativado**.
- **Permissões**: Administrador faz tudo; Funcionário só consulta (sem botões e barrado pela URL); Cliente não acessa.
- **Auditoria**: criar, editar, inativar e reativar ficam registrados na tabela `auditorias` (quem, o quê e quando).
- A tabela **`produtos`** já existe (necessária para a regra acima), com produtos de exemplo.

### Fase 3 — Produtos e vitrine (stories 3.1–3.4)
- **Vitrine** na página inicial, aberta para visitantes: só produtos **ativos, com estoque e de categoria ativa**.
  Pesquisa por parte do nome (sem diferenciar maiúsculas), filtros de categoria, tamanho e cor, ordenação por preço/recentes e **20 produtos por página**.
- **Painel de Produtos**: listagem com filtros, **cadastro com imagem** (JPG/PNG até 2 MB, com prévia), edição e reposição de estoque.
  Preço aceita o formato brasileiro (`129,90`). Não pode repetir **Nome + Categoria + Tamanho + Cor**.
- Só categorias e fornecedores **ativos** aparecem para seleção.
- **Permissões**: Funcionário cadastra e edita; só o **Administrador** inativa/reativa.
- **Melhoria**: em Categorias e Fornecedores com produtos ativos, a janela de inativação mostra só o aviso e o botão **Entendi**.

### Fase 4 — Usuários internos e permissões (stories 3.5–3.8)
- Tela **Usuários** (só Administrador): lista só a equipe (clientes não aparecem), com pesquisa por nome/e-mail e filtros de perfil e status.
- **Cadastrar e editar** Funcionário ou Administrador, com cargo (só informativo), telefone e senha (mín. 8, com confirmação).
  Na edição, senha em branco mantém a atual. O e-mail é único no sistema todo, inclusive entre clientes.
- **Inativar/Reativar**: quem é inativado perde o acesso na hora (as sessões abertas dele são apagadas).
- Travas de segurança: ninguém altera o **próprio perfil** nem **inativa a própria conta**, e o sistema nunca fica sem pelo menos **um Administrador ativo**.
- **Minha conta** da equipe: alterar nome, telefone e senha (pedindo a senha atual).
- Auditoria registra criar, editar (com perfil antes/depois), inativar, reativar e troca de senha.

## 6. Ordem de desenvolvimento

| Fase | Entrega | Stories | Situação |
|---|---|---|---|
| 0 e 1 | Base, login e perfis | 3.9, 3.13 | ✅ Pronta |
| 2 | Categorias e Fornecedores | 3.14–3.17, 3.26–3.29 | ✅ Pronta |
| 3 | Produtos e vitrine | 3.1–3.4 | ✅ Pronta |
| 4 | Usuários internos e permissões | 3.5–3.8 | ✅ Pronta |
| 5 | Carrinho e Pedidos | 3.30, 3.18, 3.19 | A fazer |
| 6 | Processo de Pagamento ⭐ | 3.31 | A fazer |
| 7 | Andamento do pedido | 3.20, 3.21 | A fazer |
| 8 | Promoções | 3.22–3.25 | A fazer |
| 9 | Conta do cliente (consultar, editar, encerrar) | 3.10–3.12 | A fazer |
| 10 | Relatórios | 3.32–3.34 | A fazer |

## 7. Onde fica cada coisa

```
app/Http/Controllers/Auth/      Login, logout e criar conta
app/Http/Controllers/Loja/      Vitrine (área pública)
app/Http/Controllers/Painel/    Telas do painel administrativo
app/Http/Middleware/            VerificaPerfil (perfil:admin,...) e EncerraSessaoInativa
app/Http/Requests/              Validações (Form Requests)
app/Models/                     User, Categoria, Fornecedor, Produto, Auditoria
app/Models/Concerns/TemStatus   Comportamento comum de Ativo/Inativo (exclusão lógica)
app/Policies/                   Quem pode fazer o quê em cada cadastro (Admin x Funcionário)
app/Rules/CpfCnpj.php           Regra de validação de CPF/CNPJ
app/Support/                    Status, Documento (CPF/CNPJ) e Auditoria
config/mary.php                 Configurações próprias do sistema
database/migrations/            Estrutura das tabelas
database/seeders/               Administrador inicial e usuários de teste
lang/pt_BR/                     Mensagens em português
public/css/mary.css             Cores e estilo da Mary Modas
public/js/viacep.js             Preenchimento do endereço pelo CEP
public/img/                     Imagem padrão de produto
storage/app/public/produtos/    Imagens enviadas no cadastro (não vão para o Git)
resources/views/                Telas (Blade)
routes/web.php                  Rotas
tests/Feature/                  Testes automatizados
```

## 8. Comandos úteis

| Comando | O que faz |
|---|---|
| `php artisan serve` | Sobe o site em http://127.0.0.1:8000 |
| `php artisan migrate:fresh --seed` | **Apaga e recria** todas as tabelas com os dados iniciais |
| `php artisan test` | Roda os testes automatizados (109 testes até a Fase 4) |
| `php artisan route:list --except-vendor` | Lista as rotas do sistema |
| `php artisan optimize:clear` | Limpa caches (use se algo "não atualizar") |

## 9. Problemas comuns

| Erro | Causa | Solução |
|---|---|---|
| `could not find driver` | Extensão `pdo_mysql` desligada | Tire o `;` de `extension=pdo_mysql` no `php.ini` |
| `The zip extension and unzip/7z commands are both missing` | Extensão `zip` desligada | Tire o `;` de `extension=zip` no `php.ini` |
| `Your PHP version (8.2.x) does not satisfy ... ^8.3` | Tentou instalar o Laravel 13 | Use este projeto (Laravel 12); não rode `laravel new` |
| `SQLSTATE[HY000] [1049] Unknown database 'mary_modas'` | Banco não criado | Crie o banco no phpMyAdmin (passo 3.4) |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL do XAMPP parado | Clique em **Start** no MySQL |
| `No application encryption key has been specified` | Falta a chave | `php artisan key:generate` |
| Imagem do produto não aparece (ícone quebrado) | Falta o link da pasta de imagens | `php artisan storage:link` |
| Testes falham com `GD extension is not installed` | Extensão `gd` desligada | Tire o `;` de `extension=gd` no `php.ini` |

## 10. Padrão de branches e commits

- Branches: `tipo/descricao` em minúsculas e com hífen — `feature/fase-02-categorias-fornecedores`, `fix/login-conta-inativa`, `docs/readme`.
- Uma branch por fase/módulo, sempre com **Pull Request** para a `main`.
- O `.env` **nunca** vai para o GitHub (já está no `.gitignore`).

> O MVP antigo em PHP puro continua no histórico do Git (commit `f3e3bfa`) caso alguém precise consultar.
