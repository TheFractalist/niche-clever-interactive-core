# NCI Core test matrix

| Gate | Deterministic coverage | Environment evidence | Current state |
| --- | --- | --- | --- |
| C0 | Manifest schema, source/asset denylist, ownership/UX review | Docker topology configuration and remote image-manifest readiness | Tested |
| C1 | PHP domain/lifecycle/settings/readiness/validation/policy/API negative tests | WordPress 6.5/PHP 8.1 activation/deactivation fixture | Tested |
| C2 | Save validation, escaping/XSS, shortcode/block and unavailable public state tests | Desktop fixture settings/public-panel flow; narrow-width and enabled-flow checks remain | Implemented; partial acceptance |
| C3 | Mock transport, evidence annotation and failure/non-disclosure tests | Segregated live canary later | Planned; canary blocked |
| C4 | Rate/budget accounting, trusted proxy, SSRF/URL and preservation tests | Concurrent InnoDB fixture | Planned |
| C5 | PHP lint, PHPUnit, WPCS/PHPCS, Plugin Check, JS/browser, ZIP/licence/source audits | WordPress 6.5/PHP 8.1 and current matrix | Planned |

The fixture topology uses only generated NCI settings/data. Existing AskFolio installations are never fixture targets. No test credential, vector-store identifier, corpus material, database dump, ZIP or screenshot is committed.
