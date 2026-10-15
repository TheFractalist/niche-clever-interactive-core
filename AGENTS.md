# Niche Clever Interactive Core — operating guidance

## Authority and entry sequence

1. Jacob's current direct instruction and the dated decision records in the canonical NCI context pack control this repository.
2. Read README.md, docs/ARCHITECTURE.md, SOURCE-ALLOWLIST.md and progress.md before changing Core.
3. This repository is the source authority for Core-only code created after the authorised 12 October 2026 foundation package.
4. The NCI context pack records programme decisions and handback evidence; it is not a build or runtime directory.

## Programme boundaries

- This is non-production Core work. C0 has begun under the continuous Core-first authority; do not represent a completed phase as a release.
- The AskFolio checkpoint is reference-only. Reconstruction requires a prior entry in provenance/nci-source-manifest.json, including pinned source blob, lawful ownership/licence, target method scope, decision reference and test evidence. Never copy a whole mixed legacy file, legacy test, vendor, build, migration, runtime data or corpus.
- The legacy logo recorded in the provenance manifest is the sole legacy-asset exception; the administrator banner is new owner-supplied NCI artwork (manifest id `nci-admin-banner`, adopted 15 October 2026, licensed GPL-2.0-or-later by the owner's declaration). Do not add another legacy image, screenshot or WordPress.org asset without an owner decision and manifest entry.
- Do not add Pro implementation, a production credential/store, existing AskFolio access, release publication, remote tag, WordPress.org work or production deployment.
- Preserve Core as the sole future owner of the public chat route, provider access, credential resolution, request ordering, final evidence validation and public errors. Pro compatibility is provisional and must fail closed.
- Do not put private corpus content, visitor data, logs, provider identifiers or secrets in this repository.

## Workspace layout and where to edit

NCI Core is the direct successor to AskFolio (the AskFolio source remains at `Niche Clever Website Rebrand/plugins/wp-interactive-portfolio` and is reference-only here). Four sibling folders in `C:\Software Dev` hold local-only evidence for this programme. They are not separate applications, they are not git repositories, and Harness excludes them from the catalogue so that NCI Core is the single catalogue entry:

| Folder (in `C:\Software Dev`) | Contents | Edit rule |
| --- | --- | --- |
| `NCI Candidate Artifacts` | Built rc.1 and rc.2 candidate packages and C5 canary, quality, technical and visual evidence runs | Generated evidence; do not edit |
| `NCI Core Candidate Artifacts` | Dated rc3–rc7 candidate packages | Generated evidence; do not edit |
| `NCI Candidate Tooling` | C5 live-canary and fixture scripts, results and standards extracts | Edit only when a task is about candidate tooling |
| `NCI Visual Parity Evidence` | Visual-parity evidence for the rc.2 Core candidate | Generated evidence; do not edit |

All source changes belong in this repository (`niche-clever-interactive-core`), never in those folders. They stay outside the repository deliberately: `tests/source-audit.php` scans every `.php`, `.js` and `.css` file under the repository root, and the fixture containers mount this repository into WordPress. Do not move or copy them under this repository without first deciding how the source audit should treat them. The hand-curated decision pack on Drive (`Niche Clever Interactive`) remains the canonical programme record.

## Change discipline

Make the smallest coherent change within the currently authorised package. Keep PHP compatible with PHP 8.1. Treat the six API seams as provisional contracts, not an invitation to implement either side.

Before committing, inspect git status, the staged diff and the source/asset audit required by SOURCE-ALLOWLIST.md. Do not use broad staging.

## Validation

Run the pure contract suite and PHP syntax checks with the local PHP executable:

    & 'C:\xampp\php\php.exe' tests/contract.php
    & 'C:\xampp\php\php.exe' tests/provenance.php
    & 'C:\xampp\php\php.exe' tests/source-audit.php
    Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }
    git diff --check

These checks do not demonstrate WordPress, browser, provider, storage, security or release behaviour. Run the relevant fixture/browser/static/package tests before recording a later gate. Record validation and remaining gate conditions in progress.md.
