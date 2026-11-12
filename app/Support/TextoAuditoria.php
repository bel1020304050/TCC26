<?php

namespace App\Support;

use App\Models\Auditoria;
use App\Models\Pedido;
use App\Models\User;

/**
 * Traduz um registro de auditoria (gravado como códigos) em texto para o relatório:
 * "editou" → "Editou", "produtos" → "Produtos", {"antes": ..., "depois": ...} → "Preço: R$ 89,90 → R$ 79,90".
 */
final class TextoAuditoria
{
    public const ACOES = [
        'criou' => 'Cadastrou', 'editou' => 'Editou', 'inativou' => 'Inativou', 'reativou' => 'Reativou',
        'cancelou' => 'Cancelou', 'alterou_status' => 'Alterou o status',
        'pagamento_aprovado' => 'Pagamento aprovado', 'pagamento_recusado' => 'Pagamento recusado', 'pagamento_estornado' => 'Pagamento estornado',
        'confirmou_boleto' => 'Confirmou boleto',
        'redefiniu_senha' => 'Redefiniu a senha', 'alterou_senha' => 'Alterou a própria senha',
        'trocou_senha_provisoria' => 'Criou a própria senha', 'recuperou_senha' => 'Recuperou a senha por e-mail',
        'confirmou_email' => 'Confirmou o e-mail', 'encerrou_conta' => 'Encerrou a conta',
    ];

    public const AREAS = [
        'produtos' => 'Produtos', 'categorias' => 'Categorias', 'fornecedores' => 'Fornecedores', 'promocoes' => 'Promoções',
        'pedidos' => 'Pedidos', 'pagamentos' => 'Pedidos', 'users' => 'Usuários e contas',
    ];

    public const PERFIS = [
        User::PERFIL_ADMIN => 'Administrador', User::PERFIL_GERENTE => 'Gerente', User::PERFIL_ATENDENTE => 'Atendente/Vendedor',
        User::PERFIL_CLIENTE => 'Cliente', 'sistema' => 'Sistema', 'funcionario' => 'Funcionário',
    ];

    // Nome dos campos que aparecem no "o que mudou"
    private const CAMPOS = [
        'nome' => 'Nome', 'name' => 'Nome', 'descricao' => 'Descrição', 'preco' => 'Preço', 'estoque' => 'Estoque',
        'tamanho' => 'Tamanho', 'cor' => 'Cor', 'categoria_id' => 'Categoria', 'fornecedor_id' => 'Fornecedor', 'imagem' => 'Foto',
        'documento' => 'CPF/CNPJ', 'tipo_pessoa' => 'Tipo de pessoa', 'telefone' => 'Telefone', 'email' => 'E-mail',
        'cep' => 'CEP', 'rua' => 'Rua', 'numero' => 'Número', 'complemento' => 'Complemento', 'bairro' => 'Bairro', 'cidade' => 'Cidade', 'uf' => 'UF',
        'perfil' => 'Perfil', 'codigo' => 'Código', 'tipo' => 'Tipo', 'valor' => 'Valor', 'modo' => 'Modo', 'aplicacao' => 'Aplica em',
        'data_inicio' => 'Início', 'data_fim' => 'Fim',
    ];

    public static function acao(string $acao): string
    {
        return self::ACOES[$acao] ?? ucfirst(str_replace('_', ' ', $acao));
    }

    public static function area(string $entidade): string
    {
        return self::AREAS[$entidade] ?? ucfirst($entidade);
    }

    public static function perfil(?string $perfil): string
    {
        return self::PERFIS[$perfil] ?? (string) $perfil;
    }

    // "Bruno Costa (Gerente)" · "Ana Souza (Cliente)" · "Cliente removido (Cliente)" · "Sistema"
    public static function quem(Auditoria $registro): string
    {
        if ($registro->perfil === \App\Support\Auditoria::SISTEMA || (! $registro->user_id && ! $registro->perfil)) {
            return 'Sistema';
        }

        return ($registro->usuario?->name ?? 'Usuário removido').' ('.self::perfil($registro->perfil).')';
    }

    // Resumo do que mudou, a partir dos detalhes gravados
    public static function mudancas(Auditoria $registro): string
    {
        $d = $registro->detalhes ?? [];

        if (isset($d['antes'], $d['depois'])) {
            $partes = [];
            foreach ($d['depois'] as $campo => $novo) {
                $antigo = $d['antes'][$campo] ?? null;
                if ((string) $antigo !== (string) $novo) {
                    $partes[] = (self::CAMPOS[$campo] ?? $campo).': '.self::valor($campo, $antigo).' → '.self::valor($campo, $novo);
                }
            }

            return $partes ? implode('; ', $partes) : 'Salvou sem alterações';
        }

        return match (true) {
            isset($d['campos']) => 'Alterou: '.implode(', ', $d['campos']),
            isset($d['de'], $d['para']) => self::status($d['de']).' → '.self::status($d['para']),
            $registro->acao === 'cancelou' => (isset($d['de']) ? self::status($d['de']).' → ' : '').'Cancelado',
            isset($d['forma']) => (Pedido::FORMAS_PAGAMENTO[$d['forma']] ?? $d['forma']).' · '.self::dinheiro($d['valor'] ?? 0)
                .(! empty($d['manual']) ? ' · confirmado manualmente' : '')
                .($registro->acao === 'pagamento_recusado' && ! empty($d['mensagem']) ? ' · '.$d['mensagem'] : ''),
            isset($d['total']) => 'Total '.self::dinheiro($d['total']),
            isset($d['perfil']) => 'Perfil: '.self::perfil($d['perfil']),
            $registro->acao === 'inativou' => 'Ativo → Inativo',
            $registro->acao === 'reativou' => 'Inativo → Ativo',
            default => '—',
        };
    }

    private static function status(string $status): string
    {
        return Pedido::STATUS[$status] ?? $status;
    }

    private static function valor(string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '(vazio)';
        }
        if ($campo === 'preco' || ($campo === 'valor' && is_numeric($valor))) {
            return self::dinheiro($valor);
        }
        if ($campo === 'perfil') {
            return self::perfil($valor);
        }
        if (in_array($campo, ['data_inicio', 'data_fim'], true) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $valor)) {
            return date('d/m/Y', strtotime((string) $valor));
        }

        return mb_strimwidth((string) $valor, 0, 40, '…');
    }

    private static function dinheiro(mixed $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }
}
