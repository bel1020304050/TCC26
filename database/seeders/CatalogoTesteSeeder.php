<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Support\Status;
use Illuminate\Database\Seeder;

/**
 * Categorias, fornecedores e produtos fictícios — os mesmos dos wireframes da documentação.
 * Só roda com APP_ENV=local.
 */
class CatalogoTesteSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [];
        foreach ([
            ['Vestidos', 'Vestidos e conjuntos', Status::ATIVO],
            ['Blusas', 'Blusas femininas', Status::ATIVO],
            ['Saias', 'Saias midi e jeans', Status::ATIVO],
            ['Acessórios', 'Bolsas, cintos e bijuterias', Status::ATIVO],
            ['Jaquetas', 'Coleção de inverno', Status::INATIVO],
        ] as [$nome, $descricao, $status]) {
            $categoria = Categoria::firstOrNew(['nome' => $nome]);
            $categoria->descricao = $descricao;
            $categoria->status = $status;
            $categoria->save();
            $categorias[$nome] = $categoria;
        }

        $fornecedores = [];
        // [nome, tipo, documento, telefone, e-mail, status, [cep, rua, número, complemento, bairro, cidade, uf]]
        foreach ([
            ['Confecções Aurora Ltda', Fornecedor::JURIDICA, '12345678000195', '(44) 3622-1000', 'contato@aurora.com.br', Status::ATIVO,
                ['87501-000', 'Avenida Paraná', '3200', 'Sala 4', 'Centro', 'Umuarama', 'PR']],
            ['Malharia Boa Vista Ltda', Fornecedor::JURIDICA, '98765432000198', '(44) 3622-2000', 'vendas@boavista.com.br', Status::ATIVO,
                ['87013-000', 'Avenida Brasil', '1500', null, 'Zona 01', 'Maringá', 'PR']],
            ['Bijus Maringá Ltda', Fornecedor::JURIDICA, '44556677000186', '(44) 3622-4000', 'bijus@maringa.com.br', Status::ATIVO,
                ['87020-000', 'Rua Santos Dumont', '820', null, 'Zona 03', 'Maringá', 'PR']],
            ['Jeans Sul Ltda', Fornecedor::JURIDICA, '11222333000181', '(44) 3622-3000', null, Status::INATIVO,
                ['87200-000', 'Avenida América', '640', null, 'Centro', 'Cianorte', 'PR']],
            // Exemplo de CNPJ no formato alfanumérico (emitido desde julho de 2026)
            ['Ateliê Nova Era Ltda', Fornecedor::JURIDICA, '12ABC34501DE35', '(44) 3622-5000', 'contato@novaera.com.br', Status::ATIVO,
                ['87502-000', 'Rua Desembargador Munhoz de Mello', '1210', null, 'Zona II', 'Umuarama', 'PR']],
            // Exemplo de fornecedor pessoa física (costureira autônoma)
            ['Rita Alves Costura', Fornecedor::FISICA, '52998224725', '(44) 99876-1234', 'rita.costura@email.com', Status::ATIVO,
                ['87504-000', 'Rua Piauí', '77', 'Casa dos fundos', 'Jardim Alvorada', 'Umuarama', 'PR']],
        ] as [$nome, $tipo, $documento, $telefone, $email, $status, $endereco]) {
            $fornecedor = Fornecedor::firstOrNew(['documento' => $documento]);
            $fornecedor->fill(['nome' => $nome, 'tipo_pessoa' => $tipo, 'telefone' => $telefone, 'email' => $email]
                + array_combine(['cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'], $endereco));
            $fornecedor->status = $status;
            $fornecedor->save();
            $fornecedores[$nome] = $fornecedor;
        }

        foreach ([
            ['Vestido Floral', 'Vestidos', 'Confecções Aurora Ltda', 'M', 'Preto', 129.90, 8, Status::ATIVO],
            ['Vestido Floral', 'Vestidos', 'Confecções Aurora Ltda', 'G', 'Preto', 129.90, 15, Status::ATIVO],
            ['Blusa Básica', 'Blusas', 'Malharia Boa Vista Ltda', 'P', 'Branco', 59.90, 25, Status::ATIVO],
            ['Blusa Social', 'Blusas', 'Malharia Boa Vista Ltda', 'P', 'Branco', 89.90, 12, Status::ATIVO],
            ['Saia Midi', 'Saias', 'Confecções Aurora Ltda', 'M', 'Bege', 119.90, 6, Status::ATIVO],
            ['Saia Jeans', 'Saias', 'Confecções Aurora Ltda', '38', 'Azul', 99.90, 10, Status::ATIVO],
            ['Jaqueta Jeans', 'Jaquetas', 'Jeans Sul Ltda', 'M', 'Azul', 229.90, 0, Status::INATIVO],
        ] as [$nome, $categoria, $fornecedor, $tamanho, $cor, $preco, $estoque, $status]) {
            $produto = Produto::firstOrNew([
                'nome' => $nome, 'categoria_id' => $categorias[$categoria]->id, 'tamanho' => $tamanho, 'cor' => $cor,
            ]);
            $produto->fill(['fornecedor_id' => $fornecedores[$fornecedor]->id, 'preco' => $preco, 'estoque' => $estoque]);
            $produto->status = $status;
            $produto->save();
        }
    }
}
