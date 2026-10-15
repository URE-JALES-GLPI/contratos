<?php

class PluginContratosProfile extends CommonDBTM
{
    public static string $rightname = 'plugin_contratos';

    public static function install(): bool
    {
        global $DB;
        $table = 'glpi_profilerights';
        if (!$DB->tableExists($table)) {
            return false;
        }
        foreach (self::getAllRights() as $right => $label) {
            $DB->doQuery(
                "INSERT IGNORE INTO `$table` (`profiles_id`, `name`, `rights`) " .
                "SELECT `id`, '" . $DB->escape(self::$rightname) . "', 0 FROM `glpi_profiles`"
            );
            break;
        }
        return true;
    }

    public static function uninstall(): bool
    {
        global $DB;
        $table = 'glpi_profilerights';
        if ($DB->tableExists($table)) {
            $DB->doQuery("DELETE FROM `$table` WHERE `name` = '" . $DB->escape(self::$rightname) . "'");
        }
        return true;
    }

    public static function getAllRights(): array
    {
        return [
            READ   => __('Leitura'),
            UPDATE => __('Escrita'),
            CREATE => __('Criar'),
            DELETE => __('Excluir'),
            PURGE  => __('Expurgar'),
        ];
    }

    public static function changeProfile(): void
    {
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item instanceof Profile) {
            return __('Contratos', 'contratos');
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof Profile) {
            self::showForProfile($item);
            return true;
        }
        return false;
    }

    public static function showForProfile(Profile $profile): void
    {
        echo '<div>' . __('Direitos gerenciados em Administração > Perfis > Contratos.', 'contratos') . '</div>';
    }
}
