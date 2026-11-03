<?php

namespace App\Pagamentos;

use RuntimeException;

// Regra do processo de pagamento que impede continuar; a mensagem é mostrada ao cliente
class PagamentoException extends RuntimeException {}
