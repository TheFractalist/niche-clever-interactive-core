<?php

declare(strict_types=1);

/*
 * A deliberately simple, deterministic source register for the T2 audit. It is not a security
 * scanner: WPCS and Plugin Check remain separate required evidence. It makes a new direct sink,
 * request read or extension buffering path visible for classification before it can drift into a
 * release candidate unnoticed.
 */
$root = dirname(__DIR__);
$categories = [
    'direct_request_reads' => [
        'pattern' => '/\$_(GET|POST|SERVER|REQUEST|COOKIE|FILES)/',
        'expected' => ['src/Admin/AdminRequest.php' => 8, 'src/Rest/ChatRoute.php' => 2],
    ],
    'output_statements' => [
        'pattern' => '/^\s*echo\b/m',
        'expected' => [
            'src/Admin/ConversationsPage.php' => 70,
            'src/Admin/ExtensionScreens.php' => 11,
            'src/Admin/HelpRenderer.php' => 38,
            'src/Admin/ProfileFieldRenderer.php' => 15,
            'src/Admin/ProfileHub.php' => 80,
            'src/Admin/SettingsPage.php' => 34,
            'src/Admin/SiteSettingsPage.php' => 14,
            'src/Rest/EventStream.php' => 1,
        ],
    ],
    'wordpress_actions' => [
        'pattern' => '/\badd_action\(/',
        'expected' => [
            'src/Admin/ConversationsPage.php' => 3,
            'src/Admin/ExtensionScreens.php' => 2,
            'src/Admin/ProfileConnectionTest.php' => 1,
            'src/Admin/ProfileHub.php' => 4,
            'src/Admin/SettingsPage.php' => 4,
            'src/Admin/SiteSettingsPage.php' => 3,
            'src/Conversation/ConversationPurge.php' => 1,
            'src/Frontend/InteractivePanel.php' => 2,
            'src/Plugin.php' => 2,
            'src/Rest/ChatRoute.php' => 1,
            'src/AI/Transport/WordPressResponsesHttpClient.php' => 1,
        ],
    ],
    'rest_registrations' => [
        'pattern' => '/\bregister_rest_route\(/',
        'expected' => ['src/Rest/ChatRoute.php' => 1],
    ],
    'redirects' => [
        'pattern' => '/\bwp_(safe_)?redirect\(/',
        'expected' => [
            'src/Admin/ConversationsPage.php' => 1,
            'src/Admin/ProfileConnectionTest.php' => 1,
            'src/Admin/ProfileHub.php' => 1,
            'src/Admin/SettingsPage.php' => 1,
            'src/Admin/SiteSettingsPage.php' => 1,
        ],
    ],
    'database_access' => [
        'pattern' => '/\$wpdb|\bprepare\(/',
        'expected' => [
            'src/Budget/WordPressBucketStore.php' => 31,
            'src/Conversation/ConversationTable.php' => 10,
            'src/Conversation/WordPressConversationStore.php' => 58,
            'src/Plugin.php' => 7,
            'uninstall.php' => 3,
        ],
    ],
    'output_buffers' => [
        'pattern' => '/\bob_start\(|\bob_get_clean\(/',
        'expected' => ['src/Admin/ExtensionScreens.php' => 4, 'src/Admin/ProfileHub.php' => 2],
    ],
    'extension_seams' => [
        'pattern' => '/->(render|screens|sections)\(/',
        'expected' => [
            'src/Admin/ExtensionScreens.php' => 4,
            'src/Admin/ProfileHub.php' => 6,
            'src/Admin/SettingsPage.php' => 3,
            'src/Admin/SiteSettingsPage.php' => 1,
            'src/Profile/ProfileDirectory.php' => 1,
        ],
    ],
];

/** @return array<string, string> */
$sourceFiles = static function (string $root): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $path = str_replace('\\', '/', $file->getPathname());
            $files[substr($path, strlen(str_replace('\\', '/', $root)) + 1)] = (string) file_get_contents($file->getPathname());
        }
    }
    $files['uninstall.php'] = (string) file_get_contents($root . '/uninstall.php');

    return $files;
};

$withoutComments = static function (string $source): string {
    $result = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $result .= str_replace(["\r", "\n"], ['', "\n"], $token[1]);
            continue;
        }
        $result .= is_array($token) ? $token[1] : $token;
    }

    return $result;
};

$actual = array_fill_keys(array_keys($categories), []);
foreach ($sourceFiles($root) as $path => $source) {
    $source = $withoutComments($source);
    foreach ($categories as $name => $category) {
        $count = preg_match_all($category['pattern'], $source);
        if ($count > 0) {
            $actual[$name][$path] = $count;
        }
    }
}

$failures = [];
foreach ($categories as $name => $category) {
    ksort($actual[$name]);
    $expected = $category['expected'];
    ksort($expected);
    if ($actual[$name] !== $expected) {
        $failures[] = $name . ' inventory changed; update the classified occurrence register before changing this sink. Actual: ' . json_encode($actual[$name]);
    }
}

$javascriptHtmlSinks = [];
$javascriptIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($javascriptIterator as $file) {
    if (!$file instanceof SplFileInfo || !in_array($file->getExtension(), ['js', 'mjs'], true)) {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    $relative = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
    if (str_starts_with($relative, 'tests/')) {
        continue;
    }
    $count = preg_match_all('/\.innerHTML\s*=|\.outerHTML\s*=|insertAdjacentHTML\(|document\.write\(|srcdoc\s*=|createContextualFragment\(/', (string) file_get_contents($file->getPathname()));
    if ($count > 0) {
        $javascriptHtmlSinks[$relative] = $count;
    }
}
if ($javascriptHtmlSinks !== []) {
    $failures[] = 'javascript_html_sinks inventory changed; production JavaScript must not introduce a raw HTML sink. Actual: ' . json_encode($javascriptHtmlSinks);
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "PASS: WordPress.org occurrence inventory verified\n");
