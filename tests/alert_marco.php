<?php
// Teste Task 4 — marcos de alerta.
if (!class_exists('CommonDBTM')) {
    class CommonDBTM
    {
    }
}
if (!class_exists('CronTask')) {
    class CronTask
    {
    }
}
require_once dirname(__DIR__) . '/src/Contract.php';
require_once dirname(__DIR__) . '/src/Alert.php';

use GlpiPlugin\Contratos\Alert;

$fail = 0;
function check($label, $cond)
{
    global $fail;
    echo ($cond ? 'OK' : 'FAIL') . " $label\n";
    if (!$cond) {
        $fail++;
    }
}

check('90 dispara', Alert::shouldAlert(['id' => 1], 90, null) === '90');
check('61 nao dispara', Alert::shouldAlert(['id' => 1], 61, null) === null);
check('60 dispara', Alert::shouldAlert(['id' => 1], 60, null) === '60');
check('30 dispara', Alert::shouldAlert(['id' => 1], 30, null) === '30');
check('0 dispara vencido', Alert::shouldAlert(['id' => 1], 0, null) === '0');
check('vencido recente nao reenvia', Alert::shouldAlert(['id' => 1], -3, date('Y-m-d H:i:s')) === null);
check('vencido antigo reenvia semanal', Alert::shouldAlert(['id' => 1], -8, date('Y-m-d H:i:s', strtotime('-8 days'))) === 'vencido_semanal');

exit($fail === 0 ? 0 : 1);
