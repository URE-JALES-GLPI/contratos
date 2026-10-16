<?php
include('../../../inc/includes.php');

Session::checkRight('plugin_contratos', READ);

$contract = new GlpiPlugin\Contratos\Contract();

if (isset($_POST['add'])) {
    Session::checkRight('plugin_contratos', CREATE);
    $contract->check(-1, CREATE, $_POST);
    if ($contract->add($_POST)) {
        Html::redirect($contract->getLinkURL());
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    Session::checkRight('plugin_contratos', UPDATE);
    $contract->check((int)$_POST['id'], UPDATE);
    if ($contract->update($_POST)) {
        Html::redirect($contract->getLinkURL());
    }
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkRight('plugin_contratos', DELETE);
    $contract->check((int)$_POST['id'], DELETE);
    $contract->delete($_POST);
    Html::redirect('/plugins/contratos/front/contract.php');
} elseif (isset($_POST['renew'])) {
    Session::checkRight('plugin_contratos', UPDATE);
    $id = (int)($_POST['id'] ?? 0);
    $nova = (string)($_POST['data_nova'] ?? '');
    $motivo = (string)($_POST['motivo'] ?? '');
    $valor = $_POST['valor_novo'] ?? null;
    $renewal = new GlpiPlugin\Contratos\Renewal();
    if ($renewal->apply($id, $nova, $motivo, $valor)) {
        Session::addMessageAfterRedirect(__('Renovado com sucesso.', 'contratos'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Renovação inválida. Use data futura maior que o vencimento atual.', 'contratos'), false, ERROR);
    }
    Html::redirect('/plugins/contratos/front/contract.form.php?id=' . $id);
} elseif (isset($_GET['id'])) {
    Html::header(__('Contrato', 'contratos'), '', 'plugins', 'plugincontratosmenu', 'contracts');
    $contract->display(['id' => (int)$_GET['id']]);
    Html::footer();
} else {
    Html::header(__('Novo contrato', 'contratos'), '', 'plugins', 'plugincontratosmenu', 'contracts');
    $contract->display(['id' => -1]);
    Html::footer();
}
