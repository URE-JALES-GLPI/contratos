<?php

class PluginContratosMenu extends CommonGLPI
{
    public static function getMenuName(): string
    {
        return __('Contratos', 'contratos');
    }

    public static function getMenuContent(): array
    {
        global $CFG_GLPI;
        $menu = [];
        if (Session::haveRight('plugin_contratos', READ)) {
            $menu['title'] = self::getMenuName();
            $menu['page']  = '/plugins/contratos/front/dashboard.php';
            $menu['links'] = [
                'search' => '/plugins/contratos/front/contract.php',
                'add'    => '/plugins/contratos/front/contract.form.php',
            ];
            $menu['options'] = [
                'dashboard' => [
                    'title' => __('Dashboard', 'contratos'),
                    'page'  => '/plugins/contratos/front/dashboard.php',
                ],
                'contracts' => [
                    'title' => __('Contratos', 'contratos'),
                    'page'  => '/plugins/contratos/front/contract.php',
                ],
            ];
        }
        return $menu;
    }
}
