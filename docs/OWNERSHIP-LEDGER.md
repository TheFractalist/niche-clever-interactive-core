# NCI Core ownership ledger

## Core-owned implementation

Core owns the plugin lifecycle, one default profile, fresh ncli_core_settings, schema marker, rate-bucket table, secret resolution, readiness, public route, request order, provider transport, completed-File-Search validation, final answer construction and public error construction. It owns the Core administration workspace and default public panel. From RC9 it also owns the optional conversation history: the `ncli_core_conversation_turns` table, its recording at the end of the validated request path, the Conversations screen, basic deletion, retention purge and `uninstall.php`.

Core API v1 is an extension boundary, not shared implementation. Only its five declared seams may be registered. Core validates all seam output and remains the sole route/provider/credential/retrieval owner.

## Pro-owned implementation

Pro owns additional profiles, chronology, authored questions and collections, contextual placement, Viewer bridge, cache and conversation-history governance (export, retention schedules, per-profile scope and audited erasure) layered over Core's table rather than a second archive. It has independent settings and schema for its own features; it will need a Core seam to send earlier turns as provider context. Its future placement must never select a Core profile by fallback, and it must remain inert when Core is unavailable or incompatible.

## Excluded and retired material

No NCI component may use WPIP settings/transients/constants/tables, existing AskFolio installations, legacy migration, legacy uninstall, edition gates, free-from-premium processing or copied mixed legacy source. The legacy corpus, credentials, provider IDs, screenshots and all unlisted visual assets remain excluded.

## Asset exception

The manifest records only the owner-granted logo and administrator banner. They are not to be copied until their target phase requires them, and package audit must reject every other legacy image.
