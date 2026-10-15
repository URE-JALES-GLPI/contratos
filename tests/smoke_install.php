<?php
// Smoke test Task 1 — verifica esqueleto do plugin contratos.
// Uso no GLPI/XAMPP: php tests/smoke_install.php
$base = dirname(__DIR__);
$ok = true;
foreach (['setup.php', 'hook.php', 'composer.json', 'inc/menu.class.php', 'inc/profile.class.php'] as $f) {
    if (!file_exists($base . '/' . $f)) { echo "MISSING $f\n"; $ok = false; }
}
if (strpos((string)@file_get_contents($base . '/hook.php'), 'glpi_plugin_contratos_contracts') === false) {
    echo "MISSING TABLE contracts\n"; $ok = false;
}
echo $ok ? "SMOKE PASS\n" : "SMOKE FAIL\n";
exit($ok ? 0 : 1);
