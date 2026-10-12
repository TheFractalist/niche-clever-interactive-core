# Source and build allowlist — foundation status

## Status

**Implemented policy for this foundation only.** It is deny-by-default and is not a release manifest, extraction approval, package policy or permission to introduce external material.

## Permitted material

The permitted set is limited to newly authored material created in this repository during the authorised Core-foundation package:

| Path set | Permitted purpose | Provenance |
| --- | --- | --- |
| AGENTS.md, README.md, LICENSE, SOURCE-ALLOWLIST.md, progress.md | Governance and foundation record | Newly authored |
| docs/** | Provisional Core architecture and contracts | Newly authored |
| niche-clever-interactive-core.php, src/** | Nonfunctional PHP foundation and contract types | Newly authored |
| tests/** | Pure local contract tests | Newly authored |

## Denied material

Unless Jacob gives a later explicit, per-item approval, do not add:

- AskFolio source, tests, notices, vendors, build material, CSS, JavaScript, graphics or any adaptation derived from it. The protected checkpoint is reference-only.
- Third-party dependencies, copied licence notices, vendored code, generated code or downloaded packages.
- Niche Clever/AskFolio imagery, logos, banners, screenshots, release assets or WordPress.org assets.
- Credentials, provider configuration or identifiers, vector-store/corpus material, visitor data, question/answer records, logs, databases, runtime copies or build/release archives.
- Pro code, a database schema, migrations, WordPress routes/UI, provider calls, installation fixtures, CI workflows, release tags or package artefacts.

## Source and build policy

There is no authorised build or release action in this foundation. If one is later authorised, its inputs must be an approved manifest that permits only the relevant new Core source and independently approved dependencies/assets. It must reject all other repository paths and fail before creating a package.

Before any commit or later package proposal, inspect the changed paths and confirm:

1. every source file is newly authored in this repository;
2. no protected AskFolio identifier, source, asset or dependency was incorporated;
3. no secret, private data, provider/corpus material or release output is present; and
4. the proposed change does not introduce Pro implementation or a functional service.

The future source-extraction allowlist is **reference-only** until individual items receive later written approval. No whole legacy file is authorised for copying.
