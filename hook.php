<?php

function plugin_contratos_schema(): bool
{
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    if (!$DB->tableExists('glpi_plugin_contratos_contracts')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_contratos_contracts` (
                `id` INT {$sign} NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL DEFAULT '',
                `tipo` VARCHAR(20) NOT NULL DEFAULT 'internet',
                `entities_id` INT {$sign} NOT NULL DEFAULT '0',
                `is_recursive` TINYINT NOT NULL DEFAULT 0,
                `suppliers_id` INT {$sign} NOT NULL DEFAULT '0',
                `users_id_responsavel` INT {$sign} NOT NULL DEFAULT '0',
                `operadora` VARCHAR(100) DEFAULT NULL,
                `link_velocidade` VARCHAR(50) DEFAULT NULL,
                `circuito_id` VARCHAR(100) DEFAULT NULL,
                `itemtype` VARCHAR(100) DEFAULT NULL,
                `items_id` INT {$sign} NOT NULL DEFAULT '0',
                `numero_nf` VARCHAR(100) DEFAULT NULL,
                `software_name` VARCHAR(255) DEFAULT NULL,
                `chave` VARCHAR(255) DEFAULT NULL,
                `quantidade` INT NOT NULL DEFAULT 1,
                `versao` VARCHAR(50) DEFAULT NULL,
                `numero_contrato` VARCHAR(100) DEFAULT NULL,
                `valor_mensal` DECIMAL(12,2) DEFAULT NULL,
                `valor_total` DECIMAL(12,2) DEFAULT NULL,
                `multa_percent` DECIMAL(5,2) DEFAULT NULL,
                `reajuste_indice` VARCHAR(50) DEFAULT NULL,
                `data_inicio` DATE DEFAULT NULL,
                `data_vencimento` DATE NOT NULL,
                `renovacao_auto` TINYINT NOT NULL DEFAULT 0,
                `status` VARCHAR(20) NOT NULL DEFAULT 'ativo',
                `comment` TEXT DEFAULT NULL,
                `is_deleted` TINYINT NOT NULL DEFAULT 0,
                `date_creation` DATETIME DEFAULT NULL,
                `date_mod` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `tipo` (`tipo`),
                KEY `venc` (`data_vencimento`),
                KEY `ent` (`entities_id`),
                KEY `status` (`status`),
                KEY `resp` (`users_id_responsavel`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die($DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_contratos_renewals')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_contratos_renewals` (
                `id` INT {$sign} NOT NULL AUTO_INCREMENT,
                `contracts_id` INT {$sign} NOT NULL DEFAULT '0',
                `data_antiga` DATE DEFAULT NULL,
                `data_nova` DATE DEFAULT NULL,
                `motivo` TEXT DEFAULT NULL,
                `valor_novo` DECIMAL(12,2) DEFAULT NULL,
                `users_id` INT {$sign} NOT NULL DEFAULT '0',
                `date_creation` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `contracts_id` (`contracts_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die($DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_contratos_alert_log')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_contratos_alert_log` (
                `id` INT {$sign} NOT NULL AUTO_INCREMENT,
                `contracts_id` INT {$sign} NOT NULL DEFAULT '0',
                `marco` VARCHAR(20) NOT NULL DEFAULT '',
                `vencimento_ref` DATE DEFAULT NULL,
                `date_creation` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `contracts_id` (`contracts_id`),
                KEY `marco` (`marco`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ") or die($DB->error());
    }

    return true;
}

function plugin_contratos_install(): bool
{
    plugin_contratos_schema();
    PluginContratosProfile::install();

    try {
        if (class_exists('CronTask')) {
            $cron = new CronTask();
            if (!$cron->getFromDBByCrit(['itemtype' => 'GlpiPlugin\\Contratos\\Alert', 'name' => 'cronCheck'])) {
                $cron->add([
                    'itemtype'      => 'GlpiPlugin\\Contratos\\Alert',
                    'name'          => 'cronCheck',
                    'frequency'     => 86400,
                    'param'         => 0,
                    'state'         => 1,
                    'mode'          => 2,
                    'allowmode'     => 3,
                    'logs_lifetime' => 30,
                    'hourmin'       => 0,
                    'comment'       => 'Contratos: alertas de vencimento 90/60/30 dias',
                ]);
            }
        }
    } catch (Throwable $e) {
        error_log('[contratos] cron install: ' . $e->getMessage());
    }

    return true;
}

function plugin_contratos_update(string $old_version): bool
{
    plugin_contratos_schema();
    return true;
}

function plugin_contratos_uninstall(): bool
{
    global $DB;

    foreach ([
        'glpi_plugin_contratos_contracts',
        'glpi_plugin_contratos_renewals',
        'glpi_plugin_contratos_alert_log',
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `{$table}`");
        }
    }

    try {
        if (class_exists('CronTask')) {
            $cron = new CronTask();
            $cron->deleteByCriteria(['itemtype' => 'GlpiPlugin\\Contratos\\Alert', 'name' => 'cronCheck']);
        }
    } catch (Throwable $e) {
        error_log('[contratos] cron uninstall: ' . $e->getMessage());
    }

    PluginContratosProfile::uninstall();
    return true;
}
