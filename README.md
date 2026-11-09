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
   extension=openssl, não antes de install o composer.
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
| Gerente | bruno@marymodas.com.br | `gerente123` | Gerencia a loja, menos a equipe |
| Atendente/Vendedor | rafael@marymodas.com.br | `func12345` | Produtos e pedidos do dia a dia |
| Atendente/Vendedor inativo | juliana@marymodas.com.br | `func12345` | Não consegue entrar (teste) |
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

### Fase 5 — Carrinho e Pedidos (stories 3.30, 3.18 e 3.19)
- **Carrinho** (só cliente logado): botão **Adicionar ao carrinho** na vitrine, botões − e +, remover item, quantidade disponível e totais
  sempre calculados no servidor. Não deixa passar do estoque nem adicionar produto inativo, sem estoque ou de categoria inativa.
- **Confirmar pedido**: resumo do carrinho e endereço de entrega. Ao confirmar, cria **um único pedido** "Aguardando Pagamento"
  (cliques repetidos não duplicam), copia itens, preços e endereço ("congelados") e **bloqueia o carrinho** ("Em Pagamento").
- **O estoque não é baixado ao criar o pedido**: isso acontece só no pagamento aprovado (Fase 6), como manda a documentação.
- Se faltar estoque na hora de confirmar, o pedido não é criado e o sistema diz qual item ajustar e quanto há disponível.
- **Meus pedidos**: lista do mais recente ao mais antigo e detalhes com itens, valores, endereço, pagamento e **histórico de status**.
  Um cliente não consegue abrir o pedido de outro, nem trocando o número na URL.
- **Painel › Pedidos** (Funcionário e Administrador): todos os pedidos, com pesquisa por nº ou cliente e filtros de período e status.
  O histórico mostra quem fez cada mudança.
- Cupom aparece desativado até a Fase 8; o botão **Pagar** fica desativado até a Fase 6.
- Dados de teste: a cliente **Ana** já tem 2 pedidos antigos (um Entregue e um Cancelado) e um carrinho com 2 itens.

### Fase 6 — Processo de Pagamento ⭐ (processo 3.31)
- Depois de confirmar o pedido, o cliente vai direto para a **tela de pagamento** (etapa 3) e escolhe **PIX, Cartão de Crédito ou Boleto**.
- **Pagamento simulado** (sem cobrança real), funcionando como o ambiente de testes de um provedor:

  | Forma | Como testar | Resultado |
  |---|---|---|
  | PIX | Gerar PIX → **Simular pagamento recebido** | Aprovado |
  | Cartão | Qualquer número que **não** termine em 0000 ou 9999 (ex.: `4111 1111 1111 1111`) | Aprovado na hora |
  | Cartão | Final **0000** (ex.: `5555 4444 3333 0000`) | Recusado: o pedido continua aguardando e dá para tentar de novo |
  | Cartão | Final **9999** | Provedor "fora do ar": mensagem clara, nada muda |
  | Boleto | Gerar boleto → Administrador abre o pedido no painel → **Confirmar compensação do boleto** | Aprovado |

- **Aprovado** = numa única transação: pedido **Pago**, **estoque baixado**, carrinho **Finalizado e limpo**, tela de **Concluído**.
- **Recusado ou Pendente** = o estoque não é baixado e o pedido continua **Aguardando Pagamento**.
- A mesma aprovação recebida duas vezes **baixa o estoque uma vez só** (idempotência). Se faltar estoque na hora da aprovação,
  o pedido é **Cancelado**, o pagamento é **Estornado** e o carrinho volta a ficar editável.
- **Dados do cartão não são salvos**: só a bandeira e os 4 últimos dígitos. Eles também não voltam preenchidos se der erro de validação.
- Toda tentativa fica registrada (Pendente, Aprovado, Recusado ou Estornado) e aparece nos detalhes do pedido.
- O código usa a interface `ProvedorPagamento`: hoje quem responde é o `PagamentoSimulado`. Para usar o Mercado Pago, basta criar
  outra classe e trocar uma linha no `AppServiceProvider` (seção 4.1 da documentação).

### Fase 7 — Andamento do pedido (stories 3.20 e 3.21)
- **Atualizar status** (Funcionário e Administrador): botão na lista e nos detalhes do pedido, oferecendo **só o próximo passo**
  (Pago → Em Separação → Enviado → Entregue). Não deixa pular etapas nem voltar, e "Pago" só vem do pagamento.
- O **histórico** grava status anterior, status novo, data, hora e responsável (e aparece para o cliente).
- **Cancelar pedido** (exclusão lógica, nada é apagado):

  | Status do pedido | Quem pode cancelar | O que acontece |
  |---|---|---|
  | Aguardando Pagamento | Cliente, Funcionário ou Administrador | PIX/boleto em aberto deixa de valer e o carrinho volta a ficar editável |
  | Pago ou Em Separação | **Só o Administrador** | Pagamento **estornado** e itens **devolvidos ao estoque**, numa única transação |
  | Enviado ou Entregue | Ninguém | Bloqueado (troca e devolução ficam fora do MVP) |

- O **motivo** é opcional para o cliente e obrigatório para a equipe. Tudo fica na auditoria.

### Fase 8 — Promoções e cupons (stories 3.22–3.25)
- **Painel › Promoções**: pesquisa por nome ou código e filtro por **situação**: Vigente, Agendada, Expirada ou Inativa.
  A situação é calculada pelas datas no momento da consulta.
- **Cadastrar e editar** (só o Administrador):
  - Campos: modo **Automática** ou **Cupom** (código único), desconto **Percentual (1% a 50%)** ou **Valor fixo (> 0)**,
    período e aplicação **Geral, Categoria ou Produto**.
  - Promoção já usada em pedido não pode mudar modo nem código, e a edição registra os valores antes e depois.
- **Inativar/Reativar** (só o Administrador): a promoção deixa de valer em novas compras, e os pedidos já feitos mantêm o desconto.
  O Funcionário só consulta.
- **Vitrine**: selo laranja e preço promocional para promoções **automáticas** vigentes.
- **Carrinho**: campo de **cupom**. O servidor sempre recalcula e **os descontos não se somam**: vale só o maior
  entre as automáticas e o cupom. Valor fixo nunca deixa o total negativo. Cupom inativado depois sai do carrinho com aviso.
- O **pedido guarda o desconto e o cupom usados**, e o pagamento cobra esse valor congelado.
- Dados de teste: cupom **MARY20** (20%, vigente), **VIP30** (agendado), **Queima de Saias** (15% automática nas saias),
  **Desconto Blusas** (inativa) e **Liquidação Inverno** (expirada).

### Fase 9 — Conta do cliente (stories 3.10–3.12)
- **Minha conta › Meus dados** (menu do nome no topo): nome, e-mail, telefone, endereço completo e "cliente desde".
  A senha nunca aparece. Não há id na URL: a tela sempre mostra a conta de quem está logado.
- **Editar meus dados**: mesmas regras do cadastro, com **ViaCEP** ao trocar o CEP. O e-mail continua único.
  Os pedidos antigos mantêm o endereço que já estava copiado neles.
- **Alterar senha**, pedindo a senha atual.
- **Encerrar minha conta** (exige a senha atual):
  - Bloqueado se houver pedido Aguardando Pagamento, Pago, Em Separação ou Enviado; a mensagem diz qual pedido.
  - Numa única transação: status **Encerrada**, dados pessoais **anonimizados** ("Cliente removido"), todas as sessões derrubadas.
  - Os pedidos ficam registrados, o login é bloqueado e o **e-mail fica livre** para uma conta nova.
  - No painel, o pedido mostra "Cliente removido · Conta encerrada em dd/mm/aaaa".
- Só o próprio cliente encerra a conta: a equipe não tem essa opção.

### Fase 10 — Relatórios (stories 3.32–3.34)

| Relatório | Quem acessa | Filtros | Indicadores |
|---|---|---|---|
| **Vendas** | Só Administrador | Período, status e forma de pagamento | Vendas pagas, faturamento e ticket médio |
| **Produtos / Estoque** | Administrador e Funcionário | Categoria, fornecedor, status, tamanho, cor e "só estoque baixo" | Produtos, unidades e itens com estoque ≤ 10 (em destaque) |
| **Clientes** | Só Administrador | Período de cadastro, com/sem compras e ordem | Clientes no período, com compras pagas e valor total |

- **Venda paga** = Pago, Em Separação, Enviado ou Entregue. Aguardando Pagamento, Cancelado e estornos aparecem na lista, mas não entram no faturamento.
- O período padrão é dos **últimos 30 dias**.
- **Exportar CSV**: abre direto no Excel, com `;` e acentos certos.
- **Exportar PDF**: abre uma versão para impressão e o diálogo do navegador. Basta escolher **"Salvar como PDF"**.
  A tela, o CSV e o PDF têm exatamente as mesmas colunas.
- Relatório de clientes sem telefone e endereço (LGPD). Conta encerrada aparece como "Cliente removido", sem e-mail.
- As rotas e as exportações recusam quem não tem permissão (Cliente, ou Funcionário em Vendas e Clientes).

### Fase 12 — Perfis da equipe (seção 2.2 e stories 3.5–3.8)
- A equipe passa a ter **três perfis**: **Administrador**, **Gerente** e **Atendente/Vendedor**. O campo **Cargo** saiu:
  agora o perfil é a única informação de função, e ele é que define as permissões.
- A migration converte quem era `funcionario` em `atendente`, então ninguém perde o acesso.
- Onde as fases anteriores falam em **Funcionário**, leia **Atendente/Vendedor**. As permissões atuais são estas:

| Ação | Administrador | Gerente | Atendente/Vendedor |
|---|:---:|:---:|:---:|
| Usuários da equipe (cadastrar, editar, inativar) | ✅ | — | — |
| Categorias, Fornecedores e Promoções: cadastrar e editar | ✅ | ✅ | Só consulta |
| Inativar/Reativar categoria, fornecedor, produto e promoção | ✅ | ✅ | — |
| Produtos: cadastrar, editar e repor estoque | ✅ | ✅ | ✅ |
| Pedidos: consultar e avançar o status | ✅ | ✅ | ✅ |
| Cancelar pedido aguardando pagamento | ✅ | ✅ | ✅ |
| Cancelar pedido pago (com estorno) e confirmar boleto | ✅ | ✅ | — |
| Relatórios de Vendas, Produtos/Estoque e Clientes | ✅ | ✅ | ✅ |

- **Correção junto com esta fase**: quando um pedido aguardando pagamento era cancelado e o cliente tentava comprar de novo,
  aparecia o erro *Duplicate entry … pedidos_carrinho_id_unique*. Agora os itens vão para um **carrinho novo** e o antigo fica
  finalizado, ligado ao pedido cancelado.

## 6. Ordem de desenvolvimento

| Fase | Entrega | Stories | Situação |
|---|---|---|---|
| 0 e 1 | Base, login e perfis | 3.9, 3.13 | ✅ Pronta |
| 2 | Categorias e Fornecedores | 3.14–3.17, 3.26–3.29 | ✅ Pronta |
| 3 | Produtos e vitrine | 3.1–3.4 | ✅ Pronta |
| 4 | Usuários internos e permissões | 3.5–3.8 | ✅ Pronta |
| 5 | Carrinho e Pedidos | 3.30, 3.18, 3.19 | ✅ Pronta |
| 6 | Processo de Pagamento ⭐ | 3.31 | ✅ Pronta |
| 7 | Andamento do pedido | 3.20, 3.21 | ✅ Pronta |
| 8 | Promoções | 3.22–3.25 | ✅ Pronta |
| 9 | Conta do cliente (consultar, editar, encerrar) | 3.10–3.12 | ✅ Pronta |
| 10 | Relatórios | 3.32–3.34 | ✅ Pronta |
| 11 | Revisão de comentários e tela inicial do painel | — | ✅ Pronta |
| 12 | Perfis (Administrador, Gerente, Atendente/Vendedor) | 2.2, 3.5–3.8 | ✅ Pronta |
| 13 | Documentos e endereços (fornecedor PF/PJ, CPF do cliente, CEP) | 3.9–3.12, 3.26–3.29 | A fazer |
| 14 | PDF com dompdf | 3.32–3.34 | A fazer |
| 15 | Auditoria (motivo obrigatório e relatório) | 3.35 | A fazer |
| 16 | Melhorias na loja (página do produto, cupons visíveis, rastreio) | 3.2, 3.20, 3.30 | A fazer |
| 17 | Pagamento no sandbox do Asaas | 3.31 | A fazer |
| 18 | Fechamento (documentação e apresentação) | — | A fazer |

### Fase 11 — Revisão de comentários
- Os comentários do código explicam **o que o trecho faz e por quê**, com rótulos: `Validação:`, `Regra:`, `Segurança:`, `Banco:` e `LGPD:`.
  Não há mais referências a story, RNF ou seção no código; a ligação com a documentação fica na tabela da seção 7.
- Nos testes, cada caso de teste do documento continua marcado com o código dele (ex.: `// CT-3-18-01`).
- A **página inicial do painel** mostra o resumo da loja: pedidos aguardando pagamento, pedidos para separar,
  produtos com estoque baixo, vendas do mês e os últimos pedidos. Cada card é um atalho para a tela certa.

## 7. Onde fica cada coisa

### Story → arquivos (rastreabilidade)

| Story | Funcionalidade | Onde está no código |
|---|---|---|
| 3.1, 3.3, 3.4 | Cadastrar, editar e inativar produto | `Painel/ProdutoController`, `Requests/ProdutoRequest`, `Policies/ProdutoPolicy`, `views/painel/produtos` |
| 3.2 | Vitrine (pesquisar e filtrar produtos) | `Loja/VitrineController`, `Models/Produto` (`scopeNaVitrine`), `views/loja/vitrine` |
| 3.5–3.8 | Usuários da equipe | `Painel/UsuarioController`, `Requests/UsuarioRequest`, `Policies/UserPolicy`, `Middleware/EncerraSessaoInativa` |
| 3.9 | Criar conta (cliente) | `Auth/RegisterController`, `Requests/CriarContaRequest`, `public/js/viacep.js` |
| 3.10, 3.11 | Consultar e editar meus dados | `Loja/ContaController`, `Requests/EditarContaRequest`, `views/loja/conta` |
| 3.12 | Encerrar minha conta (LGPD) | `Services/EncerrarConta`, `Loja/ContaController@destroy` |
| 3.13 | Login e logout | `Auth/LoginController`, `Middleware/VerificaPerfil` |
| 3.14–3.17 | Categorias | `Painel/CategoriaController`, `Requests/CategoriaRequest`, `Policies/CategoriaPolicy` |
| 3.18 | Criar pedido | `Loja/PedidoController@confirmar` e `@store`, `Models/Pedido`, `Models/PedidoItem` |
| 3.19 | Consultar pedidos | `Loja/PedidoController@index`/`@show`, `Painel/PedidoController`, `Policies/PedidoPolicy` |
| 3.20, 3.21 | Atualizar status e cancelar pedido | `Services/AndamentoPedido`, `Painel/PedidoController`, `Loja/PedidoController@cancelar` |
| 3.22–3.25 | Promoções e cupons | `Painel/PromocaoController`, `Requests/PromocaoRequest`, `Models/Promocao` |
| 3.26–3.29 | Fornecedores | `Painel/FornecedorController`, `Requests/FornecedorRequest`, `Rules/CpfCnpj` |
| 3.30 | Carrinho e cupom | `Loja/CarrinhoController`, `Models/Carrinho` (`totais()`) |
| 3.31 ⭐ | Processo de pagamento | `Services/FinalizarCompra`, `Loja/PagamentoController`, `Pagamentos/` |
| 3.32–3.34 | Relatórios | `Painel/RelatorioController`, `Relatorios/RelatorioVendas`, `RelatorioEstoque`, `RelatorioClientes` |

Os testes de cada story ficam em `tests/Feature`, e cada caso de teste do documento é marcado com o código dele (ex.: `CT-3-18-01`).

### Pastas

```
app/Http/Controllers/Auth/      Login, logout e criar conta
app/Http/Controllers/Loja/      Vitrine, carrinho e pedidos do cliente
app/Http/Controllers/Painel/    Telas do painel administrativo
app/Http/Middleware/            VerificaPerfil (perfil:admin,...) e EncerraSessaoInativa
app/Http/Requests/              Validações (Form Requests)
app/Models/                     User, Categoria, Fornecedor, Produto, Auditoria, Carrinho, Pedido
app/Models/Concerns/TemStatus   Comportamento comum de Ativo/Inativo (exclusão lógica)
app/Pagamentos/                 Provedor de pagamento (interface + simulado)
app/Policies/                   Quem pode fazer o quê em cada cadastro (Administrador, Gerente e Atendente)
app/Relatorios/                 Os 3 relatórios (consulta, colunas, indicadores)
app/Services/FinalizarCompra    Processo de pagamento (pagar, aprovar, baixar estoque, finalizar carrinho)
app/Services/AndamentoPedido    Atualizar status e cancelar pedido
app/Services/EncerrarConta      Encerrar a conta do cliente com anonimização
app/Rules/CpfCnpj.php           Regra de validação de CPF/CNPJ
app/Support/                    Status, Documento (CPF/CNPJ) e Auditoria
config/mary.php                 Configurações próprias do sistema
database/migrations/            Estrutura das tabelas
database/seeders/               Administrador inicial, usuários, catálogo e pedidos de teste
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
| `php artisan test` | Roda os testes automatizados (225 testes até a Fase 12) |
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
