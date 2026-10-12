# Niche Clever Interactive Core — operating guidance

## Authority and entry sequence

1. Jacob's current direct instruction and the dated decision records in the canonical NCI context pack control this repository.
2. Read README.md, docs/ARCHITECTURE.md, SOURCE-ALLOWLIST.md and progress.md before changing Core.
3. This repository is the source authority for Core-only code created after the authorised 12 October 2026 foundation package.
4. The NCI context pack records programme decisions and handback evidence; it is not a build or runtime directory.

## Foundation boundaries

- This is a non-production foundation, not functional NCI Core 1.0.0.
- All code in this initial foundation is newly authored. The AskFolio checkpoint is reference-only: do not copy, adapt, extract, vendor or otherwise incorporate its code, tests, notices, assets or dependencies without a later per-item written approval.
- Do not add Pro code, a functional chat flow, routes, provider clients, credentials, databases, WordPress installation work, assets, CI, packages, tags or releases.
- Preserve Core as the sole future owner of the public chat route, provider access, credential resolution, request ordering, final evidence validation and public errors. Pro compatibility is provisional and must fail closed.
- Do not put private corpus content, visitor data, logs, provider identifiers or secrets in this repository.

## Change discipline

Make the smallest coherent change within the currently authorised package. Keep PHP compatible with PHP 8.1. Treat the six API seams as provisional contracts, not an invitation to implement either side.

Before committing, inspect git status, the staged diff and the source/asset audit required by SOURCE-ALLOWLIST.md. Do not use broad staging.

## Validation

Run the pure contract suite and PHP syntax checks with the local PHP executable:

    & 'C:\xampp\php\php.exe' tests/contract.php
    Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & 'C:\xampp\php\php.exe' -l $_.FullName }
    git diff --check

These checks do not demonstrate WordPress, browser, provider, storage, security or release behaviour. Record validation and remaining gate conditions in progress.md.
