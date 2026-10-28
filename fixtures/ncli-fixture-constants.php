<?php
/**
 * Must be loaded as a mu-plugin before the candidate Core plugin. This is the only place the
 * disposable fixture selects mock transport or its visual streaming pause; neither setting is
 * present in the candidate archive or a normal WordPress configuration.
 */

defined('ABSPATH') || exit;

defined('NCLI_CORE_MOCK_TRANSPORT') || define('NCLI_CORE_MOCK_TRANSPORT', true);
defined('NCLI_CORE_SCOPE_SECRET') || define('NCLI_CORE_SCOPE_SECRET', 'ncli-fixture-scope-only');
$ncliFixtureDelay = (int) getenv('NCLI_FIXTURE_STREAM_DELAY_MS');
if ($ncliFixtureDelay >= 0 && $ncliFixtureDelay <= 1000 && !defined('NCLI_CORE_FIXTURE_STREAM_DELAY_MS')) {
    define('NCLI_CORE_FIXTURE_STREAM_DELAY_MS', $ncliFixtureDelay);
}
