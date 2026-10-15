<?php

namespace GlpiPlugin\Contratos;

class Alert extends \CommonDBTM
{
    /**
     * Decide o marco a alertar.
     * @param array $row contrato (não usado além de id)
     * @param int $dias dias para vencimento (negativo = vencido)
     * @param string|null $ultimoEnvioVencido datetime do último envio semanal (para dias<0)
     * @return string|null '90'|'60'|'30'|'0'|'vencido_semanal'|null
     */
    public static function shouldAlert(array $row, int $dias, ?string $ultimoEnvioVencido): ?string
    {
        if ($dias === 90) {
            return '90';
        }
        if ($dias === 60) {
            return '60';
        }
        if ($dias === 30) {
            return '30';
        }
        if ($dias === 0) {
            return '0';
        }
        if ($dias < 0) {
            if ($ultimoEnvioVencido === null || $ultimoEnvioVencido === '') {
                return 'vencido_semanal';
            }
            $ts = strtotime($ultimoEnvioVencido);
            if ($ts === false) {
                return 'vencido_semanal';
            }
            if ((time() - $ts) >= 7 * 86400) {
                return 'vencido_semanal';
            }
            return null;
        }
        return null;
    }

    public static function alreadySent(int $contracts_id, string $marco, string $vencimento): bool
    {
        global $DB;
        try {
            $it = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_plugin_contratos_alert_log',
                'WHERE'  => [
                    'contracts_id'  => $contracts_id,
                    'marco'         => $marco,
                    'vencimento_ref' => $vencimento,
                ],
                'LIMIT' => 1,
            ]);
            return $it->count() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function markSent(int $contracts_id, string $marco, string $vencimento): void
    {
        global $DB;
        try {
            $DB->insert('glpi_plugin_contratos_alert_log', [
                'contracts_id'   => $contracts_id,
                'marco'          => $marco,
                'vencimento_ref' => $vencimento,
                'date_creation'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[contratos] markSent: ' . $e->getMessage());
        }
    }

    public static function lastWeeklySent(int $contracts_id): ?string
    {
        global $DB;
        try {
            $it = $DB->request([
                'SELECT' => ['date_creation'],
                'FROM'   => 'glpi_plugin_contratos_alert_log',
                'WHERE'  => ['contracts_id' => $contracts_id, 'marco' => 'vencido_semanal'],
                'ORDER'  => 'date_creation DESC',
                'LIMIT'  => 1,
            ]);
            foreach ($it as $r) {
                return (string)$r['date_creation'];
            }
        } catch (\Throwable $e) {
            error_log('[contratos] lastWeekly: ' . $e->getMessage());
        }
        return null;
    }

    public static function cronCheck(\CronTask $task): int
    {
        global $DB;
        $sent = 0;
        try {
            $it = $DB->request([
                'SELECT' => ['id', 'name', 'tipo', 'entities_id', 'data_vencimento', 'users_id_responsavel'],
                'FROM'   => 'glpi_plugin_contratos_contracts',
                'WHERE'  => ['is_deleted' => 0, 'status' => 'ativo'],
                'LIMIT'  => 500,
            ]);
            foreach ($it as $row) {
                try {
                    $venc = (string)$row['data_vencimento'];
                    if ($venc === '' || $venc === '0000-00-00') {
                        continue;
                    }
                    $dias = Contract::diasParaVencimento($venc);
                    $marco = null;
                    if ($dias < 0) {
                        $marco = self::shouldAlert($row, $dias, self::lastWeeklySent((int)$row['id']));
                    } else {
                        $marco = self::shouldAlert($row, $dias, null);
                    }
                    if ($marco === null) {
                        continue;
                    }
                    if ($marco !== 'vencido_semanal' && self::alreadySent((int)$row['id'], $marco, $venc)) {
                        continue;
                    }
                    if (self::sendMail($row, $dias, $marco)) {
                        self::markSent((int)$row['id'], $marco, $venc);
                        $sent++;
                    }
                } catch (\Throwable $e) {
                    error_log('[contratos] cron row: ' . $e->getMessage());
                    continue;
                }
            }
            $task->log("Contratos: $sent alerta(s) enviado(s).");
            return 1;
        } catch (\Throwable $e) {
            error_log('[contratos] cronCheck: ' . $e->getMessage());
            return 0;
        }
    }

    public static function sendMail(array $row, int $dias, string $marco): bool
    {
        try {
            $to = self::resolveRecipients($row);
            if (empty($to)) {
                return false;
            }
            $assunto = sprintf(
                '[Contratos] %s vence em %dd — %s',
                (string)($row['tipo'] ?? 'contrato'),
                $dias,
                (string)($row['name'] ?? ('#' . (int)$row['id']))
            );
            if ($dias < 0) {
                $assunto = sprintf(
                    '[Contratos] %s VENCIDO há %dd — %s',
                    (string)($row['tipo'] ?? 'contrato'),
                    abs($dias),
                    (string)($row['name'] ?? ('#' . (int)$row['id']))
                );
            }
            $corpo = "Contrato: " . ($row['name'] ?? '') . "\n"
                . "Tipo: " . ($row['tipo'] ?? '') . "\n"
                . "Vencimento: " . ($row['data_vencimento'] ?? '') . " ({$dias}d)\n"
                . "Marco: $marco\n"
                . "Ver: /plugins/contratos/front/contract.form.php?id=" . (int)$row['id'] . "\n";
            if (class_exists('NotificationMailing') && method_exists('NotificationMailing', 'sendNotification')) {
                return (bool)\NotificationMailing::sendNotification($to, $assunto, $corpo);
            }
            $headers = "From: glpi@localhost\r\nContent-Type: text/plain; charset=UTF-8";
            return mail(implode(',', (array)$to), $assunto, $corpo, $headers);
        } catch (\Throwable $e) {
            error_log('[contratos] sendMail: ' . $e->getMessage());
            return false;
        }
    }

    public static function resolveRecipients(array $row): array
    {
        global $DB;
        $emails = [];
        try {
            $uid = (int)($row['users_id_responsavel'] ?? 0);
            if ($uid > 0) {
                $u = new \User();
                if ($u->getFromDB($uid) && !empty($u->fields['email'])) {
                    $emails[] = (string)$u->fields['email'];
                }
            }
        } catch (\Throwable $e) {
            error_log('[contratos] recipients: ' . $e->getMessage());
        }
        return array_values(array_unique(array_filter($emails)));
    }
}
