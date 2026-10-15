<?php
// Teste Task 2 — validacao + semaforo (roda com ou sem GLPI; cria stub se preciso).
if (!class_exists('CommonDBTM')) {
    class CommonDBTM
    {
    }
}
require_once dirname(__DIR__) . '/src/Contract.php';
require_once dirname(__DIR__) . '/src/Renewal.php';

use GlpiPlugin\Contratos\Contract;

$fail = 0;
function check($label, $cond)
{
    global $fail;
    echo ($cond ? 'OK' : 'FAIL') . " $label\n";
    if (!$cond) {
        $fail++;
    }
}

check('semaforo 95 verde', Contract::semaforo(95) === 'verde');
check('semaforo 65 amarelo', Contract::semaforo(65) === 'amarelo');
check('semaforo 45 laranja', Contract::semaforo(45) === 'laranja');
check('semaforo 5 vermelho', Contract::semaforo(5) === 'vermelho');
check('semaforo -1 vencido', Contract::semaforo(-1) === 'vencido');
check('garantia sem item nem NF rejeita', !empty(Contract::validate(['tipo' => 'garantia', 'data_vencimento' => '2026-12-01'])));
check('garantia com NF aceita', empty(Contract::validate(['tipo' => 'garantia', 'data_vencimento' => '2026-12-01', 'numero_nf' => '123'])));
check('internet sem operadora rejeita', !empty(Contract::validate(['tipo' => 'internet', 'data_vencimento' => '2026-12-01', 'entities_id' => 1])));
check('licenca qtd 0 rejeita', !empty(Contract::validate(['tipo' => 'licenca', 'data_vencimento' => '2026-12-01', 'software_name' => 'Office', 'quantidade' => 0])));
check('vencimento < inicio rejeita', !empty(Contract::validate(['tipo' => 'internet', 'entities_id' => 1, 'operadora' => 'Vivo', 'data_inicio' => '2026-12-01', 'data_vencimento' => '2026-01-01'])));

exit($fail === 0 ? 0 : 1);
