<?php
include('../../../inc/includes.php');

Session::checkRight('plugin_contratos', READ);

Html::header(__('Contratos', 'contratos'), '', 'plugins', 'plugincontratosmenu', 'contracts');

$contract = new GlpiPlugin\Contratos\Contract();
Search::show('GlpiPlugin\\Contratos\\Contract');

Html::footer();
