# Disposable local fixture topology

This directory is for generated NCI-only fixture data. It must not connect to an existing local WordPress installation, an AskFolio database, a provider store or a production service.

`docker-compose.candidate-wp65.yml` and `Run-NciCandidateWp65Fixture.ps1` provide the clean-candidate topology. Each invocation creates a new, timestamped Compose project with WordPress `6.5-php8.1-apache`, MariaDB `10.11` and a PHP 8.1 WP-CLI container. The Compose network is internal-only. The runner installs an exact supplied Core ZIP, copies only the fixture mock constants as an MU plugin, verifies the candidate manifest, and writes receipts outside Git before removing only the project and volumes that it created.

The fixture has no provider configuration. `NCLI_CORE_MOCK_TRANSPORT` selects the deterministic mock transport, and its optional fixture delay only makes the pending state observable. Never put a provider key, corpus, vector-store identifier, production dump or customer data in the Compose file, constants file, volume or receipt.

For the local `1.0.3-r4` evidence run, use the archive, SHA-256 and manifest generated from its verified expansion:

```powershell
& .\fixtures\Run-NciCandidateWp65Fixture.ps1 `
  -Phase Both `
  -CoreArchive 'C:\Software Dev\NCI Core Candidate Artifacts\2026-10-27-progressive-answers-1.0.3-r4\niche-clever-interactive-1.0.3.zip' `
  -CoreArchiveSha256 '238cf57c02b0279d301bd10e79b20f498d91c616a5df7fc452ab123fa010a644' `
  -CoreManifest 'C:\Software Dev\NCI Core Candidate Artifacts\2026-10-27-progressive-answers-1.0.3-r4\niche-clever-interactive-1.0.3-r4.install-manifest.sha256'
```

The Premium phase defaults to the archived compatible `0.1.0-dev` ZIP and its recorded SHA-256. A caller validating another separately built Premium candidate must supply both `-PremiumArchive` and its exact `-PremiumArchiveSha256`; the runner does not read, copy or modify the Premium source checkout. The runner records command receipts, image digests, installed-manifest checks, Plugin Check output, HTTP assertions and debug-log result under the requested evidence root. The caller may use `-KeepFixture` only while collecting additional authorised observation; run the printed project-specific Compose `down --volumes --remove-orphans` command afterwards. Do not run destructive Docker commands against any unrelated project or volume.
