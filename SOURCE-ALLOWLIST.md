# Source and build allowlist

## Status

**C0 implemented policy.** It is deny-by-default, versioned through provenance/nci-source-manifest.json and does not grant blanket extraction permission. The protected checkpoint remains reference-only.

## Permitted material

The permitted set is limited to newly authored Core material, the machine-readable C0 governance/fixture files, and the two manifest-recorded owner-approved visual assets only when their target phase uses them:

| Path set | Permitted purpose | Provenance |
| --- | --- | --- |
| AGENTS.md, README.md, LICENSE, SOURCE-ALLOWLIST.md, progress.md, .gitignore | Governance and continuity | Newly authored |
| docs/**, provenance/** | Architecture, ownership, UX/test records and provenance controls | Newly authored except manifest source metadata |
| fixtures/** | Disposable local topology and instructions, without data snapshots | Newly authored |
| niche-clever-interactive-core.php, src/** | Newly authored Core code | Newly authored or manifest-recorded reconstruction |
| tests/** | Local deterministic checks | Newly authored |
| assets/images/ncli-logo.png, assets/images/ncli-admin-banner.png | Approved NCI visual identity only | Exact manifest entries nci-logo and nci-admin-banner |

## Denied material

Unless Jacob gives a later explicit, per-item approval, do not add:

- AskFolio source, tests, notices, vendors, build material, CSS, JavaScript, graphics or any unmanifested adaptation. The protected checkpoint is reference-only.
- Third-party dependencies, copied licence notices, vendored code, generated code or downloaded packages.
- Niche Clever/AskFolio imagery, logos, banners, screenshots, release assets or WordPress.org assets, except the exact two manifest-recorded owner-approved assets.
- Credentials, provider configuration or identifiers, vector-store/corpus material, visitor data, question/answer records, logs, databases, runtime copies or build/release archives.
- Pro code, Pro schema, migrations, release tags or package artefacts.

## Source and build policy

The C0 fixture topology is authorised only for generated NCI-only local data. No fixture, build or package may include a production credential, corpus, provider ID, database dump, archive record or unapproved asset. A later package action must reject all unlisted inputs before creating an artefact.

Before any commit or later package proposal, inspect the changed paths and confirm:

1. every source file is newly authored or has a complete, approved manifest record before reconstruction;
2. no protected AskFolio identifier, source, asset or dependency was incorporated outside the two exact asset exceptions;
3. no secret, private data, provider/corpus material or release output is present; and
4. the proposed change does not introduce Pro implementation or a functional service.

No manifest item permits copying a whole legacy file. Direct incorporation is false except the two owner-granted binary visual assets.
