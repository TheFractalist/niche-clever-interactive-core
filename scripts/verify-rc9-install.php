<?php
/**
 * Verifies an installed Niche Clever Interactive plugin folder against the exact
 * rc.9 file set (81 files: the conversation-history work from commit 02c24b6 merged with
 * the model-cost configuration from commit 094c654, as built from the merge commit). Read-only: it changes nothing.
 *
 * Usage:  C:\xampp\php\php.exe verify-rc9-install.php "C:\xampp\htdocs\Test-Site\wp-content\plugins\niche-clever-interactive"
 *
 * Text files are compared after normalising CRLF to LF, so a Windows checkout
 * with autocrlf does not cause false differences. Binary files compare raw.
 */
$expected = <<<'MANIFEST'
ffddcb320fd1211e55902408091b1efda62e42a784e3fb3b5fe536d49c766245  assets/css/admin.css
bf84267fb7b3e19013c6b31fb360783f1e8e89579ffd5effd205933741ab2272  assets/css/public.css
8cce4f63d628a7da81b6fa617788b9e552a1021db06a27c75cf4e67252ef2a89  assets/images/ncli-admin-banner.png
e83a3f4933240d02f3d8eb88faecc9da92665cf1a6243e461b74f66c36beef8c  assets/images/ncli-logo.png
f500efa37593f000c5613c1bfb0e026ce235bc8bb8373cf5a083d3436cb46c73  assets/js/admin.js
827199c4aef394bdfbb3da1a77d5d266ab275631f05345bc75fd41ebdb64ba7b  assets/js/public.js
d243539bbc4d01806b3b90c8fad846ff9be40f320e31f2326dca19a37def2c93  blocks/interactive/block.json
a9b42af2fc89054e89376ba61d16fd0850cf7c0a9f02e376a8b417e02a35ef2c  blocks/interactive/render.php
9f538cfd4fcf522e0332479559f85612daa7b8e0c50254499e5ec3d7533774b0  CHANGELOG.md
edaef632cbb643e4e7a221717a6c441a4c1a7c918e6e4d56debc3d8739b233f6  COPYING
d4c4bbd2b2f0c85952f4b1618e3a114465f4b566afc1ebdef465030c56696f94  LICENSE
32f553895f1aad076bc9c7a5b993367fe09b060666c39aa36727074d3e877179  niche-clever-interactive-core.php
8146ee275a57567633af9d386ae63360cdd37fa5363ef51202352f56833fc67a  README.md
03da741e33a89d0e008413ffc6799e71795ab0811ca2b717bd758392987d136b  readme.txt
033daf2347a19959bd733c0742efb3d732e75da7b40c29b463b4d5df8c348fcf  src/Admin/ConversationsPage.php
9e564e3de261259191e0116a599366d5da3b261de7eccf8948f665a455099c44  src/Admin/SettingsPage.php
840bb8b6c44f3fa172f7fd07e4c0d432b3bf96a54d5c59b8344cdcb0118d85e2  src/AI/ChatOutcome.php
1c199528a6100b49437079f3afd88b14c24317644dda53e69a31cbd7d21c9398  src/AI/ChatService.php
ec2e504746d3cad5e193d2475e7f3f24f140a2f3340bd2782d89f3cc55cd2260  src/AI/ConnectionTestResult.php
21a0b04cc5786750cdb4823c19a2adf7f32471dfd2158e8a6073910d76a13a5f  src/AI/ConnectionTestService.php
7cd7377cf49c87fc67418b8ce6855f2ba4721efd872085386c5079d1bba3a8cb  src/AI/Transport/DeterministicMockTransport.php
7f3e76d6490e71db04525c3b2adc72d60188f7599cf4e656bd684ec2cb689e9e  src/AI/Transport/OpenAiResponsesFileSearchTransport.php
b27299f074b708b28f3c41bad9cf2be652e5709d2c82b26e995fc2dbd2c0de3f  src/AI/Transport/ProviderFailure.php
fa73a6ffe54ccaba9f283de449997717689153ea13364a30c032b0132dd7f3ea  src/AI/Transport/RequestOptions.php
e7c047ccc3475e33a2177a2ddd44dcc2a81e23d7ee3709590ad27839603b1a0c  src/AI/Transport/ResponsesHttpClient.php
844e3d2463b6983768f443e4b0957d78752bcdac948c863b320894b954b6a4ac  src/AI/Transport/ResponsesHttpResponse.php
f03fe791b9cf1a57521939dba36956819fcd801502e7d9428025b8c6e1b7e8a6  src/AI/Transport/ResponseTransport.php
bd1b20222f714dcaa13b7415de1ee216a8bc7db0f0e9f924bc9ff1d3e6e37bba  src/AI/Transport/TransportRequest.php
3948f9327178377bf633223fa3e5237e86272561bfb23db63d7b70aaa00863e7  src/AI/Transport/TransportResult.php
a3c89ffd15e1a0b0afe65061b75a9399565f231725ef2ca0c340b8a0336f5699  src/AI/Transport/UnavailableTransport.php
800cd62449faceccd0d8ab708c99a7580b4aef902210560dcc6dddbc3af8c800  src/AI/Transport/WordPressResponsesHttpClient.php
a098a46b6c6ef17f4fd3706ab038bf6634abfb2e6c883b4604002cf368cff136  src/Api/V1/Contracts/AnswerCacheV1.php
1bc562a68ef248f9644e1e5b7f06e61a760beb03ec4d2accbe8064fc84ee20aa  src/Api/V1/Contracts/ChronologyProviderV1.php
10d70b19d06d828b5f336c845bb0cef838c4002311cb2eda951af73f84bd489c  src/Api/V1/Contracts/CompletedTurnRecorderV1.php
60883aa7336f5c9c8bfd41277ac7fba0e895a424c641f7992ba9ba2d92619bc2  src/Api/V1/Contracts/ProfileResolverV1.php
12753ff085999e4e7ef8112820e6113a7ac953763739e07b9061426075021aec  src/Api/V1/Contracts/WorkspaceExtensionV1.php
b1a9ee0bd58c8b49fe37b6a9b68d63ad4221e567ec78a329c4202f69f2330d5f  src/Api/V1/Support/CompletedTurnEligibilityV1.php
e1503be44891b012a844dddfbc87765b7c0c77d5a36c90536289bacd517c2645  src/Api/V1/Support/ExtensionRegistryV1.php
b5d75dbde63eed506db54c4dbf8290deec88ebbb53b44909ef77f6251fcff31c  src/Api/V1/Value/CachedAnswerV1.php
9b8dc4dc8383185d6ad60e18e03061e1fdf7af70461e26935ffb4ee6707f17e7  src/Api/V1/Value/CacheKeyV1.php
627a314446f899030568f8498a29b812f1192838f43c11df2bd40a598f52df2d  src/Api/V1/Value/ChronologyFactV1.php
fb6d3058fe6f9179491defca9d3c1c2e6f1ffd6ea952b439d936dac33e02d3af  src/Api/V1/Value/CompletedTurnV1.php
ca53c1edc08a72c448f5d0ba530580f9c62e1f95e9b4f58c439c308b079f1120  src/Api/V1/Value/ProfileIdV1.php
64ea8d6563943d96dd83546e99b71a0b33d3ab78ce20f68a5012b954218b1d7f  src/Api/V1/Value/ResolvedProfileV1.php
1e3705ae594bee99338bc64299107a6488c833438c617350c85b0e93469a63e4  src/Api/V1/Value/WorkspaceContextV1.php
49062348878730f2db901f18d3e5ba8f6ab6f2e3efeaa5794fc5e34149c52b53  src/Api/V1/Value/WorkspaceScreenV1.php
6737af20830dfe2ce196c2dab0a2b6f544ad7f9f9190b839e90a9203da446373  src/autoload.php
93a2203bd494a0114b6488abcf3818a486522a5f5d1895dee22fa51cbca8283d  src/Budget/BucketStore.php
ea997dd4e15973a8a984dbae4d5fc4e98109f80914c15b2e337c4eb19bfb2e1e  src/Budget/BudgetGate.php
77f4ceb1c91cf1ecd20d80a510a23e3f7e36c2c4971b47cfafbffe4e04d6c863  src/Budget/ClientScopeResolver.php
0035948ca69c8c57c1e7ab9e808445cb03c72fc34d455719027a72588f776818  src/Budget/ProviderReservation.php
36e2778c0757468f11095137dbdff8c5da035a7f9a7f1c0b6ea013655f8416a4  src/Budget/Reservation.php
acc4e57bbd53453a74b4348da07c790cc533a2fc0aa6ad85fa122cf3f3ecf9f9  src/Budget/WordPressBucketStore.php
b1bfcaa404c5acddb7a1b4c3984d7899f4461ed1e6a0bdf156118b5828703937  src/Configuration/ConnectionFingerprint.php
6d8bf764542d3bcc00b7c1fb34030ba22c80742f26ef35328cd66bb2f06fd6d4  src/Configuration/CoreSettings.php
ab9872f905a4ba38e87c58f2cbb959e0cb3b9d20e45be99b263d3c1f41274205  src/Configuration/ModelCatalogue.php
b904c40aac7d87e3a54cb18cc93a257a1a5d6ea1a25b23e328b5365faf49bf0d  src/Configuration/PanelAppearance.php
73a70cf4a5fdafd4b55b64641e1d3e787b1c038d97d008b618a09fda2f996e28  src/Configuration/Readiness.php
6d14a31943a797d38fc7b43a6c6923e200a0a10b850a1e79b3b79d80c2ae08d9  src/Configuration/SecretResolver.php
971623ee926313a60137b3fbea119fd7fbb330c4d8e8c07cbddffa0eebb9a917  src/Configuration/TransportMode.php
c47e8406bc262041c57ca864b39ca149b010f97ca741a26ea2ca5065b0daf392  src/Conversation/ConversationId.php
22faeaddeb12b334ae950f09a7f11c7912ad3a68508eb10a8753e8c70bf74474  src/Conversation/ConversationPurge.php
abf5c59507fc1c380b3fde7b87c7da9d9ab86578734870dd320ebbcc318179d6  src/Conversation/ConversationRecorder.php
753d76ea2a81e5061d06c1dda5e3d9384c6f4b9b2250d595e82eaea8af0de6c9  src/Conversation/ConversationStore.php
c247121ab84c0f75748a923d20d6ad525bcd4a587e2fbee1a3abd4e4709f4dcb  src/Conversation/ConversationTable.php
7ac99a570d58ca17598b616a8c5adf2ef027796251a522dfc1db9953c6fe82af  src/Conversation/ConversationTurn.php
ff50efed152ed659b9412e83be5f56ba215cb1ed4b6bc9670165188421ec51b6  src/Conversation/WordPressConversationStore.php
7b5b47dd9d2accd823bed3841b7bf0db0fcbd23aa47c9c1a10ab8a7f1f1be0af  src/Diagnostics/FailureLog.php
690001337fa4d6eb199293e4f2d6abf6eabfa85db102639308634baa41009b56  src/Diagnostics/OptionFailureLog.php
253f07288127e629bbf737bffcd2f038791159868f1771f702ab02f444ca3f08  src/Frontend/InteractivePanel.php
f3beaeabe31f62acebad0dec46bcadb0cd826d00fba842c3162188c16f613191  src/Plugin.php
c46d220367202696ffe3f779c736db316af161f2fdaccaf02b0b78cfbd0cd3b9  src/Profile/DefaultProfileResolverV1.php
2651f3e8bb3e98d340cba4d2ca1ff0540827b602ea1d73c5b78e883a825e03c3  src/Rest/ChatRoute.php
7aa8ba97138dbc2f01ed270640833a069a46f5f5063d8903c61b2716693def6b  src/Security/AdminActionGuard.php
97b702e0a6f44fabad503dde6807041cc0bfba6edbd0858de6a8a1b4c864a22e  src/Security/ChatRequestValidator.php
7455a1e68454a9534bbf62d1f2321201cc74993e2efef88f9dccc4821f57ce89  src/Security/PolicyFingerprint.php
685b50b749ab3658715c87092fc58cae52d55104b56babcca2189684a71275fd  src/Security/PromptPolicy.php
aa940232faa795a995c802af228534d70db98e0976ad6bfdecbb6e39197d6fcb  src/Security/PublicError.php
bb263a6abb267446c3682fe9aa95bba43876748a6f18e123ecef1d54b2d312cf  src/Security/QuestionValidator.php
d3034abe1bbdd9568d1eb69ecc2952c950ce47055cf08717603d79fd4c61a8ab  src/Support/Translator.php
1c1ab23ece85f786b19ae9d0471a2ad54ab470c610527b0897fb304c5dbc8fad  uninstall.php
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
    echo "\nRESULT: MATCH. This folder is exactly the rc.9 file set (81-file merged set).\n";
    exit(0);
}
echo "\nRESULT: DOES NOT MATCH rc.9.\n";
exit(1);
