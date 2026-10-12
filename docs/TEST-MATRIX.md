# NCI Core test matrix

| Gate | Deterministic coverage | Environment evidence | Current state |
| --- | --- | --- | --- |
| C0 | Manifest schema, source/asset denylist, ownership/UX review | Docker topology configuration and remote image-manifest readiness | Tested |
| C1 | PHP domain/lifecycle/settings/readiness/validation/policy/API negative tests | WordPress 6.5/PHP 8.1 activation/deactivation fixture | Tested |
| C2 | Save validation, escaping/XSS, shortcode/block and unavailable public state tests | Desktop and 390px fixture settings/public-panel flow; keyboard order and enabled mock flow | Tested |
| C3 | Mock transport, answer-only route response, completed-search/fingerprint failure and non-disclosure tests | WordPress 6.5/PHP 8.1 deterministic mock route; segregated live canary later | Tested; mock-only |
| C4 | Rate/budget accounting, trusted proxy and preservation tests | WordPress 6.5/PHP 8.1/MariaDB 20-request concurrent fixture | Tested |
| C5 | PHP lint, deterministic JavaScript/package/source/ZIP checks, complete GPL text, PHPCS 3.13.6/WPCS 3.4.1 focused security/database ruleset and Plugin Check 2.1.0 passed. The live transport has pure success, incomplete-search, provider-failure and non-disclosure coverage. Plugin Check emitted only reviewed fixture-directory/text-domain, row-locked rate-SQL and direct-provider-integration warnings. | WordPress 6.5/PHP 8.1/MariaDB completed segregated live File Search plus public-safe answer/failure/accounting checks. WordPress 7.1/PHP 8.3/MariaDB completed mock route/refusal and activation/deactivation preservation. The exact portable ZIP extracted, activated, passed Plugin Check and rolled back on the current fixture. | Technically accepted historical RC1; not published |
| RC5 evidence-map removal | Removed map configuration, citation parsing, metadata API seam and public source-link rendering; retained completed-search/policy/corpus safeguards and legacy-setting discard test. | Current Test-Site exact-ZIP activation rendered answer-only mocked success, safe incomplete retrieval, AI & Corpus administration and a no-overflow 390px panel; package denylist, Plugin Check and RC5/RC4 rollback restoration passed. | Mock-verified local candidate; no new live provider call |

The fixture topology uses only generated NCI settings/data. Existing AskFolio installations are never fixture targets. No test credential, vector-store identifier, corpus material, database dump, ZIP or screenshot is committed.
