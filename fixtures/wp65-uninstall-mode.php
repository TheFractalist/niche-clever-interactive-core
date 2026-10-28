<?php

use NicheCleverInteractive\Core\Configuration\CoreSettings;

$mode = $args[0] ?? '';
if ($mode !== 'retain' && $mode !== 'delete') {
    throw new RuntimeException('Use retain or delete for the fixture uninstall setting.');
}
$stored = get_option(CoreSettings::OPTION_NAME, []);
if (!is_array($stored)) {
    throw new RuntimeException('Core fixture settings are missing.');
}
$stored['lifecycle'] = ['delete_on_uninstall' => $mode === 'delete'];
update_option(CoreSettings::OPTION_NAME, CoreSettings::fromArray($stored)->toArray(), false);
echo wp_json_encode(['delete_on_uninstall' => $mode === 'delete']) . PHP_EOL;
