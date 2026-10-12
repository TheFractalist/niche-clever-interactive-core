# C0 UX gap and deviation ledger

The protected AskFolio UX is evidence only. Core will preserve the useful one-panel workflow, safe unavailable state, limit notice, basic shortcode/block presentation, responsive interaction and keyboard order. It will use NCI identifiers and newly authored markup/CSS/JavaScript.

The following intentional deviations are already accepted by the Core/Pro boundary:

| Legacy behaviour | NCI Core disposition |
| --- | --- |
| Free/Premium edition fences | Removed. Core has no tier state or sales gate. |
| Multiple instance/profile selection | Pro only. Core has one default profile and fails closed for named profiles. |
| Predefined questions, collections and contextual routes | Pro only; they must fail safely when Pro is absent. |
| Persistent conversations/archive | Pro only; Core transcript is browser-session only. |
| Legacy public identifiers and WPIP settings | Replaced by fresh NCI identifiers/settings; no migration. |

Only the manifest-approved logo and admin banner may be reused. Browser screenshots will be redacted and kept outside the repository and context pack; a checksum/reference only appears in phase receipts.

## Visual-parity correction, 12 October 2026

The `1.0.0-rc.2` correction reconstructs the established administrator masthead/tabs/cards/sticky save affordance and the Classic, Minimal and Dark public panel language in NCI-owned PHP, CSS and JavaScript. It is not a redesign. The former Core `editorial` panel setting is a compatibility alias for `dark` and is not exposed in the workspace.

The only direct binary incorporation remains the exact manifest-pinned Niche Clever logo and administrator banner. The protected administrator stylesheet, public stylesheet, public script and renderer are reference-only adaptation sources; no legacy file is copied. The following omissions remain deliberate Core/Pro separations: multi-profile switching, authored question collections, contextual placement, cache controls and archive controls.
