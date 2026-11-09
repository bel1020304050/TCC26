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

    // Exemplo oficial da Receita Federal para o CNPJ alfanumérico
    public function test_cnpj_alfanumerico(): void
    {
        $this->assertTrue(Documento::cnpjValido('12.ABC.345/01DE-35'));
        $this->assertTrue(Documento::cnpjValido('12abc34501de35'));       // minúsculas e sem pontuação
        $this->assertFalse(Documento::cnpjValido('12.ABC.345/01DE-36'));  // dígito errado
        $this->assertFalse(Documento::cnpjValido('12.ABC.345/01DE-3X'));  // dígito verificador com letra
        $this->assertFalse(Documento::cnpjValido('AAAAAAAAAAAAAA'));      // repetido
        $this->assertFalse(Documento::cpfValido('12ABC34501D'));          // CPF não aceita letras
    }

    public function test_cpf_e_cnpj_separados(): void
    {
        $this->assertTrue(Documento::cpfValido('529.982.247-25'));
        $this->assertFalse(Documento::cpfValido('12.345.678/0001-95'));
        $this->assertTrue(Documento::cnpjValido('12.345.678/0001-95'));
        $this->assertFalse(Documento::cnpjValido('529.982.247-25'));
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
        $this->assertSame('12.ABC.345/01DE-35', Documento::formatar('12abc34501de35'));
        $this->assertNull(Documento::formatar(null));
    }
}
