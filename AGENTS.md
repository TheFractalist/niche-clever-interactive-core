# Niche Clever Interactive Core — operating guidance

## Authority and entry sequence

1. Jacob's current direct instruction and the dated decision records in the canonical NCI context pack control this repository.
2. Read README.md, docs/ARCHITECTURE.md, SOURCE-ALLOWLIST.md and progress.md before changing Core.
3. This repository is the source authority for Core-only code created after the authorised 12 October 2026 foundation package.
4. The NCI context pack records programme decisions and handback evidence; it is not a build or runtime directory.

## Programme boundaries

- This is non-production Core work. C0 has begun under the continuous Core-first authority; do not represent a completed phase as a release.
- The AskFolio checkpoint is reference-only. Reconstruction requires a prior entry in provenance/nci-source-manifest.json, including pinned source blob, lawful ownership/licence, target method scope, decision reference and test evidence. Never copy a whole mixed legacy file, legacy test, vendor, build, migration, runtime data or corpus.
- The two legacy visual assets specifically recorded in the provenance manifest are the sole asset exception. Do not add another legacy image, screenshot or WordPress.org asset without an owner decision and manifest entry.
- Do not add Pro implementation, a production credential/store, existing AskFolio access, release publication, remote tag, WordPress.org work or production deployment.
- Preserve Core as the sole future owner of the public chat route, provider access, credential resolution, request ordering, final evidence validation and public errors. Pro compatibility is provisional and must fail closed.
- Do not put private corpus content, visitor data, logs, provider identifiers or secrets in this repository.

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
