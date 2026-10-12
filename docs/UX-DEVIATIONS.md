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
