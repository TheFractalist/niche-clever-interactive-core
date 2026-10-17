<?php
/**
 * Verifies an installed Niche Clever Interactive plugin folder against the exact
 * rc.10 file set (111 files, from the working tree when this manifest was generated; regenerate it after any source change). Read-only: it changes nothing.
 *
 * Usage:  C:\xampp\php\php.exe verify-install.php "C:\xampp\htdocs\Test-Site\wp-content\plugins\niche-clever-interactive"
 *
 * Text files are compared after normalising CRLF to LF, so a Windows checkout
 * with autocrlf does not cause false differences. Binary files compare raw.
 */
$expected = <<<'MANIFEST'
ea4c9bc39487f449f8c7c02fc9102ffc71a72ca7a6246e61ed229bdddb73c792  CHANGELOG.md
edaef632cbb643e4e7a221717a6c441a4c1a7c918e6e4d56debc3d8739b233f6  COPYING
d4c4bbd2b2f0c85952f4b1618e3a114465f4b566afc1ebdef465030c56696f94  LICENSE
bd44566b0f64ac5d7bb0fee23cc14434e88a1f26bdac157809393fa6413c5533  README.md
e26e5f4a1f1272e85ca1c447626d5d1b528d144d64ae1e9d88d06e721491cfea  assets/css/admin.css
59ae9562daa8c748ecd58e6ef615b5fa4beb30cf3458030b6c6e9961c386e91d  assets/css/public.css
8cce4f63d628a7da81b6fa617788b9e552a1021db06a27c75cf4e67252ef2a89  assets/images/ncli-admin-banner.png
e83a3f4933240d02f3d8eb88faecc9da92665cf1a6243e461b74f66c36beef8c  assets/images/ncli-logo.png
f500efa37593f000c5613c1bfb0e026ce235bc8bb8373cf5a083d3436cb46c73  assets/js/admin.js
36381347996a415a5560b9307ea2b1a599cd04a805b667bbe93db531fa29c01f  assets/js/public.js
4ebc0b930e42138140cb67d796f0990511035bdb39c33f56abc9ba4fe6936630  blocks/interactive/block.json
131a2e302ffff963df29725865875d481240eb7e111bda63a4f729163587c36a  blocks/interactive/render.php
d6ab6603bbe6381a518944b5f0223cb52b28f70661633f688a65f3c2d83b80fd  niche-clever-interactive-core.php
8955e49803da756373b1380ccad8a80a2bd12b6c149fb654ca57fa9098c60b2f  readme.txt
c0937aa1136071699672bc2a08a5edbbeab1c3fdbc8698a52c37cb84370b427a  src/AI/ChatOutcome.php
667d10597f2648dc838b36e98e25645329e17ec3c10b27ebeef14631caac1614  src/AI/ChatService.php
ec2e504746d3cad5e193d2475e7f3f24f140a2f3340bd2782d89f3cc55cd2260  src/AI/ConnectionTestResult.php
a13b9188108504aac8349189fce1e49ad20c58ba6065f1765c7e9cf4ac038ae5  src/AI/ConnectionTestService.php
7cd7377cf49c87fc67418b8ce6855f2ba4721efd872085386c5079d1bba3a8cb  src/AI/Transport/DeterministicMockTransport.php
403a671e54b723742b2afa9205e3e602aa79ebcc1f225c0a5b9a631c3e2b0c82  src/AI/Transport/OpenAiResponsesFileSearchTransport.php
b27299f074b708b28f3c41bad9cf2be652e5709d2c82b26e995fc2dbd2c0de3f  src/AI/Transport/ProviderFailure.php
fa73a6ffe54ccaba9f283de449997717689153ea13364a30c032b0132dd7f3ea  src/AI/Transport/RequestOptions.php
f03fe791b9cf1a57521939dba36956819fcd801502e7d9428025b8c6e1b7e8a6  src/AI/Transport/ResponseTransport.php
e7c047ccc3475e33a2177a2ddd44dcc2a81e23d7ee3709590ad27839603b1a0c  src/AI/Transport/ResponsesHttpClient.php
844e3d2463b6983768f443e4b0957d78752bcdac948c863b320894b954b6a4ac  src/AI/Transport/ResponsesHttpResponse.php
ea70a8328af9aa2ad05635160cc513b5a824be07f843ff5e4ada5b2983ae31c8  src/AI/Transport/TransportRequest.php
ef877334518775e00acb72b32dc12be84b979ac6bf7bccc8186269a8bf2c7f7b  src/AI/Transport/TransportResult.php
a3c89ffd15e1a0b0afe65061b75a9399565f231725ef2ca0c340b8a0336f5699  src/AI/Transport/UnavailableTransport.php
0a651d6abdfeac9c3e0de0c51e3637484301896c7085702521d50a23b4e9b1fc  src/AI/Transport/WordPressResponsesHttpClient.php
0c9ed90ebf4ab13fff6ecfd8b28cad760de8d6d26f844d2884178e1eb394cc00  src/Admin/ConversationsPage.php
65ffbbdb74a14aa29ff442a09c165642c3cb891accf6c02262659a34a74e9728  src/Admin/ExtensionScreens.php
c1b3c4f1af090e68ffa277f6f92ba2926e667ef2e63b53bbdd9ca232ebd8947b  src/Admin/HelpRenderer.php
8371ac0a17b5ca01904aad135b0c042c5400b2021d0989f447cea4a2ca9ee64a  src/Admin/ProfileConnectionTest.php
351ce70474ec7d91d70531d896c439f530b1a9fe7f507d142a735f3f8a57ce88  src/Admin/ProfileFieldRenderer.php
dfd08a32d794f37744a1fba63784e83d10f25ea769f7b67a2a9215cd5e8ab25d  src/Admin/ProfileHub.php
9e49841711c56f98b6a076123f08dcb898595281f305fd50126da804c8a57b11  src/Admin/SettingsPage.php
b6e1c0d878c9da4b38f5942986ebb0b01a2f983c05de05969c40300fee66d2a1  src/Admin/SiteSettingsPage.php
147abb51fd541fd7f964d61dbe5f5c4ca469043796e165bebab77b70e38c223c  src/Api/V1/Contracts/AnswerCacheV1.php
1bc562a68ef248f9644e1e5b7f06e61a760beb03ec4d2accbe8064fc84ee20aa  src/Api/V1/Contracts/ChronologyProviderV1.php
10d70b19d06d828b5f336c845bb0cef838c4002311cb2eda951af73f84bd489c  src/Api/V1/Contracts/CompletedTurnRecorderV1.php
0c48f0d92fbd88b06cdc64221bc2b0e00e3acae37ac21b5f44858edf06947494  src/Api/V1/Contracts/ConversationArchiveV1.php
738c44d460c57079ab81f8fe5d403a52fcad00490b7e8a089fc6ca3889e91313  src/Api/V1/Contracts/ConversationContextV1.php
0da4e6ac0d3a4d8eb3ca434cca53a935e86b53a41fbb632ac0bab5549e51ebc7  src/Api/V1/Contracts/ConversationGovernanceV1.php
6b6147a2cf3c2b011988a26789b86076088cf2f9cef9acaafa3c06dabdbe2d6a  src/Api/V1/Contracts/EvidenceCitationsV1.php
094ab0c11231d081976730f4843b5006e820bbfbb248ed91e1dd6df89c8b85f8  src/Api/V1/Contracts/PanelColoursProviderV1.php
6ddbb751567f93cce704e6ce4081f237c709e8d6418004eb04c80e317ffaaac1  src/Api/V1/Contracts/ProfileEditorV1.php
60883aa7336f5c9c8bfd41277ac7fba0e895a424c641f7992ba9ba2d92619bc2  src/Api/V1/Contracts/ProfileResolverV1.php
0220eab6e511271ee4997b1414318a7b659bd801076d7a3728a5daa6def52685  src/Api/V1/Contracts/ProfileWorkspaceV1.php
d28fac139382f9d4d2a4dc0476e32e8c36ae7a5c237636134a76891cdbc7950a  src/Api/V1/Contracts/ViewerAccessV1.php
12753ff085999e4e7ef8112820e6113a7ac953763739e07b9061426075021aec  src/Api/V1/Contracts/WorkspaceExtensionV1.php
b1a9ee0bd58c8b49fe37b6a9b68d63ad4221e567ec78a329c4202f69f2330d5f  src/Api/V1/Support/CompletedTurnEligibilityV1.php
14754976351667b5a334eccf8fec466f0e87d4dce70834554905d4e377d20f13  src/Api/V1/Support/ExtensionRegistryV1.php
aab8294f11e1203cf1e5bb46fe543fdc2ee87d5693a09dd49e771a80adcd867c  src/Api/V1/Value/AccessDecisionV1.php
8db16fcb74383b7c58ee70ed2c50be784b762e8bacc5c89b822698158586603c  src/Api/V1/Value/ApprovedLinkV1.php
07640b1f99cb7807b000908d6142a2ea46f703755be1d553544cc3c39169c2bc  src/Api/V1/Value/ArchivedTurnV1.php
0f3d3debd503956b7d63d11e2c1f3d0957db9f4c1bc7b4c9d7a634cdbc5315b2  src/Api/V1/Value/CacheKeyV1.php
1a55a8d327ec6f0340a1ddae80014df5604702d57c94f309da9837b8efedd45a  src/Api/V1/Value/CachedAnswerV1.php
63d324f77e807392c0fe24040489b2c666f37130f6a10eb81f16860491d16be8  src/Api/V1/Value/ChronologyFactV1.php
67fee482266b3b9cf30456d1c43ad0aa4d678285f93295e63b21b5977a1787b0  src/Api/V1/Value/CompletedTurnV1.php
24a036f9ccd449b1b31bd20c49dfbf60b6bc192ca9c82edde0e73516896481d5  src/Api/V1/Value/EarlierTurnV1.php
9cf30bbc91cc50b3e095bf20fc39b76d473049962f84a38cd32194c2bd839cea  src/Api/V1/Value/EvidenceCitationV1.php
7b928b4363e2372caf382de560c7e2f81b880f3c5e10128ff76b2e38ae8d07eb  src/Api/V1/Value/PanelColoursV1.php
0e7707b03219721da51e7c1677bd51b46d661ba9fc3cdb6afb270af3371fa07f  src/Api/V1/Value/ProfileEditV1.php
ca53c1edc08a72c448f5d0ba530580f9c62e1f95e9b4f58c439c308b079f1120  src/Api/V1/Value/ProfileIdV1.php
14359c3f299b6d4dd12a404cd39f2937b8d6f6c038aac2edfd9e846c94eadf71  src/Api/V1/Value/ProfileSectionV1.php
e4327ba8d9dc7570d3927abd6bc93492f81ee3178071f214bb5117e68e427e9e  src/Api/V1/Value/ProfileSummaryV1.php
042470c1bc7294598bd78ecc72d32534f6c635fe8d9c1bddd3ec2db40935ad6a  src/Api/V1/Value/ResolvedProfileV1.php
f9f2e9a7ddbc950d1ffffdc0db626d46de8b9a490f0e1c0c0f68f75e275b7b0b  src/Api/V1/Value/WorkspaceContextV1.php
6a56bf1448a66620581e62fe96677520352098f271993b3de14dd6c50255a8fa  src/Api/V1/Value/WorkspaceScreenV1.php
93a2203bd494a0114b6488abcf3818a486522a5f5d1895dee22fa51cbca8283d  src/Budget/BucketStore.php
29d2edb8ae799ef69bd50a7c815d9d9eca02a0a6d063212b8215318497834198  src/Budget/BudgetGate.php
77f4ceb1c91cf1ecd20d80a510a23e3f7e36c2c4971b47cfafbffe4e04d6c863  src/Budget/ClientScopeResolver.php
0035948ca69c8c57c1e7ab9e808445cb03c72fc34d455719027a72588f776818  src/Budget/ProviderReservation.php
36e2778c0757468f11095137dbdff8c5da035a7f9a7f1c0b6ea013655f8416a4  src/Budget/Reservation.php
acc4e57bbd53453a74b4348da07c790cc533a2fc0aa6ad85fa122cf3f3ecf9f9  src/Budget/WordPressBucketStore.php
e1a2688496dbf47c14e0c605e03a9d0e903e164d9bb84e7e7d33036a13e46b67  src/Configuration/ConnectionFingerprint.php
0fa6b2f895621f2f6132f03cb96d48665cf0edd5b31f25cf5b07e9e0093737ee  src/Configuration/CoreSettings.php
183a7122355a923b06ea8e3f3737559c811c3ab541e94d00e2c225ddc3d71992  src/Configuration/ModelCatalogue.php
1db74b6577d15befb5b6d4e2b7c64128a69d8b742d3e78ef5f1e3453be15e3fb  src/Configuration/ModelSettings.php
40a6e3b3b22d9a93fb3dfcc740eb92285b430e0d9a11ad3d574fdd399ddeebf0  src/Configuration/PanelAppearance.php
c6cc8c0ae5bd927c3591b84245cf57c72a4143da0061c8b62b3487d554c697c5  src/Configuration/Readiness.php
6d14a31943a797d38fc7b43a6c6923e200a0a10b850a1e79b3b79d80c2ae08d9  src/Configuration/SecretResolver.php
971623ee926313a60137b3fbea119fd7fbb330c4d8e8c07cbddffa0eebb9a917  src/Configuration/TransportMode.php
3bddc1924862cf05982f31d78b5a76d503b9e9bb4bdf468094372fcad55e4a6b  src/Conversation/ConversationCriteriaStore.php
c47e8406bc262041c57ca864b39ca149b010f97ca741a26ea2ca5065b0daf392  src/Conversation/ConversationId.php
da670097a1e6479c053baab2d6af55c6e31c92a850ca48f9db2cddf6d73b2cad  src/Conversation/ConversationPurge.php
eb77879dcdf9a541de842b864b6882737d029ae7d37891bbfc525c233cf9aef8  src/Conversation/ConversationRecorder.php
753d76ea2a81e5061d06c1dda5e3d9384c6f4b9b2250d595e82eaea8af0de6c9  src/Conversation/ConversationStore.php
c247121ab84c0f75748a923d20d6ad525bcd4a587e2fbee1a3abd4e4709f4dcb  src/Conversation/ConversationTable.php
7ac99a570d58ca17598b616a8c5adf2ef027796251a522dfc1db9953c6fe82af  src/Conversation/ConversationTurn.php
8fb6b01e2777d204ab2eeb2e8a29685acfcf5c82701761b9f33d9e2ee9d7572a  src/Conversation/GuardedConversationArchive.php
40b751e146b5ffaa50b986d05a3ca9d5320450d7cac13610a98eecb5acc76d96  src/Conversation/WordPressConversationStore.php
7b5b47dd9d2accd823bed3841b7bf0db0fcbd23aa47c9c1a10ab8a7f1f1be0af  src/Diagnostics/FailureLog.php
690001337fa4d6eb199293e4f2d6abf6eabfa85db102639308634baa41009b56  src/Diagnostics/OptionFailureLog.php
44dfd46702ab2132dbf05067d0c506a9efec78791a46853783a880293de5e66a  src/Frontend/InteractivePanel.php
68d876db3a54908e170d0454a3f523bc739f30955b07a06f7984593aebecd327  src/Plugin.php
c46d220367202696ffe3f779c736db316af161f2fdaccaf02b0b78cfbd0cd3b9  src/Profile/DefaultProfileResolverV1.php
76e45627b517895df7bc0f175b3992233025ba3ce4e9f28a2932635ada291c38  src/Profile/OptionProfileTestRecords.php
a06cc1a4cdf45f9aec2911a3e8fc8cd0ff1c89310e142247d1f899ba59227a4a  src/Profile/ProfileDirectory.php
e830ab9f5280ceacb2cad9c705da631e689bfdce0f56329d3e3bd331257077ca  src/Profile/ProfileRuntime.php
ccdd314b783cce60a9ecf375bb3b41c0cef09f85863e740a4b22fa7248a1e727  src/Profile/ProfileTestRecords.php
c1c8900f147bbdd3a77ab9e1e0184d6b2bce4bd9f76cd9167dd3da1e11774228  src/Rest/ChatRoute.php
7aa8ba97138dbc2f01ed270640833a069a46f5f5063d8903c61b2716693def6b  src/Security/AdminActionGuard.php
e9d52ef60a63b03b88d8efab2f0902446b9e903bc6c0494d6a4d2f53997e0efd  src/Security/ChatRequestValidator.php
b1426f484964f0efeab583e6bf57c2d1170129157db64a4434c31afed8ab5bf8  src/Security/PolicyFingerprint.php
aae18de3e325ba4289765bccdf5a39b73c79c17f68b7d11f3f9a96d0e1671873  src/Security/PromptPolicy.php
63541db85158149cb2162e2779beb0049ee77e36d94a6146a0ae09ebf62fe18c  src/Security/PublicError.php
bb263a6abb267446c3682fe9aa95bba43876748a6f18e123ecef1d54b2d312cf  src/Security/QuestionValidator.php
d3034abe1bbdd9568d1eb69ecc2952c950ce47055cf08717603d79fd4c61a8ab  src/Support/Translator.php
6737af20830dfe2ce196c2dab0a2b6f544ad7f9f9190b839e90a9203da446373  src/autoload.php
1c1ab23ece85f786b19ae9d0471a2ad54ab470c610527b0897fb304c5dbc8fad  uninstall.php
MANIFEST;
$textExtensions = ['php', 'js', 'css', 'md', 'txt', 'json'];
$path = $argv[1] ?? '';
if ($path === '' || !is_dir($path)) {
    fwrite(STDERR, "Usage: php verify-install.php <plugin-folder>\n");
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
    echo "\nRESULT: MATCH. This folder is exactly the rc.10 file set (111 files).\n";
    exit(0);
}
echo "\nRESULT: DOES NOT MATCH rc.10.\n";
exit(1);
