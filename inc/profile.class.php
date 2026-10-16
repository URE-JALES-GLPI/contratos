<?php

class PluginContratosProfile extends CommonDBTM
{
    public static string $rightname = 'plugin_contratos';

    public static function install(bool $grant_superadmin = true): bool
    {
        global $DB;
        $table = 'glpi_profilerights';
        if (!$DB->tableExists($table)) {
            return false;
        }
        // Garante uma linha por perfil (perfis criados após o install entram com 0).
        $DB->doQuery(
            "INSERT IGNORE INTO `$table` (`profiles_id`, `name`, `rights`) " .
            "SELECT `id`, '" . $DB->escape(self::$rightname) . "', 0 FROM `glpi_profiles`"
        );
        // Acesso total ao Super-Admin (id 4 no GLPI padrão) apenas na instalação.
        if ($grant_superadmin) {
            $all = READ | UPDATE | CREATE | DELETE | PURGE;
            $DB->doQuery(
                "UPDATE `$table` SET `rights` = $all " .
                "WHERE `profiles_id` = 4 AND `name` = '" . $DB->escape(self::$rightname) . "'"
            );
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

    public static function getProfileRights(int $profiles_id): int
    {
        if ($profiles_id <= 0) {
            return 0;
        }
        if (class_exists('ProfileRight') && method_exists('ProfileRight', 'getProfileRights')) {
            $rights = ProfileRight::getProfileRights($profiles_id, [self::$rightname]);
            return (int)($rights[self::$rightname] ?? 0);
        }
        global $DB;
        $table = 'glpi_profilerights';
        if (!$DB->tableExists($table)) {
            return 0;
        }
        $iterator = $DB->request([
            'SELECT' => ['rights'],
            'FROM'   => $table,
            'WHERE'  => ['profiles_id' => $profiles_id, 'name' => self::$rightname],
        ]);
        foreach ($iterator as $row) {
            return (int)$row['rights'];
        }
        return 0;
    }

    public static function updateProfile(array $input): void
    {
        $profiles_id = (int)($input['profiles_id'] ?? $input['id'] ?? 0);
        if ($profiles_id <= 0) {
            return;
        }
        $rights = 0;
        $posted = $input['rights'] ?? [];
        if (is_array($posted)) {
            foreach (self::getAllRights() as $right => $label) {
                if (!empty($posted[$right])) {
                    $rights |= (int)$right;
                }
            }
        }
        if (class_exists('ProfileRight') && method_exists('ProfileRight', 'updateProfileRights')) {
            ProfileRight::updateProfileRights($profiles_id, [self::$rightname => $rights]);
            return;
        }
        global $DB;
        $table = 'glpi_profilerights';
        if (!$DB->tableExists($table)) {
            return;
        }
        $existing = 0;
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $table,
            'WHERE'  => ['profiles_id' => $profiles_id, 'name' => self::$rightname],
        ]);
        foreach ($iterator as $row) {
            $existing = (int)$row['id'];
            break;
        }
        if ($existing > 0) {
            $DB->update($table, ['rights' => $rights], ['id' => $existing]);
        } else {
            $DB->insert($table, [
                'profiles_id' => $profiles_id,
                'name'        => self::$rightname,
                'rights'      => $rights,
            ]);
        }
    }

    public static function showForProfile(Profile $profile): void
    {
        $profiles_id = (int)$profile->getID();
        if ($profiles_id <= 0) {
            return;
        }
        $canedit = Session::haveRight('profile', UPDATE);
        $current = self::getProfileRights($profiles_id);

        if ($canedit) {
            echo "<form method='post' action='" . Plugin::getWebDir('contratos') . "/front/profile.form.php'>";
            echo Html::hidden('profiles_id', ['value' => $profiles_id]);
            if (method_exists('Session', 'getNewCSRFToken')) {
                echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            }
        }
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>" . sprintf(
            __('%1$s — %2$s', 'contratos'),
            __('Contratos', 'contratos'),
            htmlspecialchars((string)$profile->getName(), ENT_QUOTES, 'UTF-8')
        ) . "</th></tr>";
        $row = 0;
        foreach (self::getAllRights() as $right => $label) {
            $class = ($row % 2 === 0) ? 'tab_bg_1' : 'tab_bg_2';
            echo "<tr class='$class'><td>" . $label . "</td><td class='center'>";
            if ($canedit) {
                $checked = ($current & (int)$right) ? 'checked' : '';
                echo "<input type='checkbox' name='rights[" . (int)$right . "]' value='1' $checked>";
            } else {
                echo (($current & (int)$right) ? __('Sim') : __('Não'));
            }
            echo "</td></tr>";
            $row++;
        }
        if ($canedit) {
            echo "<tr><td class='center' colspan='2'>";
            echo Html::submit(_x('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo "</td></tr>";
        }
        echo "</table>";
        if ($canedit) {
            Html::closeForm();
        }
    }
}
