<?php

namespace GlpiPlugin\Contratos;

class Renewal extends \CommonDBTM
{
    public static function getTypeName($nb = 0): string
    {
        return _n('Renovação', 'Renovações', $nb, 'contratos');
    }

    public static function isNovaDataValida(string $nova, ?string $antiga = null): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nova)) {
            return false;
        }
        if ($nova < date('Y-m-d')) {
            return false;
        }
        if ($antiga !== null && $antiga !== '' && $nova <= $antiga) {
            return false;
        }
        return true;
    }

    public function apply(int $contracts_id, string $novaData, string $motivo = '', $valorNovo = null): bool
    {
        global $DB;
        $c = new Contract();
        if (!$c->getFromDB($contracts_id)) {
            return false;
        }
        $antiga = (string)($c->fields['data_vencimento'] ?? '');
        if (!self::isNovaDataValida($novaData, $antiga)) {
            return false;
        }
        $ok = $DB->insert('glpi_plugin_contratos_renewals', [
            'contracts_id' => $contracts_id,
            'data_antiga'  => $antiga,
            'data_nova'    => $novaData,
            'motivo'       => $motivo,
            'valor_novo'   => $valorNovo,
            'users_id'     => (int)(\Session::getLoginUserID() ?: 0),
            'date_creation' => date('Y-m-d H:i:s'),
        ]);
        if (!$ok) {
            return false;
        }
        $upd = ['id' => $contracts_id, 'data_vencimento' => $novaData];
        if ($valorNovo !== null && $valorNovo !== '') {
            $upd['valor_total'] = $valorNovo;
        }
        if (!$c->update($upd)) {
            return false;
        }
        $DB->doQuery(
            "DELETE FROM `glpi_plugin_contratos_alert_log` WHERE `contracts_id` = " . (int)$contracts_id .
            " AND (`vencimento_ref` IS NULL OR `vencimento_ref` <> '" . $DB->escape($novaData) . "')"
        );
        return true;
    }
}
