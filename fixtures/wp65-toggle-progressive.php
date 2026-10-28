<?php

use NicheCleverInteractive\Core\Configuration\CoreSettings;

$value = $args[0] ?? '';
if ($value !== 'on' && $value !== 'off') {
    throw new RuntimeException('Use on or off for the fixture progressive setting.');
}
$stored = get_option(CoreSettings::OPTION_NAME, []);
if (!is_array($stored)) {
    throw new RuntimeException('Core fixture settings are missing.');
}
$stored['streaming'] = ['progressive' => $value === 'on'];
$settings = CoreSettings::fromArray($stored);
update_option(CoreSettings::OPTION_NAME, $settings->toArray(), false);
echo wp_json_encode(['progressive' => $settings->progressiveStreaming()]) . PHP_EOL;
