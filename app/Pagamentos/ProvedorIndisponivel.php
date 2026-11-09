<?php

namespace App\Pagamentos;

use RuntimeException;

// Falha temporária de comunicação com o provedor (fora do ar, sem internet, demorou demais)
class ProvedorIndisponivel extends RuntimeException {}
