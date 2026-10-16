<?php
include('../../../inc/includes.php');

Session::checkRight('profile', UPDATE);
if (method_exists('Session', 'checkCSRF')) {
    Session::checkCSRF($_POST);
}

if (isset($_POST['update'])) {
    PluginContratosProfile::updateProfile($_POST);
    Session::addMessageAfterRedirect(__('Direitos atualizados.', 'contratos'), false, INFO);
}
Html::back();
