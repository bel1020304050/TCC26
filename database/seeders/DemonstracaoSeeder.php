<?php

namespace Database\Seeders;

use App\Models\Carrinho;
use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;
use App\Pagamentos\PagamentoException;
use App\Pagamentos\PagamentoSimulado;
use App\Services\AndamentoPedido;
use App\Services\FinalizarCompra;
use App\Support\Auditoria;
use App\Support\Status;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SplPriorityQueue;

/**
 * Dados de DEMONSTRAÇÃO para a apresentação: 6 meses de loja funcionando (junho a novembro de 2026).
 *
 *   php artisan migrate:fresh --seed --seeder=DemonstracaoSeeder
 *
 * Diferente dos outros seeders, aqui nada é escrito "na mão" no histórico: cada acontecimento roda as MESMAS
 * regras do sistema (FinalizarCompra, AndamentoPedido e Auditoria) com o relógio voltado para a data dele
 * (Carbon::setTestNow). Assim estoque, pagamentos, histórico dos pedidos e auditoria ficam coerentes entre si,
 * como se a equipe e os clientes tivessem usado o sistema de verdade.
 *
 * O pagamento é sempre o simulado (nenhuma cobrança vai para o Asaas), qualquer que seja o .env.
 * Os sorteios usam semente fixa: rodar de novo gera exatamente os mesmos dados.
 */
class DemonstracaoSeeder extends Seeder
{
    private FinalizarCompra $compra;

    private AndamentoPedido $andamento;

    private Carbon $agora;

    /** Fila de acontecimentos em ordem de data (o mais antigo sai primeiro) */
    private SplPriorityQueue $fila;

    private int $sequencia = 0;

    /** @var array<string, User> */
    private array $equipe = [];

    /** @var array<int, User> */
    private array $clientes = [];

    /** @var array<string, Categoria> */
    private array $categorias = [];

    /** @var array<string, Fornecedor> */
    private array $fornecedores = [];

    /** @var array<string, array<int, Produto>> variações de cada produto (pelo nome) */
    private array $produtos = [];

    // Variações que terminam com estoque baixo (a reposição de novembro chega com pouca peça)
    private const ESTOQUE_BAIXO = [
        'Blusa Babado Rosa|M' => 3, 'Vestido Midi Floral|P' => 2, 'Shorts Jeans Destroyed|38' => 2,
        'Jaqueta Bomber Sarja|P' => 1, 'Moletom Canguru com Capuz|G' => 3, 'Calça Jeans Flare|40' => 2,
    ];

    public function run(): void
    {
        if (Pedido::exists()) {
            $this->command?->warn('Já existem pedidos no banco. Rode: php artisan migrate:fresh --seed --seeder=DemonstracaoSeeder');

            return;
        }

        mt_srand(20261124);
        $this->agora = Carbon::now();
        $provedor = new PagamentoSimulado; // nunca o Asaas: são dados de demonstração
        $this->compra = new FinalizarCompra($provedor);
        $this->andamento = new AndamentoPedido($provedor);
        $this->fila = new SplPriorityQueue;
        $this->fila->setExtractFlags(SplPriorityQueue::EXTR_DATA);

        $this->call([AdminSeeder::class, UsuariosTesteSeeder::class]);

        $this->equipe();
        $this->catalogo();
        $this->clientes();
        $this->promocoes();
        $this->reposicoesEAjustes();
        $this->pedidos();

        // Executa todos os acontecimentos na ordem das datas
        while (! $this->fila->isEmpty()) {
            ($this->fila->extract())();
        }

        Carbon::setTestNow();
        Auth::forgetUser();
        $this->command?->info(sprintf('Demonstração pronta: %d pedidos, %d produtos, %d clientes, %d registros de auditoria.',
            Pedido::count(), Produto::count(), count($this->clientes), DB::table('auditorias')->count()));
    }

    // ------------------------------------------------------------------ agenda

    /** Agenda um acontecimento: na hora marcada, o relógio do sistema volta para essa data e "quem" fica logado */
    private function em(Carbon|string $quando, ?User $quem, Closure $acao): void
    {
        $quando = $quando instanceof Carbon ? $quando->copy() : Carbon::parse($quando);
        if ($quando->greaterThan($this->agora)) {
            return; // nada no futuro
        }
        $ordem = [-$quando->getTimestamp(), -(++$this->sequencia)]; // mesma data: na ordem em que foi agendado
        $this->fila->insert(function () use ($quando, $quem, $acao) {
            Carbon::setTestNow($quando);
            $quem ? Auth::setUser($quem) : Auth::forgetUser();
            $acao();
        }, $ordem);
    }

    // Dia útil às h:m (pula sábado à tarde e domingo para a equipe)
    private function expediente(Carbon $data, int $hora, int $minuto = 0): Carbon
    {
        $d = $data->copy()->setTime($hora, $minuto);
        while ($d->isSunday()) {
            $d->addDay();
        }

        return $d;
    }

    private function sorteio(array $lista): mixed
    {
        return $lista[mt_rand(0, count($lista) - 1)];
    }

    private function chance(int $porcento): bool
    {
        return mt_rand(1, 100) <= $porcento;
    }

    // ------------------------------------------------------------------ equipe

    private function equipe(): void
    {
        $this->equipe = [
            'mary' => User::where('email', 'mary@marymodas.com.br')->firstOrFail(),
            'bruno' => User::where('email', 'bruno@marymodas.com.br')->firstOrFail(),
            'rafael' => User::where('email', 'rafael@marymodas.com.br')->firstOrFail(),
            'juliana' => User::where('email', 'juliana@marymodas.com.br')->firstOrFail(),
        ];
        // As contas da equipe "existem" desde o começo de maio
        User::whereIn('id', collect($this->equipe)->pluck('id'))->update(['created_at' => '2026-05-04 09:00:00']);
        $this->equipe['mary']->forceFill(['created_at' => '2026-05-04 08:00:00'])->save();

        $mary = $this->equipe['mary'];
        foreach (['bruno', 'rafael', 'juliana'] as $i => $quem) {
            $u = $this->equipe[$quem];
            $this->em("2026-05-04 09:1{$i}", $mary, fn () => Auditoria::registrar('criou', $u, ['perfil' => $u->perfil]));
        }
        // Juliana: contratada em maio, desligada em agosto
        $this->em('2026-08-29 17:40', $mary, fn () => Auditoria::registrar('inativou', $this->equipe['juliana'], motivo: 'Contrato de experiência encerrado'));
        // Rafael esqueceu a senha: o gerente gera uma provisória e ele troca no primeiro acesso
        $this->em('2026-10-05 08:52', $this->equipe['bruno'], fn () => Auditoria::registrar('redefiniu_senha', $this->equipe['rafael']));
        $this->em('2026-10-05 09:03', $this->equipe['rafael'], fn () => Auditoria::registrar('trocou_senha_provisoria', $this->equipe['rafael']));
        // Bruno ganha um telefone novo da loja
        $this->em('2026-09-02 14:20', $mary, function () {
            $bruno = $this->equipe['bruno'];
            $antes = $bruno->only(['name', 'email', 'telefone', 'perfil']);
            $bruno->telefone = '(44) 99777-4545';
            $bruno->save();
            Auditoria::registrar('editou', $bruno, ['antes' => $antes, 'depois' => $bruno->only(['name', 'email', 'telefone', 'perfil'])]);
        });
        $this->em('2026-07-14 18:05', $mary, fn () => Auditoria::registrar('alterou_senha', $mary));
    }

    // ------------------------------------------------------------------ catálogo

    private function catalogo(): void
    {
        $mary = $this->equipe['mary'];
        $bruno = $this->equipe['bruno'];
        $rafael = $this->equipe['rafael'];

        // [nome, descrição, data]
        $categorias = [
            ['Vestidos', 'Vestidos curtos, midi e longos', '2026-05-05 10:00'],
            ['Blusas', 'Blusas femininas de tecido plano', '2026-05-05 10:02'],
            ['Camisetas', 'Camisetas básicas de malha', '2026-05-05 10:04'],
            ['Calças', 'Calças jeans e de alfaiataria', '2026-05-05 10:06'],
            ['Shorts', 'Shorts e bermudas', '2026-05-05 10:08'],
            ['Saias', 'Saias midi, longas e jeans', '2026-05-05 10:10'],
            ['Conjuntos', 'Conjuntos de duas peças', '2026-05-05 10:12'],
            ['Acessórios', 'Bolsas, cintos e bijuterias', '2026-05-05 10:14'],
            ['Moda Praia', 'Biquínis, maiôs e saídas de praia', '2026-05-05 10:16'],
            ['Casacos e Jaquetas', 'Coleção de inverno: jaquetas e casacos', '2026-06-01 09:30'],
            ['Moletons', 'Moletons e blusões', '2026-06-01 09:32'],
        ];
        foreach ($categorias as [$nome, $descricao, $quando]) {
            $this->categorias[$nome] = $categoria = new Categoria(['nome' => $nome, 'descricao' => $descricao]);
            $this->em($quando, $mary, function () use ($categoria) {
                $categoria->save();
                Auditoria::registrar('criou', $categoria);
            });
        }
        // Renomeada quando chegaram as bermudas
        $this->em('2026-07-20 11:15', $bruno, function () {
            $c = $this->categorias['Shorts'];
            $antes = $c->only(['nome', 'descricao']);
            $c->nome = 'Shorts e Bermudas';
            $c->save();
            Auditoria::registrar('editou', $c, ['antes' => $antes, 'depois' => $c->only(['nome', 'descricao'])]);
        });
        $this->em('2026-09-10 16:30', $mary, fn () => $this->inativar($this->categorias['Acessórios'], 'Linha de acessórios suspensa até fechar com um novo fornecedor'));
        $this->em('2026-08-03 10:00', $bruno, fn () => $this->inativar($this->categorias['Conjuntos'], 'Sem peças em estoque nesta coleção'));
        $this->em('2026-10-26 09:20', $bruno, fn () => $this->reativar($this->categorias['Conjuntos'], 'Coleção de verão com conjuntos confirmada pelo fornecedor'));

        // [chave, nome, tipo, base do documento, telefone, e-mail, [cep, rua, número, complemento, bairro, cidade, uf], data]
        $fornecedores = [
            ['aurora', 'Confecções Aurora Ltda', Fornecedor::JURIDICA, '231457890001', '(44) 3622-1000', 'contato@confeccoesaurora.com.br',
                ['87501-120', 'Avenida Paraná', '3200', 'Sala 4', 'Centro', 'Umuarama', 'PR'], '2026-05-06 14:00'],
            ['denim', 'Denim Paraná Indústria Têxtil Ltda', Fornecedor::JURIDICA, '384512670001', '(44) 3629-4400', 'vendas@denimparana.com.br',
                ['87200-020', 'Avenida América', '1450', null, 'Zona 01', 'Cianorte', 'PR'], '2026-05-06 14:10'],
            ['noroeste', 'Malharia Noroeste Ltda', Fornecedor::JURIDICA, '502938170001', '(44) 3225-7800', 'comercial@malharianoroeste.com.br',
                ['87013-230', 'Avenida Brasil', '5320', 'Galpão 2', 'Zona 05', 'Maringá', 'PR'], '2026-05-06 14:20'],
            ['outono', 'Outono Sul Confecções Ltda', Fornecedor::JURIDICA, '617283940001', '(43) 3337-2200', 'pedidos@outonosul.com.br',
                ['86020-000', 'Rua Pernambuco', '880', null, 'Centro', 'Londrina', 'PR'], '2026-06-01 09:40'],
            ['claudia', 'Cláudia Mendes Costura', Fornecedor::FISICA, '418273650', '(44) 99812-3344', 'claudia.costura@email.com',
                ['87504-210', 'Rua Piauí', '77', 'Casa dos fundos', 'Jardim Alvorada', 'Umuarama', 'PR'], '2026-05-07 15:30'],
        ];
        foreach ($fornecedores as [$chave, $nome, $tipo, $base, $telefone, $email, $endereco, $quando]) {
            $documento = $tipo === Fornecedor::JURIDICA ? self::cnpj($base) : self::cpf($base);
            $this->fornecedores[$chave] = $f = new Fornecedor(['nome' => $nome, 'tipo_pessoa' => $tipo, 'documento' => $documento,
                'telefone' => $telefone, 'email' => $email] + array_combine(['cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'], $endereco));
            $this->em($quando, $bruno, function () use ($f) {
                $f->save();
                Auditoria::registrar('criou', $f);
            });
        }
        $this->em('2026-08-18 10:45', $bruno, function () {
            $f = $this->fornecedores['denim'];
            $campos = ['nome', 'tipo_pessoa', 'documento', 'telefone', 'email', 'cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'];
            $antes = $f->only($campos);
            $f->telefone = '(44) 3629-4410';
            $f->email = 'comercial@denimparana.com.br';
            $f->save();
            Auditoria::registrar('editou', $f, ['antes' => $antes, 'depois' => $f->only($campos)]);
        });
        $this->em('2026-10-14 17:10', $mary, fn () => $this->inativar($this->fornecedores['claudia'], 'Costureira deixou de aceitar encomendas novas'));

        // [nome, categoria, fornecedor, imagem, cor, preço, [tamanho => estoque inicial], descrição, data, quem cadastrou]
        $produtos = [
            ['Blusa Babado Rosa', 'Blusas', 'aurora', 'blusa-babado-rosa', 'Rosa', 89.90, ['P' => 10, 'M' => 12, 'G' => 10, 'GG' => 6],
                'Blusa de crepe com gola V e babados, mangas 3/4 bufantes com punho franzido e botões frontais. Leve e elegante para o trabalho ou o fim de semana.',
                '2026-05-08 09:30', 'rafael'],
            ['Vestido Midi Floral', 'Vestidos', 'aurora', 'vestido-midi-floral', 'Off-white floral', 189.90, ['P' => 8, 'M' => 10, 'G' => 8],
                'Vestido midi de alças finas com estampa floral, cintura franzida (lastex) e saia em camadas. Fresco e confortável para os dias quentes.',
                '2026-05-08 09:40', 'rafael'],
            ['Calça Jeans Flare', 'Calças', 'denim', 'calca-jeans-flare', 'Azul médio', 169.90, ['36' => 8, '38' => 12, '40' => 12, '42' => 8, '44' => 5],
                'Calça jeans flare de cintura alta, lavagem média e elastano para mais conforto. Alonga a silhueta e combina com tênis ou salto.',
                '2026-05-08 10:00', 'rafael'],
            ['Shorts Jeans Destroyed', 'Shorts', 'denim', 'shorts-jeans-destroyed', 'Azul claro', 99.90, ['36' => 8, '38' => 10, '40' => 10, '42' => 6],
                'Shorts jeans de cintura alta com puídos e barra desfiada. Lavagem clara, perfeito para o verão.',
                '2026-05-08 10:10', 'rafael'],
            ['Camiseta Básica Gola Redonda', 'Camisetas', 'noroeste', 'camiseta-basica-azul-marinho', 'Azul-marinho', 59.90, ['P' => 15, 'M' => 20, 'G' => 18, 'GG' => 10],
                'Camiseta de malha 100% algodão, gola redonda e caimento reto. A peça coringa do guarda-roupa.',
                '2026-05-08 10:20', 'rafael'],
            ['Jaqueta Bomber Sarja', 'Casacos e Jaquetas', 'outono', 'jaqueta-bomber-verde', 'Verde militar', 249.90, ['P' => 5, 'M' => 8, 'G' => 8, 'GG' => 4],
                'Jaqueta bomber em sarja de algodão com gola alta, zíper frontal, bolsos laterais e punhos em ribana.',
                '2026-06-02 10:00', 'bruno'],
            ['Moletom Canguru com Capuz', 'Moletons', 'noroeste', 'moletom-canguru-azul-marinho', 'Azul-marinho', 179.90, ['P' => 6, 'M' => 10, 'G' => 10, 'GG' => 6],
                'Moletom flanelado por dentro, com capuz ajustável e bolso canguru. Quentinho para as noites frias.',
                '2026-06-02 10:20', 'bruno'],
            ['Bermuda Sarja com Elástico', 'Shorts', 'outono', 'bermuda-sarja-verde', 'Verde militar', 109.90, ['P' => 6, 'M' => 10, 'G' => 10, 'GG' => 6],
                'Bermuda de sarja com cós de elástico e cordão, bolsos faca. Confortável para o dia a dia.',
                '2026-07-20 11:30', 'bruno'],
        ];
        foreach ($produtos as [$nome, $categoria, $fornecedor, $imagem, $cor, $preco, $tamanhos, $descricao, $quando, $quem]) {
            $i = 0;
            foreach ($tamanhos as $tamanho => $estoque) {
                $p = new Produto(['nome' => $nome, 'descricao' => $descricao, 'preco' => $preco, 'estoque' => $estoque,
                    'tamanho' => (string) $tamanho, 'cor' => $cor]);
                $this->produtos[$nome][] = $p;
                $this->em(Carbon::parse($quando)->addMinutes(2 * $i++), $this->equipe[$quem], function () use ($p, $categoria, $fornecedor, $imagem) {
                    $p->categoria_id = $this->categorias[$categoria]->id;
                    $p->fornecedor_id = $this->fornecedores[$fornecedor]->id;
                    // Cada variação tem o seu arquivo (trocar a foto de uma não apaga a das outras)
                    $arquivo = 'produtos/'.$imagem.'-'.mb_strtolower($p->tamanho).'.jpg';
                    Storage::disk('public')->put($arquivo, file_get_contents(database_path("seeders/imagens/{$imagem}.jpg")));
                    $p->imagem = $arquivo;
                    $p->save();
                    Auditoria::registrar('criou', $p);
                });
            }
        }
        // Preço do vestido baixou na virada da estação
        $this->em('2026-09-21 09:10', $bruno, function () {
            foreach ($this->produtos['Vestido Midi Floral'] as $p) {
                $this->editarProduto($p, ['preco' => 169.90]);
            }
        });
        // GG da jaqueta saiu de linha
        $this->em('2026-10-20 15:00', $bruno, fn () => $this->inativar($this->variacao('Jaqueta Bomber Sarja', 'GG'), 'Tamanho GG descontinuado pelo fornecedor'));
    }

    private function variacao(string $nome, string $tamanho): Produto
    {
        return collect($this->produtos[$nome])->firstOrFail(fn (Produto $p) => $p->tamanho === $tamanho);
    }

    private function editarProduto(Produto $p, array $mudancas): void
    {
        $p->refresh();
        $campos = ['nome', 'descricao', 'categoria_id', 'fornecedor_id', 'preco', 'estoque', 'tamanho', 'cor'];
        $antes = $p->only($campos);
        $p->fill($mudancas)->save();
        Auditoria::registrar('editou', $p, ['antes' => $antes, 'depois' => $p->only($campos)]);
    }

    private function inativar($registro, string $motivo): void
    {
        $registro->refresh();
        $registro->status = Status::INATIVO;
        $registro->save();
        Auditoria::registrar('inativou', $registro, motivo: $motivo);
    }

    private function reativar($registro, string $motivo): void
    {
        $registro->refresh();
        $registro->status = Status::ATIVO;
        $registro->save();
        Auditoria::registrar('reativou', $registro, motivo: $motivo);
    }

    // Reposição de estoque no começo de cada mês (atendente edita o estoque das variações que estão acabando)
    private function reposicoesEAjustes(): void
    {
        foreach (['2026-07-01', '2026-08-03', '2026-09-01', '2026-10-01', '2026-11-03'] as $dia) {
            $this->em($this->expediente(Carbon::parse($dia), 8, 40), $this->equipe['rafael'], function () use ($dia) {
                $novembro = str_starts_with($dia, '2026-11');
                foreach ($this->produtos as $nome => $variacoes) {
                    foreach ($variacoes as $p) {
                        $p->refresh();
                        if (! $p->exists || $p->status !== Status::ATIVO) {
                            continue;
                        }
                        $chave = "{$nome}|{$p->tamanho}";
                        if ($novembro && isset(self::ESTOQUE_BAIXO[$chave])) {
                            // Fornecedor mandou pouca peça: fica com estoque baixo no relatório
                            if ($p->estoque !== self::ESTOQUE_BAIXO[$chave]) {
                                $this->editarProduto($p, ['estoque' => self::ESTOQUE_BAIXO[$chave]]);
                            }
                        } elseif ($p->estoque <= 4) {
                            $this->editarProduto($p, ['estoque' => $p->estoque + mt_rand(6, 12)]);
                        }
                    }
                }
            });
        }
    }

    // ------------------------------------------------------------------ clientes

    private function clientes(): void
    {
        $ana = User::where('email', 'ana@email.com')->firstOrFail();
        $ana->forceFill(['created_at' => '2026-05-20 19:12:00'])->save();
        $this->clientes[] = $ana;

        // [nome, e-mail, base do CPF, telefone, [cep, rua, número, complemento, bairro], data do cadastro]
        $novos = [
            ['Beatriz Oliveira', 'beatriz.oliveira@email.com', '305817264', '(44) 99812-1001', ['87501-030', 'Rua Ministro Oliveira Salazar', '2150', null, 'Centro'], '2026-05-28 20:40'],
            ['Carla Mendes', 'carla.mendes@email.com', '412093758', '(44) 99734-2210', ['87502-070', 'Avenida Rio Branco', '3480', 'Apto 12', 'Zona II'], '2026-06-03 13:05'],
            ['Daniela Ferreira', 'dani.ferreira@email.com', '527361904', '(44) 99145-7788', ['87505-160', 'Rua Perdoncini', '845', null, 'Jardim Panorama'], '2026-06-11 21:15'],
            ['Eduardo Santos', 'eduardo.santos@email.com', '163928475', '(44) 99620-3456', ['87503-240', 'Rua Doutor Camargo', '1320', null, 'Zona III'], '2026-06-27 10:22'],
            ['Fernanda Ribeiro', 'fernanda.ribeiro@email.com', '284716395', '(44) 99988-1212', ['87506-110', 'Avenida Presidente Castelo Branco', '2990', 'Casa 2', 'Parque Dom Pedro I'], '2026-07-09 18:47'],
            ['Gabriela Lima', 'gabi.lima@email.com', '391827465', '(44) 99701-6543', ['87504-050', 'Rua Itapuã', '512', null, 'Jardim Alvorada'], '2026-08-02 16:33'],
            ['Henrique Costa', 'henrique.costa@email.com', '472619358', '(44) 99355-8080', ['87507-020', 'Avenida Ângelo Moreira da Fonseca', '1780', null, 'Jardim Cruzeiro'], '2026-08-25 12:10'],
            ['Isabela Rocha', 'isabela.rocha@email.com', '586273914', '(44) 99268-4321', ['87501-180', 'Avenida Maringá', '3120', 'Apto 501', 'Centro'], '2026-10-29 22:01'],
            ['Juliana Pereira', 'ju.pereira@email.com', '637481925', '(44) 99840-1717', ['87508-090', 'Rua Guaíra', '305', null, 'Jardim São Cristóvão'], '2026-11-04 09:48'],
        ];
        foreach ($novos as [$nome, $email, $base, $telefone, $endereco, $quando]) {
            $u = new User(['name' => $nome, 'email' => $email, 'cpf' => self::cpf($base), 'telefone' => $telefone,
                'password' => 'cliente123'] + array_combine(['cep', 'rua', 'numero', 'complemento', 'bairro'], $endereco)
                + ['cidade' => 'Umuarama', 'uf' => 'PR']);
            $this->clientes[] = $u;
            $this->em($quando, null, function () use ($u) {
                $u->save();
                Auth::setUser($u);
                Auditoria::registrar('criou', $u); // o próprio cliente se cadastra
            });
            $this->em(Carbon::parse($quando)->addMinutes(mt_rand(2, 15)), $u, function () use ($u) {
                $u->forceFill(['email_verified_at' => now()])->save();
                Auditoria::registrar('confirmou_email', $u);
            });
        }
        // Alguns movimentos de conta
        $this->em('2026-09-03 19:30', $this->clientes[2], function () {
            $c = $this->clientes[2]->refresh();
            $c->numero = '3500';
            $c->complemento = 'Apto 21';
            $c->save();
            Auditoria::registrar('editou', $c, ['campos' => ['numero', 'complemento']]);
        });
        $this->em('2026-10-11 08:15', $this->clientes[4], fn () => Auditoria::registrar('alterou_senha', $this->clientes[4]));
        $this->em('2026-11-02 21:40', $this->clientes[6], fn () => Auditoria::registrar('recuperou_senha', $this->clientes[6]));
    }

    // ------------------------------------------------------------------ promoções

    private function promocoes(): void
    {
        $bruno = $this->equipe['bruno'];
        $mary = $this->equipe['mary'];
        // [nome, modo, código, tipo, valor, início, fim, aplicação, categoria, quem, criada em]
        $lista = [
            ['Dia dos Namorados', Promocao::AUTOMATICA, null, Promocao::PERCENTUAL, 10, '2026-06-05', '2026-06-12', Promocao::CATEGORIA, 'Vestidos', $bruno, '2026-06-01 11:00'],
            ['Liquida Inverno', Promocao::CUPOM, 'INVERNO20', Promocao::PERCENTUAL, 20, '2026-07-15', '2026-08-31', Promocao::CATEGORIA, 'Casacos e Jaquetas', $bruno, '2026-07-13 15:20'],
            ['Semana do Cliente', Promocao::AUTOMATICA, null, Promocao::PERCENTUAL, 10, '2026-09-14', '2026-09-20', Promocao::GERAL, null, $mary, '2026-09-08 10:00'],
            ['Primavera', Promocao::CUPOM, 'PRIMAVERA15', Promocao::VALOR, 15, '2026-09-22', '2026-10-15', Promocao::GERAL, null, $bruno, '2026-09-21 09:30'],
            ['Boas-vindas', Promocao::CUPOM, 'BEMVINDA10', Promocao::PERCENTUAL, 10, '2026-10-01', '2026-12-31', Promocao::GERAL, null, $mary, '2026-09-29 17:00'],
            ['Moletons em Oferta', Promocao::AUTOMATICA, null, Promocao::PERCENTUAL, 20, '2026-10-20', '2026-11-30', Promocao::CATEGORIA, 'Moletons', $bruno, '2026-10-19 16:00'],
            ['Black Friday Mary Modas', Promocao::AUTOMATICA, null, Promocao::PERCENTUAL, 15, '2026-11-20', '2026-11-30', Promocao::GERAL, null, $mary, '2026-11-16 10:30'],
            ['Verão de Shorts', Promocao::CUPOM, 'VERAO25', Promocao::PERCENTUAL, 25, '2026-12-01', '2027-01-31', Promocao::CATEGORIA, 'Shorts', $bruno, '2026-11-18 14:10'],
        ];
        foreach ($lista as [$nome, $modo, $codigo, $tipo, $valor, $inicio, $fim, $aplicacao, $categoria, $quem, $quando]) {
            $this->em($quando, $quem, function () use ($nome, $modo, $codigo, $tipo, $valor, $inicio, $fim, $aplicacao, $categoria) {
                $p = new Promocao(['nome' => $nome, 'modo' => $modo, 'codigo' => $codigo, 'tipo' => $tipo, 'valor' => $valor,
                    'data_inicio' => $inicio, 'data_fim' => $fim, 'aplicacao' => $aplicacao,
                    'categoria_id' => $categoria ? $this->categorias[$categoria]->id : null]);
                $p->save();
                Auditoria::registrar('criou', $p);
            });
        }
        // Oferta dos moletons suspensa antes do fim
        $this->em('2026-11-12 18:20', $mary, fn () => $this->inativar(Promocao::where('nome', 'Moletons em Oferta')->firstOrFail(),
            'Margem baixa: suspensa até a Black Friday terminar'));
    }

    // ------------------------------------------------------------------ pedidos

    private function pedidos(): void
    {
        // Pedidos por mês (a loja cresce e a Black Friday puxa novembro)
        $porMes = ['2026-06' => 9, '2026-07' => 10, '2026-08' => 11, '2026-09' => 12, '2026-10' => 14, '2026-11' => 18];
        foreach ($porMes as $mes => $quantidade) {
            $inicio = Carbon::parse("{$mes}-01");
            $fim = $mes === '2026-11' ? $this->agora->copy()->subHours(2) : $inicio->copy()->endOfMonth();
            for ($i = 0; $i < $quantidade; $i++) {
                // Em novembro, metade dos pedidos cai na Black Friday (a partir do dia 20)
                $de = ($mes === '2026-11' && $i % 2 === 0) ? Carbon::parse('2026-11-20') : $inicio;
                $quando = Carbon::createFromTimestamp(mt_rand($de->getTimestamp(), $fim->getTimestamp()), config('app.timezone'))
                    ->setTime(mt_rand(8, 22), mt_rand(0, 59));
                if ($quando->greaterThan($this->agora)) {
                    $quando = $this->agora->copy()->subHours(mt_rand(3, 30));
                }
                $this->agendarPedido($quando);
            }
        }
    }

    private function agendarPedido(Carbon $quando): void
    {
        // Sorteios feitos agora (ordem fixa), aplicados na hora do acontecimento
        $sorteio = [
            'cliente' => mt_rand(0, 999),
            'itens' => mt_rand(1, 100) <= 60 ? 1 : (mt_rand(1, 100) <= 75 ? 2 : 3),
            'escolhas' => [mt_rand(0, 999), mt_rand(0, 999), mt_rand(0, 999)],
            'quantidade2' => $this->chance(15),
            'cupom' => $this->chance(45),
            'forma' => $this->sorteio(['pix', 'pix', 'pix', 'pix', 'cartao', 'cartao', 'cartao', 'boleto', 'boleto']),
            'desiste' => $this->chance(7),
            'recusa' => $this->chance(18),
            'estorno' => $this->chance(6),
            'pagaEm' => mt_rand(2, 25),
            'separa' => mt_rand(14, 30),
            'entrega' => mt_rand(3, 6),
            'equipe' => $this->chance(75) ? 'rafael' : 'bruno',
        ];
        $recente = $quando->greaterThan($this->agora->copy()->subHours(30));

        $this->em($quando, null, function () use ($quando, $sorteio, $recente) {
            $clientes = array_values(array_filter($this->clientes, fn (User $c) => $c->exists && $c->created_at->lessThan($quando)));
            if (! $clientes) {
                return;
            }
            $cliente = $clientes[$sorteio['cliente'] % count($clientes)];
            Auth::setUser($cliente);

            $disponiveis = Produto::where('status', Status::ATIVO)->where('estoque', '>', 0)
                ->whereHas('categoria', fn ($q) => $q->where('status', Status::ATIVO))->orderBy('id')->get();
            if ($disponiveis->isEmpty()) {
                return;
            }

            // Carrinho e pedido, como o PedidoController faz
            $carrinho = Carrinho::create(['user_id' => $cliente->id]);
            $escolhidos = [];
            for ($i = 0; $i < $sorteio['itens']; $i++) {
                $p = $disponiveis[$sorteio['escolhas'][$i] % $disponiveis->count()];
                if (isset($escolhidos[$p->id])) {
                    continue;
                }
                $escolhidos[$p->id] = true;
                $quantidade = ($sorteio['quantidade2'] && $p->estoque >= 2 && $i === 0) ? 2 : 1;
                $carrinho->itens()->create(['produto_id' => $p->id, 'quantidade' => $quantidade]);
            }
            if ($sorteio['cupom']) {
                $codigo = Promocao::validas()->where('modo', Promocao::CUPOM)->orderBy('id')->value('codigo');
                if ($codigo) {
                    $carrinho->cupom = $codigo;
                    $carrinho->save();
                }
            }
            $pedido = $this->criarPedido($carrinho->fresh(), $cliente);

            if ($sorteio['desiste']) {
                $this->em($quando->copy()->addMinutes(mt_rand(20, 300)), $cliente, fn () => $this->tentar(fn () => $this->andamento->cancelar(
                    $pedido->fresh(), $cliente, $this->sorteio(['Desisti da compra', 'Escolhi o tamanho errado', 'Vou comprar na loja física']))));

                return;
            }
            if ($recente && $sorteio['forma'] !== 'cartao') {
                // Pedido de hoje/ontem: PIX ou boleto gerado e ainda aguardando o pagamento
                $this->em($quando->copy()->addMinutes(3), $cliente, fn () => $this->tentar(fn () => $this->compra->pagar($pedido->fresh(), $cliente, $sorteio['forma'])));

                return;
            }
            $this->pagamento($pedido, $cliente, $quando->copy()->addMinutes($sorteio['pagaEm']), $sorteio);
        });
    }

    private function criarPedido(Carrinho $carrinho, User $cliente): Pedido
    {
        $totais = $carrinho->totais();
        $pedido = new Pedido;
        $pedido->user_id = $cliente->id;
        $pedido->carrinho_id = $carrinho->id;
        $pedido->subtotal = $totais['subtotal'];
        $pedido->desconto = $totais['desconto'];
        $pedido->total = $totais['total'];
        $pedido->promocao_id = $totais['promocao']?->id;
        $pedido->cupom = $totais['promocao']?->codigo;
        foreach (['cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'] as $campo) {
            $pedido->{$campo} = $cliente->{$campo};
        }
        $pedido->save();
        foreach ($carrinho->itens as $item) {
            $pedido->itens()->create([
                'produto_id' => $item->produto_id, 'nome' => $item->produto->nome, 'tamanho' => $item->produto->tamanho,
                'cor' => $item->produto->cor, 'quantidade' => $item->quantidade, 'preco_unitario' => $item->produto->preco,
                'subtotal' => $item->subtotal(),
            ]);
        }
        $pedido->registrarStatus(Pedido::AGUARDANDO_PAGAMENTO, $cliente);
        $carrinho->status = Carrinho::EM_PAGAMENTO;
        $carrinho->save();
        Auditoria::registrar('criou', $pedido, ['total' => (float) $pedido->total]);

        return $pedido;
    }

    private function pagamento(Pedido $pedido, User $cliente, Carbon $quando, array $sorteio): void
    {
        $forma = $sorteio['forma'];
        $cartaoOk = ['cartao_numero' => '4111111111111111', 'cartao_nome' => mb_strtoupper($cliente->name), 'cartao_validade' => '12/30', 'cartao_cvv' => '123'];

        if ($forma === 'cartao') {
            if ($sorteio['recusa']) {
                // Primeira tentativa recusada pela operadora; o cliente tenta de novo com outro cartão
                $this->em($quando, $cliente, fn () => $this->tentar(fn () => $this->compra->pagar($pedido->fresh(), $cliente, 'cartao',
                    ['cartao_numero' => '5555444433330000'] + $cartaoOk)));
                $quando = $quando->copy()->addMinutes(mt_rand(2, 8));
            }
            $this->em($quando, $cliente, function () use ($pedido, $cliente, $cartaoOk, $quando, $sorteio) {
                $this->tentar(fn () => $this->compra->pagar($pedido->fresh(), $cliente, 'cartao', $cartaoOk));
                $this->depoisDoPagamento($pedido, $quando, $sorteio);
            });

            return;
        }

        $this->em($quando, $cliente, fn () => $this->tentar(fn () => $this->compra->pagar($pedido->fresh(), $cliente, $forma)));

        if ($forma === 'pix') {
            $aprovado = $quando->copy()->addMinutes(mt_rand(1, 6));
            $this->em($aprovado, null, function () use ($pedido, $aprovado, $sorteio) {
                $pagamento = $pedido->pagamentos()->where('status', Pagamento::PENDENTE)->latest('id')->first();
                if ($pagamento) {
                    $this->tentar(fn () => $this->compra->simular($pagamento)); // o banco confirma o PIX
                }
                $this->depoisDoPagamento($pedido, $aprovado, $sorteio);
            });

            return;
        }

        // Boleto: compensa em 1 a 2 dias úteis; a gerência confirma no painel
        $compensou = $this->expediente($quando->copy()->addDays(mt_rand(1, 2)), mt_rand(9, 16), mt_rand(0, 59));
        $quem = $this->chance(60) ? $this->equipe['bruno'] : $this->equipe['mary'];
        $this->em($compensou, $quem, function () use ($pedido, $quem, $compensou, $sorteio) {
            $pagamento = $pedido->pagamentos()->where('status', Pagamento::PENDENTE)->latest('id')->first();
            if ($pagamento) {
                $this->tentar(fn () => $this->compra->confirmarAprovacao($pagamento, $quem));
            }
            $this->depoisDoPagamento($pedido, $compensou, $sorteio);
        });
    }

    // Separação, envio e entrega pela equipe (ou estorno, de vez em quando)
    private function depoisDoPagamento(Pedido $pedido, Carbon $pago, array $sorteio): void
    {
        if ($pedido->fresh()->status !== Pedido::PAGO) {
            return; // recusado ou cancelado automaticamente por falta de estoque
        }
        $equipe = $this->equipe[$sorteio['equipe']];

        if ($sorteio['estorno']) {
            $this->em($pago->copy()->addHours(mt_rand(3, 20)), $this->equipe['bruno'], fn () => $this->tentar(fn () => $this->andamento->cancelar(
                $pedido->fresh(), $this->equipe['bruno'], $this->sorteio([
                    'Cliente pediu o cancelamento antes do envio',
                    'Peça com defeito encontrada na separação',
                    'Cliente comprou o tamanho errado e pediu o estorno',
                ]))));

            return;
        }

        $separacao = $this->expediente($pago->copy()->addHours($sorteio['separa']), mt_rand(8, 11), mt_rand(0, 59));
        $envio = $this->expediente($separacao->copy()->addDay(), mt_rand(13, 17), mt_rand(0, 59));
        $entrega = $this->expediente($envio->copy()->addDays($sorteio['entrega']), mt_rand(10, 18), mt_rand(0, 59));
        foreach ([[Pedido::EM_SEPARACAO, $separacao], [Pedido::ENVIADO, $envio], [Pedido::ENTREGUE, $entrega]] as [$status, $data]) {
            $this->em($data, $equipe, fn () => $this->tentar(fn () => $this->andamento->atualizarStatus($pedido->fresh(), $status, $equipe)));
        }
    }

    // As regras do sistema podem recusar um passo (ex.: estoque acabou antes): aí o passo simplesmente não acontece
    private function tentar(Closure $passo): void
    {
        try {
            $passo();
        } catch (PagamentoException) {
            // mesma resposta que a tela daria ao usuário; para a demonstração, segue em frente
        }
    }

    // ------------------------------------------------------------------ documentos válidos (dígitos verificadores)

    public static function cpf(string $base9): string
    {
        $n = array_map('intval', str_split($base9));
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += $n[$i] * ($t + 1 - $i);
            }
            $n[$t] = ((10 * $soma) % 11) % 10;
        }

        return implode('', $n);
    }

    public static function cnpj(string $base12): string
    {
        $n = array_map('intval', str_split($base12));
        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $pesos) {
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += $n[$i] * $peso;
            }
            $resto = $soma % 11;
            $n[] = $resto < 2 ? 0 : 11 - $resto;
        }

        return implode('', $n);
    }
}
