<?php

define('PLUGIN_CONTRATOS_VERSION', '1.0.0');
define('PLUGIN_CONTRATOS_MIN_GLPI', '10.0.0');
define('PLUGIN_CONTRATOS_MAX_GLPI', '13.0.0');

function plugin_version_contratos(): array
{
    return [
        'name'         => '[GEST] Contratos e Vencimentos',
        'version'      => PLUGIN_CONTRATOS_VERSION,
        'author'       => 'GEST',
        'license'      => 'GPL v2+',
        'homepage'     => '',
        'requirements' => ['glpi' => ['min' => PLUGIN_CONTRATOS_MIN_GLPI, 'max' => PLUGIN_CONTRATOS_MAX_GLPI]],
    ];
}

function plugin_contratos_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_CONTRATOS_MIN_GLPI, 'lt')) {
        echo 'Este plugin requer GLPI ' . PLUGIN_CONTRATOS_MIN_GLPI . ' ou superior.';
        return false;
    }
    return true;
}

function plugin_contratos_check_config(bool $verbose = false): bool
{
    return true;
}

function plugin_init_contratos(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['contratos'] = true;
    $PLUGIN_HOOKS['change_profile']['contratos'] = ['PluginContratosProfile', 'changeProfile'];
    $PLUGIN_HOOKS['cron']['contratos'] = ['GlpiPlugin\\Contratos\\Alert', 'cronCheck'];

    Plugin::registerClass('PluginContratosProfile', ['addtabon' => 'Profile']);
    Plugin::registerClass('GlpiPlugin\\Contratos\\Contract', [
        'document_types' => true,
    ]);

    if (Session::getLoginUserID()) {
        if (Session::haveRight('plugin_contratos', READ)) {
            $PLUGIN_HOOKS['menu_toadd']['contratos'] = [
                'tools' => 'PluginContratosMenu',
            ];
        }
    }
}
