<?php
/**
 * Verifies an installed Niche Clever Interactive plugin folder against the exact
 * rc.9 file set from git commit 02c24b6 (plugin source identical in every later
 * documentation-only commit). Read-only: it changes nothing.
 *
 * Usage:  C:\xampp\php\php.exe verify-rc9-install.php "C:\xampp\htdocs\Test-Site\wp-content\plugins\niche-clever-interactive"
 *
 * Text files are compared after normalising CRLF to LF, so a Windows checkout
 * with autocrlf does not cause false differences. Binary files compare raw.
 */
$expected = <<<'MANIFEST'
99fe3c45f1afaf6e0ae782f6f08e1c3ccd4534050ecea8f5b5990aa653587223  CHANGELOG.md
edaef632cbb643e4e7a221717a6c441a4c1a7c918e6e4d56debc3d8739b233f6  COPYING
d4c4bbd2b2f0c85952f4b1618e3a114465f4b566afc1ebdef465030c56696f94  LICENSE
1eeed6b600e582f0a164ea411ab605f27f6555954b3b4c85ec42a3a1390f98d9  README.md
71418bd612dd3bb063d8980df4854604cb9336533a143c46c839f1a13f60d40e  assets/css/admin.css
46efea90773d62c8881fab6333b50ff0cefa860113a72f4a4a3108b48576e781  assets/css/public.css
ccf545900fd67304eb96ddeee43a9b1039ac17fba287152d3d70429147c3f4b8  assets/images/ncli-admin-banner.png
e83a3f4933240d02f3d8eb88faecc9da92665cf1a6243e461b74f66c36beef8c  assets/images/ncli-logo.png
7dc378ded39c74468e3b1c7f4c4fa4b4f07cf6d0083167e52f6021f42fd6e465  assets/js/admin.js
1424d05bae8815a31243ae6b779f055c79083e4f0fcce93ad0835df7ddee92be  assets/js/public.js
d243539bbc4d01806b3b90c8fad846ff9be40f320e31f2326dca19a37def2c93  blocks/interactive/block.json
a9b42af2fc89054e89376ba61d16fd0850cf7c0a9f02e376a8b417e02a35ef2c  blocks/interactive/render.php
32f553895f1aad076bc9c7a5b993367fe09b060666c39aa36727074d3e877179  niche-clever-interactive-core.php
c2333ae9f4849114148c866876b42eb85826b5f60c7e00edbe30bd1b9f2da536  readme.txt
840bb8b6c44f3fa172f7fd07e4c0d432b3bf96a54d5c59b8344cdcb0118d85e2  src/AI/ChatOutcome.php
11e4a2617dd613072b3c12e556207a51bbeae15fa7d28acce129bed13d549e98  src/AI/ChatService.php
ec2e504746d3cad5e193d2475e7f3f24f140a2f3340bd2782d89f3cc55cd2260  src/AI/ConnectionTestResult.php
ddbc2bff78d24d6edebb21747f7912340f865ace42c67b999ddbc207f80d5374  src/AI/ConnectionTestService.php
7cd7377cf49c87fc67418b8ce6855f2ba4721efd872085386c5079d1bba3a8cb  src/AI/Transport/DeterministicMockTransport.php
ef93bfaa71a8beeb57bf535773c7e3b2f6d68801e59aa96cb41483aba08a6ad2  src/AI/Transport/OpenAiResponsesFileSearchTransport.php
f03fe791b9cf1a57521939dba36956819fcd801502e7d9428025b8c6e1b7e8a6  src/AI/Transport/ResponseTransport.php
e7c047ccc3475e33a2177a2ddd44dcc2a81e23d7ee3709590ad27839603b1a0c  src/AI/Transport/ResponsesHttpClient.php
844e3d2463b6983768f443e4b0957d78752bcdac948c863b320894b954b6a4ac  src/AI/Transport/ResponsesHttpResponse.php
f561e045bf94cba92d21106351c625de2ca5e68af58dc6b8a1e8d3fa77ebc1a7  src/AI/Transport/TransportRequest.php
3948f9327178377bf633223fa3e5237e86272561bfb23db63d7b70aaa00863e7  src/AI/Transport/TransportResult.php
a3c89ffd15e1a0b0afe65061b75a9399565f231725ef2ca0c340b8a0336f5699  src/AI/Transport/UnavailableTransport.php
62d5ee8e9911bd595c03de1d9663f7b79a528eb192362d20729c4d9ac135ed3d  src/AI/Transport/WordPressResponsesHttpClient.php
83bfeba9753841a64476415316c815883dd8bc97a1fe65f315b1cb702bec2d65  src/Admin/ConversationsPage.php
742f5b3a147bed8490c168ebbf838e34cceae3cc49b57dcc1980a677a66c4e45  src/Admin/SettingsPage.php
a098a46b6c6ef17f4fd3706ab038bf6634abfb2e6c883b4604002cf368cff136  src/Api/V1/Contracts/AnswerCacheV1.php
1bc562a68ef248f9644e1e5b7f06e61a760beb03ec4d2accbe8064fc84ee20aa  src/Api/V1/Contracts/ChronologyProviderV1.php
10d70b19d06d828b5f336c845bb0cef838c4002311cb2eda951af73f84bd489c  src/Api/V1/Contracts/CompletedTurnRecorderV1.php
60883aa7336f5c9c8bfd41277ac7fba0e895a424c641f7992ba9ba2d92619bc2  src/Api/V1/Contracts/ProfileResolverV1.php
12753ff085999e4e7ef8112820e6113a7ac953763739e07b9061426075021aec  src/Api/V1/Contracts/WorkspaceExtensionV1.php
b1a9ee0bd58c8b49fe37b6a9b68d63ad4221e567ec78a329c4202f69f2330d5f  src/Api/V1/Support/CompletedTurnEligibilityV1.php
e1503be44891b012a844dddfbc87765b7c0c77d5a36c90536289bacd517c2645  src/Api/V1/Support/ExtensionRegistryV1.php
9b8dc4dc8383185d6ad60e18e03061e1fdf7af70461e26935ffb4ee6707f17e7  src/Api/V1/Value/CacheKeyV1.php
b5d75dbde63eed506db54c4dbf8290deec88ebbb53b44909ef77f6251fcff31c  src/Api/V1/Value/CachedAnswerV1.php
627a314446f899030568f8498a29b812f1192838f43c11df2bd40a598f52df2d  src/Api/V1/Value/ChronologyFactV1.php
fb6d3058fe6f9179491defca9d3c1c2e6f1ffd6ea952b439d936dac33e02d3af  src/Api/V1/Value/CompletedTurnV1.php
ca53c1edc08a72c448f5d0ba530580f9c62e1f95e9b4f58c439c308b079f1120  src/Api/V1/Value/ProfileIdV1.php
64ea8d6563943d96dd83546e99b71a0b33d3ab78ce20f68a5012b954218b1d7f  src/Api/V1/Value/ResolvedProfileV1.php
1e3705ae594bee99338bc64299107a6488c833438c617350c85b0e93469a63e4  src/Api/V1/Value/WorkspaceContextV1.php
49062348878730f2db901f18d3e5ba8f6ab6f2e3efeaa5794fc5e34149c52b53  src/Api/V1/Value/WorkspaceScreenV1.php
93a2203bd494a0114b6488abcf3818a486522a5f5d1895dee22fa51cbca8283d  src/Budget/BucketStore.php
ea997dd4e15973a8a984dbae4d5fc4e98109f80914c15b2e337c4eb19bfb2e1e  src/Budget/BudgetGate.php
46f56b91faa1b0fb6645ee99136c0a2e133b110d230944001e66499acbda0956  src/Budget/ClientScopeResolver.php
0035948ca69c8c57c1e7ab9e808445cb03c72fc34d455719027a72588f776818  src/Budget/ProviderReservation.php
36e2778c0757468f11095137dbdff8c5da035a7f9a7f1c0b6ea013655f8416a4  src/Budget/Reservation.php
acc4e57bbd53453a74b4348da07c790cc533a2fc0aa6ad85fa122cf3f3ecf9f9  src/Budget/WordPressBucketStore.php
b61e102a78589ae85760692885f4085d8152ab0429f4985c9c8b387300e64fd5  src/Configuration/ConnectionFingerprint.php
c18c825f99fa88a5d907aed5e02cbc2a5b515a44ab2404bb702dcca2085f06dc  src/Configuration/CoreSettings.php
c4c7a6079d5995402519e47c1c19a82e453b5723a7d8e11d390661f8b4a02cab  src/Configuration/PanelAppearance.php
d57c3b0be8697b02c2ad081e2596b4dc9898babf254ca3ac5f74c6567a2022e6  src/Configuration/Readiness.php
6d14a31943a797d38fc7b43a6c6923e200a0a10b850a1e79b3b79d80c2ae08d9  src/Configuration/SecretResolver.php
622985ae55d3219a1a434ad88c6d4d2033c3c649e6f69bd14e2405c3664f02f7  src/Configuration/TransportMode.php
c47e8406bc262041c57ca864b39ca149b010f97ca741a26ea2ca5065b0daf392  src/Conversation/ConversationId.php
22faeaddeb12b334ae950f09a7f11c7912ad3a68508eb10a8753e8c70bf74474  src/Conversation/ConversationPurge.php
abf5c59507fc1c380b3fde7b87c7da9d9ab86578734870dd320ebbcc318179d6  src/Conversation/ConversationRecorder.php
753d76ea2a81e5061d06c1dda5e3d9384c6f4b9b2250d595e82eaea8af0de6c9  src/Conversation/ConversationStore.php
c247121ab84c0f75748a923d20d6ad525bcd4a587e2fbee1a3abd4e4709f4dcb  src/Conversation/ConversationTable.php
7ac99a570d58ca17598b616a8c5adf2ef027796251a522dfc1db9953c6fe82af  src/Conversation/ConversationTurn.php
ff50efed152ed659b9412e83be5f56ba215cb1ed4b6bc9670165188421ec51b6  src/Conversation/WordPressConversationStore.php
9c88208f80f3740a76046a8bcd995c750ce01855ab63e05ab9295c83894eca91  src/Frontend/InteractivePanel.php
01a35eb0c72f0f08c4f9e33550ae136f8bd8fcbc15f1f631dab5f79e05cb2751  src/Plugin.php
c46d220367202696ffe3f779c736db316af161f2fdaccaf02b0b78cfbd0cd3b9  src/Profile/DefaultProfileResolverV1.php
2651f3e8bb3e98d340cba4d2ca1ff0540827b602ea1d73c5b78e883a825e03c3  src/Rest/ChatRoute.php
7aa8ba97138dbc2f01ed270640833a069a46f5f5063d8903c61b2716693def6b  src/Security/AdminActionGuard.php
97b702e0a6f44fabad503dde6807041cc0bfba6edbd0858de6a8a1b4c864a22e  src/Security/ChatRequestValidator.php
7455a1e68454a9534bbf62d1f2321201cc74993e2efef88f9dccc4821f57ce89  src/Security/PolicyFingerprint.php
685b50b749ab3658715c87092fc58cae52d55104b56babcca2189684a71275fd  src/Security/PromptPolicy.php
aa940232faa795a995c802af228534d70db98e0976ad6bfdecbb6e39197d6fcb  src/Security/PublicError.php
bb263a6abb267446c3682fe9aa95bba43876748a6f18e123ecef1d54b2d312cf  src/Security/QuestionValidator.php
d3034abe1bbdd9568d1eb69ecc2952c950ce47055cf08717603d79fd4c61a8ab  src/Support/Translator.php
6737af20830dfe2ce196c2dab0a2b6f544ad7f9f9190b839e90a9203da446373  src/autoload.php
0dd262a3b92ab2fdf81fa666af08376fd9179fdfd69be14bc2eafee7ffb56f63  uninstall.php
MANIFEST;
$textExtensions = ['php', 'js', 'css', 'md', 'txt', 'json'];
$path = $argv[1] ?? '';
if ($path === '' || !is_dir($path)) {
    fwrite(STDERR, "Usage: php verify-rc9-install.php <plugin-folder>\n");
    exit(2);
}
$path = rtrim(str_replace('\\', '/', realpath($path)), '/');
$want = [];
foreach (explode("\n", trim($expected)) as $line) {
    [$hash, $file] = explode('  ', trim($line), 2);
    $want[$file] = $hash;
}
$have = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $info) {
    if ($info->isFile()) {
        $have[substr(str_replace('\\', '/', $info->getPathname()), strlen($path) + 1)] = $info->getPathname();
    }
}
$missing = array_diff_key($want, $have);
$extra = array_diff_key($have, $want);
$different = [];
foreach ($want as $file => $hash) {
    if (!isset($have[$file])) {
        continue;
    }
    $bytes = (string) file_get_contents($have[$file]);
    if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $textExtensions, true)) {
        $bytes = str_replace("\r\n", "\n", $bytes);
    }
    if (hash('sha256', $bytes) !== $hash) {
        $different[] = $file;
    }
}
$header = isset($have['niche-clever-interactive-core.php']) ? (string) file_get_contents($have['niche-clever-interactive-core.php']) : '';
preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $header, $version);
echo 'Folder:            ' . $path . "\n";
echo 'Header version:    ' . ($version[1] ?? '(main plugin file not found)') . "\n";
echo 'Expected files:    ' . count($want) . "\n";
echo 'Files found:       ' . count($have) . "\n";
foreach (['MISSING' => array_keys($missing), 'DIFFERENT CONTENT' => $different, 'UNEXPECTED EXTRA' => array_keys($extra)] as $label => $list) {
    if ($list !== []) {
        sort($list);
        echo "\n{$label} (" . count($list) . "):\n  " . implode("\n  ", $list) . "\n";
    }
}
if ($missing === [] && $different === [] && $extra === []) {
    echo "\nRESULT: MATCH. This folder is exactly the rc.9 file set (commit 02c24b6).\n";
    exit(0);
}
echo "\nRESULT: DOES NOT MATCH rc.9.\n";
exit(1);
