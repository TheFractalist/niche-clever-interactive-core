# NCI Core test matrix

| Gate | Deterministic coverage | Environment evidence | Current state |
| --- | --- | --- | --- |
| C0 | Manifest schema, source/asset denylist, ownership/UX review | Docker topology configuration and remote image-manifest readiness | Tested |
| C1 | PHP domain/lifecycle/settings/readiness/validation/policy/API negative tests | WordPress 6.5/PHP 8.1 activation/deactivation fixture | Tested |
| C2 | Save validation, escaping/XSS, shortcode/block and unavailable public state tests | Desktop and 390px fixture settings/public-panel flow; keyboard order and enabled mock flow | Tested |
| C3 | Mock transport, route response, evidence annotation/fingerprint and failure/non-disclosure tests | WordPress 6.5/PHP 8.1 deterministic mock route; segregated live canary later | Tested; mock-only |
| C4 | Rate/budget accounting, trusted proxy, SSRF/URL and preservation tests | Concurrent InnoDB fixture | Planned |
| C5 | PHP lint, PHPUnit, WPCS/PHPCS, Plugin Check, JS/browser, ZIP/licence/source audits | WordPress 6.5/PHP 8.1 and current matrix | Planned |

The fixture topology uses only generated NCI settings/data. Existing AskFolio installations are never fixture targets. No test credential, vector-store identifier, corpus material, database dump, ZIP or screenshot is committed.
