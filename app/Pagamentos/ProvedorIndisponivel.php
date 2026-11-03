<?php

namespace App\Pagamentos;

use RuntimeException;

// Falha temporária de comunicação com o provedor (RNF.03 da 3.31)
class ProvedorIndisponivel extends RuntimeException {}
