<?php

namespace Tests\Unit;

use App\Support\Documento;
use PHPUnit\Framework\TestCase;

class DocumentoTest extends TestCase
{
    public function test_cnpj_valido_e_invalido(): void
    {
        $this->assertTrue(Documento::valido('12.345.678/0001-95'));
        $this->assertTrue(Documento::valido('11222333000181'));
        $this->assertFalse(Documento::valido('12.345.678/0001-00'));  // dígito errado
        $this->assertFalse(Documento::valido('00000000000000'));       // sequência repetida
    }

    public function test_cpf_valido_e_invalido(): void
    {
        $this->assertTrue(Documento::valido('529.982.247-25'));
        $this->assertFalse(Documento::valido('529.982.247-26'));
        $this->assertFalse(Documento::valido('111.111.111-11'));
    }

    public function test_tamanho_errado_e_invalido(): void
    {
        $this->assertFalse(Documento::valido('123'));
        $this->assertFalse(Documento::valido(''));
    }

    public function test_formatar(): void
    {
        $this->assertSame('12.345.678/0001-95', Documento::formatar('12345678000195'));
        $this->assertSame('529.982.247-25', Documento::formatar('52998224725'));
        $this->assertNull(Documento::formatar(null));
    }
}
