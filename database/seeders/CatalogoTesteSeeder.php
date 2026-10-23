<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Support\Status;
use Illuminate\Database\Seeder;

/**
 * Categorias, fornecedores e produtos fictícios — os mesmos dos wireframes da documentação.
 * Só roda com APP_ENV=local. Os produtos servem para testar a regra "não inativar com produtos ativos";
 * as telas de Produtos chegam na Fase 3.
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
        foreach ([
            ['Confecções Aurora Ltda', '12345678000195', '(44) 3622-1000', 'contato@aurora.com.br', Status::ATIVO],
            ['Malharia Boa Vista Ltda', '98765432000198', '(44) 3622-2000', 'vendas@boavista.com.br', Status::ATIVO],
            ['Bijus Maringá Ltda', '44556677000186', '(44) 3622-4000', 'bijus@maringa.com.br', Status::ATIVO],
            ['Jeans Sul Ltda', '11222333000181', '(44) 3622-3000', null, Status::INATIVO],
        ] as [$nome, $documento, $telefone, $email, $status]) {
            $fornecedor = Fornecedor::firstOrNew(['documento' => $documento]);
            $fornecedor->fill(['nome' => $nome, 'telefone' => $telefone, 'email' => $email]);
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
