<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Perfis de acesso (seção 2.2 da documentação)
    public const PERFIL_CLIENTE = 'cliente';
    public const PERFIL_FUNCIONARIO = 'funcionario';
    public const PERFIL_ADMIN = 'admin';

    // Status (exclusão lógica — seção 2.3)
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_INATIVO = 'inativo';     // equipe interna desligada
    public const STATUS_ENCERRADO = 'encerrado'; // conta de cliente encerrada por ele mesmo

    public const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    /**
     * Campos liberados para preenchimento em massa.
     * "perfil" e "status" NÃO estão aqui de propósito: só o servidor define esses valores
     * (RNF.01 da story 3.9 — um campo "perfil" enviado pelo navegador é ignorado).
     */
    protected $fillable = [
        'name', 'email', 'password', 'telefone', 'cargo',
        'cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'perfil' => self::PERFIL_CLIENTE,
        'status' => self::STATUS_ATIVO,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'encerrada_em' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // E-mail sempre em minúsculas, para a regra de e-mail único funcionar
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    protected function uf(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtoupper(trim($value)));
    }

    // ---------- Perguntas sobre o perfil ----------

    public function isAdmin(): bool
    {
        return $this->perfil === self::PERFIL_ADMIN;
    }

    public function isFuncionario(): bool
    {
        return $this->perfil === self::PERFIL_FUNCIONARIO;
    }

    public function isCliente(): bool
    {
        return $this->perfil === self::PERFIL_CLIENTE;
    }

    // Equipe interna = quem usa o painel administrativo
    public function isEquipe(): bool
    {
        return $this->isAdmin() || $this->isFuncionario();
    }

    public function isAtivo(): bool
    {
        return $this->status === self::STATUS_ATIVO;
    }

    public function perfilLabel(): string
    {
        return match ($this->perfil) {
            self::PERFIL_ADMIN => 'Administrador',
            self::PERFIL_FUNCIONARIO => 'Funcionário',
            default => 'Cliente',
        };
    }

    // Para onde cada perfil vai depois do login
    public function homeRoute(): string
    {
        return $this->isEquipe() ? 'painel.inicio' : 'loja.inicio';
    }

    // ---------- Escopos de consulta ----------

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ATIVO);
    }

    public function scopeEquipe(Builder $query): Builder
    {
        return $query->whereIn('perfil', [self::PERFIL_ADMIN, self::PERFIL_FUNCIONARIO]);
    }

    public function scopeClientes(Builder $query): Builder
    {
        return $query->where('perfil', self::PERFIL_CLIENTE);
    }
}
