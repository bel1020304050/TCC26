<?php

namespace App\Models;

use App\Notifications\RecuperarSenha;
use App\Notifications\VerificarEmail;
use App\Support\Documento;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Perfis de acesso: definem o que cada pessoa pode fazer no sistema
    public const PERFIL_CLIENTE = 'cliente';
    public const PERFIL_ATENDENTE = 'atendente'; // Atendente/Vendedor: operação do dia a dia
    public const PERFIL_GERENTE = 'gerente';     // gerencia a loja, menos a equipe e a auditoria
    public const PERFIL_ADMIN = 'admin';         // dono da loja: acesso total

    // Perfis da equipe (quem usa o painel), do menor para o maior acesso
    public const PERFIS_EQUIPE = [self::PERFIL_ATENDENTE, self::PERFIL_GERENTE, self::PERFIL_ADMIN];

    // Status (exclusão lógica: a conta nunca é apagada, só muda de status)
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_INATIVO = 'inativo';     // equipe interna desligada
    public const STATUS_ENCERRADO = 'encerrado'; // conta de cliente encerrada por ele mesmo

    public const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    /**
     * Campos liberados para preenchimento em massa.
     * "perfil", "status" e "senha_provisoria" NÃO estão aqui de propósito: só o servidor define esses valores.
     * Segurança: se alguém enviar um campo "perfil=admin" pelo navegador, ele é ignorado.
     */
    protected $fillable = [
        'name', 'email', 'password', 'telefone', 'cpf',
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
            'senha_provisoria' => 'boolean',
        ];
    }

    // E-mail sempre em minúsculas, para a regra de e-mail único funcionar
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    // CPF salvo só com números (11 dígitos); vazio vira null
    protected function cpf(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => ($limpo = Documento::limpar($value)) === '' ? null : $limpo);
    }

    // Para exibir na tela: 529.982.247-25
    public function cpfFormatado(): ?string
    {
        return Documento::formatar($this->cpf);
    }

    protected function uf(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtoupper(trim($value)));
    }

    // ---------- E-mail e senha ----------

    // Regra: só o cliente precisa confirmar o e-mail (para comprar). A equipe usa o e-mail da loja e não confirma.
    public function hasVerifiedEmail(): bool
    {
        return ! $this->isCliente() || $this->email_verified_at !== null;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerificarEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RecuperarSenha($token));
    }

    // ---------- Perguntas sobre o perfil ----------

    public function isAdmin(): bool
    {
        return $this->perfil === self::PERFIL_ADMIN;
    }

    public function isGerente(): bool
    {
        return $this->perfil === self::PERFIL_GERENTE;
    }

    public function isAtendente(): bool
    {
        return $this->perfil === self::PERFIL_ATENDENTE;
    }

    // Gestão = Administrador ou Gerente (inativar cadastros, promoções, cancelar pedido pago...)
    public function isGestao(): bool
    {
        return $this->isAdmin() || $this->isGerente();
    }

    public function isCliente(): bool
    {
        return $this->perfil === self::PERFIL_CLIENTE;
    }

    // Equipe interna = quem usa o painel administrativo
    public function isEquipe(): bool
    {
        return in_array($this->perfil, self::PERFIS_EQUIPE, true);
    }

    public function isAtivo(): bool
    {
        return $this->status === self::STATUS_ATIVO;
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function isEncerrado(): bool
    {
        return $this->status === self::STATUS_ENCERRADO;
    }

    // Contato mostrado à equipe. LGPD: conta encerrada não mostra nenhum dado de contato
    public function contatoPainel(): string
    {
        if ($this->isEncerrado()) {
            return 'Conta encerrada'.($this->encerrada_em ? ' em '.$this->encerrada_em->format('d/m/Y') : '');
        }

        return $this->email.($this->telefone ? ' · '.$this->telefone : '');
    }

    public function perfilLabel(): string
    {
        return match ($this->perfil) {
            self::PERFIL_ADMIN => 'Administrador',
            self::PERFIL_GERENTE => 'Gerente',
            self::PERFIL_ATENDENTE => 'Atendente/Vendedor',
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
        return $query->whereIn('perfil', self::PERFIS_EQUIPE);
    }

    public function scopeClientes(Builder $query): Builder
    {
        return $query->where('perfil', self::PERFIL_CLIENTE);
    }
}
