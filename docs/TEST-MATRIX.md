# NCI Core test matrix

| Gate | Deterministic coverage | Environment evidence | Current state |
| --- | --- | --- | --- |
| C0 | Manifest schema, source/asset denylist, ownership/UX review | Docker topology configuration and remote image-manifest readiness | Tested |
| C1 | PHP domain/lifecycle/settings/readiness/validation/policy/API negative tests | WordPress 6.5/PHP 8.1 activation/deactivation fixture | Tested |
| C2 | Save validation, escaping/XSS, shortcode/block and unavailable public state tests | Desktop and 390px fixture settings/public-panel flow; keyboard order and enabled mock flow | Tested |
| C3 | Mock transport, route response, evidence annotation/fingerprint and failure/non-disclosure tests | WordPress 6.5/PHP 8.1 deterministic mock route; segregated live canary later | Tested; mock-only |
| C4 | Rate/budget accounting, trusted proxy, safe URL and preservation tests | WordPress 6.5/PHP 8.1/MariaDB 20-request concurrent fixture | Tested |
| C5 | PHP lint, deterministic JavaScript/package checks and source/ZIP audit passed; WPCS/PHPCS, Plugin Check and GPL full-text package review pending reviewed tooling/licensing work | WordPress 6.5/PHP 8.1/MariaDB and WordPress current/PHP 8.3/MariaDB route/lifecycle matrices; packaged-copy rollback rehearsal on current fixture | Mock-verified local candidate; live canary deferred |

The fixture topology uses only generated NCI settings/data. Existing AskFolio installations are never fixture targets. No test credential, vector-store identifier, corpus material, database dump, ZIP or screenshot is committed.
