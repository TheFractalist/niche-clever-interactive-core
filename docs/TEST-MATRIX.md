# NCI Core test matrix

| Gate | Deterministic coverage | Environment evidence | Current state |
| --- | --- | --- | --- |
| C0 | Manifest schema, source/asset denylist, ownership/UX review | Docker topology configuration and remote image-manifest readiness | Tested |
| C1 | PHP domain/lifecycle/settings/readiness/validation/policy/API negative tests | WordPress 6.5/PHP 8.1 activation/deactivation fixture | Tested |
| C2 | Save validation, escaping/XSS, shortcode/block and unavailable public state tests | Desktop and 390px fixture settings/public-panel flow; keyboard order and enabled mock flow | Tested |
| C3 | Mock transport, route response, evidence annotation/fingerprint and failure/non-disclosure tests | WordPress 6.5/PHP 8.1 deterministic mock route; segregated live canary later | Tested; mock-only |
| C4 | Rate/budget accounting, trusted proxy, safe URL and preservation tests | WordPress 6.5/PHP 8.1/MariaDB 20-request concurrent fixture | Tested |
| C5 | PHP lint, deterministic JavaScript/package/source/ZIP checks, complete GPL text, PHPCS 3.13.6/WPCS 3.4.1 focused security/database ruleset and Plugin Check 2.1.0 passed. The live transport has pure success, incomplete-search, provider-failure and non-disclosure coverage. Plugin Check emitted only reviewed fixture-directory/text-domain, row-locked rate-SQL and direct-provider-integration warnings. | WordPress 6.5/PHP 8.1/MariaDB completed segregated live File Search plus public-safe response/evidence/failure/accounting checks. WordPress 7.1/PHP 8.3/MariaDB completed mock route/refusal and activation/deactivation preservation. The exact portable ZIP extracted, activated, passed Plugin Check and rolled back on the current fixture. | Technically accepted local candidate; not published |
| C5 visual correction | Dark normalisation, bounded/escaped presentation fields, Core-only administrator tab set, reset/loading/error/evidence markup, source/provenance and package asset allowlist checks. | Desktop and 390px public-panel checks for Classic, Minimal and Dark; administrator workspace, mocked answer/refusal/unavailable states, reset/focus and keyboard order in generated fixtures. Screenshots and comparison artifact remain external and redacted. | Under verification for `1.0.0-rc.2`; C5 technical acceptance retained |

The fixture topology uses only generated NCI settings/data. Existing AskFolio installations are never fixture targets. No test credential, vector-store identifier, corpus material, database dump, ZIP or screenshot is committed.
