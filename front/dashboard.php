<?php
include('../../../inc/includes.php');

use GlpiPlugin\Contratos\Contract;

Session::checkRight('plugin_contratos', READ);

$tipo     = $_GET['tipo'] ?? '';
$status   = $_GET['status'] ?? '';
$ent      = (int)($_GET['entities_id'] ?? 0);
$faixa    = $_GET['faixa'] ?? '';

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=contratos.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'nome', 'tipo', 'entidade', 'vencimento', 'dias', 'semaforo', 'responsavel'], ';');
    foreach (Contract::listForDashboard($tipo, $ent, $status) as $r) {
        $dias = Contract::diasParaVencimento((string)$r['data_vencimento']);
        fputcsv($out, [$r['id'], $r['name'], $r['tipo'], $r['entities_id'], $r['data_vencimento'], $dias, Contract::semaforo($dias), $r['users_id_responsavel']], ';');
    }
    fclose($out);
    exit;
}

Html::header(__('Dashboard Contratos', 'contratos'), '', 'tools', 'plugincontratosmenu');

$rows = Contract::listForDashboard($tipo, $ent, $status);
$counts = ['verde' => 0, 'amarelo' => 0, 'laranja' => 0, 'vermelho' => 0, 'vencido' => 0];
foreach ($rows as $r) {
    $counts[Contract::semaforo(Contract::diasParaVencimento((string)$r['data_vencimento']))]++;
}

echo '<div class="contratos-cards">';
foreach ($counts as $k => $v) {
    echo '<div class="card ' . $k . '"><span class="n">' . $v . '</span><span>' . $k . '</span></div>';
}
echo '</div>';

echo '<form method="get" class="contratos-filtros">';
echo '<select name="tipo"><option value="">-- tipo --</option>';
foreach (['internet' => 'Internet', 'garantia' => 'Garantia', 'licenca' => 'Licença'] as $v => $l) {
    $sel = $tipo === $v ? ' selected' : '';
    echo '<option value="' . $v . '"' . $sel . '>' . $l . '</option>';
}
echo '</select> ';
echo '<select name="faixa"><option value="">-- faixa --</option>';
foreach (['verde' => '>90d', 'amarelo' => '60-90d', 'laranja' => '30-60d', 'vermelho' => '<30d', 'vencido' => 'vencido'] as $v => $l) {
    $sel = $faixa === $v ? ' selected' : '';
    echo '<option value="' . $v . '"' . $sel . '>' . $l . '</option>';
}
echo '</select> ';
echo '<input type="submit" value="' . __('Filtrar') . '" class="submit">';
echo ' <a href="?export=csv&tipo=' . urlencode($tipo) . '">' . __('Export CSV') . '</a>';
echo '</form>';

echo '<table class="tab_cadre_fixe contratos-table"><tr><th>ID</th><th>' . __('Nome') . '</th><th>' . __('Tipo') . '</th><th>' . __('Vencimento') . '</th><th>' . __('Dias') . '</th><th>Status</th></tr>';
foreach ($rows as $r) {
    $dias = Contract::diasParaVencimento((string)$r['data_vencimento']);
    $sem = Contract::semaforo($dias);
    if ($faixa !== '' && $sem !== $faixa) {
        continue;
    }
    echo '<tr class="' . $sem . '"><td>' . (int)$r['id'] . '</td>';
    echo '<td><a href="contract.form.php?id=' . (int)$r['id'] . '">' . htmlspecialchars((string)$r['name']) . '</a></td>';
    echo '<td>' . htmlspecialchars((string)$r['tipo']) . '</td>';
    echo '<td>' . htmlspecialchars((string)$r['data_vencimento']) . '</td>';
    echo '<td>' . $dias . '</td><td>' . $sem . '</td></tr>';
}
echo '</table>';

Html::footer();
