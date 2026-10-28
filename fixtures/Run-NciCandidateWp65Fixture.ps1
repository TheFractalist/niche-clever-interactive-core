[CmdletBinding()]
param(
    [ValidateSet('Core', 'Premium', 'Both')]
    [string]$Phase = 'Both',

    [Parameter(Mandatory = $true)]
    [string]$CoreArchive,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-fA-F0-9]{64}$')]
    [string]$CoreArchiveSha256,

    [Parameter(Mandatory = $true)]
    [string]$CoreManifest,

    [string]$PremiumArchive = 'C:\Software Dev\NCI Core Candidate Artifacts\2026-10-17-verify-c\niche-clever-interactive-premium-0.1.0-dev.zip',

    [ValidatePattern('^[a-fA-F0-9]{64}$')]
    [string]$PremiumArchiveSha256 = '9ac01bc13b777858bbcd04829609eba6e84341c320805f8c2a12e00213afd5c3',

    [string]$EvidenceDirectory = ('C:\Software Dev\NCI Core Candidate Artifacts\' + (Get-Date -Format 'yyyy-MM-dd') + '-wp65-php81-fixture-evidence-1.0.3-r4'),

    [ValidateRange(1024, 65535)]
    [int]$BasePort = 8097,

    [switch]$KeepFixture
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$compose = Join-Path $PSScriptRoot 'docker-compose.candidate-wp65.yml'
$pluginCheck = 'C:\Software Dev\NCI Candidate Tooling\c5-standards-2026-10-12\plugin-check-2.1.0-extracted\plugin-check'
$stamp = Get-Date -Format 'yyyyMMddHHmmss'

function Resolve-RequiredFile {
    param([string]$Path, [string]$Label)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "$Label is missing: $Path"
    }

    return (Resolve-Path -LiteralPath $Path).Path
}

function Get-NciSha256 {
    param([string]$Path)

    $getFileHash = Get-Command Get-FileHash -ErrorAction SilentlyContinue
    if ($null -ne $getFileHash) {
        return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
    }

    # Keep the runner usable in a minimal Windows PowerShell host as well as pwsh.
    $stream = [System.IO.File]::OpenRead($Path)
    try {
        $algorithm = [System.Security.Cryptography.SHA256]::Create()
        try {
            return ([System.BitConverter]::ToString($algorithm.ComputeHash($stream))).Replace('-', '').ToLowerInvariant()
        } finally {
            $algorithm.Dispose()
        }
    } finally {
        $stream.Dispose()
    }
}

function Save-NativeLog {
    param(
        [string]$LogPath,
        [scriptblock]$Command
    )

    $output = & $Command 2>&1
    $exitCode = $LASTEXITCODE
    $lines = @($output | ForEach-Object { $_.ToString() })
    Set-Content -LiteralPath $LogPath -Value $lines -Encoding utf8
    if ($exitCode -ne 0) {
        throw "Command failed; see $LogPath"
    }

    return @($output | ForEach-Object { $_.ToString() })
}

function Invoke-NciDocker {
    param(
        [string]$PhaseDirectory,
        [string]$Name,
        [string[]]$Arguments
    )

    return Save-NativeLog -LogPath (Join-Path $PhaseDirectory ($Name + '.log')) -Command {
        & docker @Arguments
    }
}

function Assert-PluginCheckResult {
    param([string]$LogPath)

    $raw = (Get-Content -LiteralPath $LogPath -Raw).Trim()
    if ($raw -eq '') {
        throw 'Plugin Check returned no machine-readable result.'
    }
    if ($raw -match 'Checks complete\. No errors found\.') {
        return [pscustomobject]@{ Findings = 0; Errors = 0; Warnings = 0 }
    }
    try {
        $findings = @($raw | ConvertFrom-Json)
    } catch {
        throw "Plugin Check did not return valid JSON: $($_.Exception.Message)"
    }

    $errors = @($findings | Where-Object { $_.severity -eq 'error' -or $_.type -eq 'error' })
    if ($errors.Count -gt 0) {
        throw "Plugin Check returned $($errors.Count) error finding(s); see $LogPath"
    }

    return [pscustomobject]@{ Findings = $findings.Count; Errors = 0; Warnings = @($findings | Where-Object { $_.severity -eq 'warning' -or $_.type -eq 'warning' }).Count }
}

$coreArchive = Resolve-RequiredFile -Path $CoreArchive -Label 'Core candidate archive'
$coreManifest = Resolve-RequiredFile -Path $CoreManifest -Label 'Core candidate manifest'
$premiumArchive = Resolve-RequiredFile -Path $PremiumArchive -Label 'Premium compatibility archive'
if (-not (Test-Path -LiteralPath $compose -PathType Leaf)) {
    throw "Candidate fixture Compose topology is missing: $compose"
}
if (-not (Test-Path -LiteralPath $pluginCheck -PathType Container)) {
    throw "Plugin Check 2.1.0 extraction is missing: $pluginCheck"
}
if ((Get-NciSha256 $coreArchive) -ne $CoreArchiveSha256.ToLowerInvariant()) {
    throw 'Core archive hash does not match the supplied candidate hash.'
}
if ((Get-NciSha256 $premiumArchive) -ne $PremiumArchiveSha256.ToLowerInvariant()) {
    throw 'Premium archive hash does not match the approved compatible archive.'
}

New-Item -ItemType Directory -Path $EvidenceDirectory -Force | Out-Null
$existingPort = $env:NCLI_FIXTURE_PORT
$existingDelay = $env:NCLI_FIXTURE_STREAM_DELAY_MS

function Invoke-NciPhase {
    param(
        [ValidateSet('core', 'premium')]
        [string]$Kind,
        [int]$Port
    )

    $project = "ncli103r4-$Kind-$stamp".ToLowerInvariant()
    $phaseDirectory = Join-Path $EvidenceDirectory $Kind
    New-Item -ItemType Directory -Path $phaseDirectory -Force | Out-Null
    $env:NCLI_FIXTURE_PORT = [string]$Port
    $env:NCLI_FIXTURE_STREAM_DELAY_MS = '250'
    $baseUrl = "http://127.0.0.1:$Port"
    $wpContainer = ''
    $preserved = $false
    $cleanup = $true

    function Invoke-Compose {
        param([string]$Name, [string[]]$Arguments)
        return Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name $Name -Arguments (@('compose', '-p', $project, '-f', $compose) + $Arguments)
    }

    function Invoke-Wp {
        param([string]$Name, [string[]]$Arguments)
        return Invoke-Compose -Name $Name -Arguments (@('run', '--rm', '--no-deps', 'cli', 'wp', ("--url=$baseUrl")) + $Arguments + @('--allow-root'))
    }

    function Copy-ToWp {
        param([string]$Name, [string]$Source, [string]$Destination)
        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name $Name -Arguments @('cp', $Source, ("${wpContainer}:$Destination")) | Out-Null
    }

    try {
        Invoke-Compose -Name 'compose-up' -Arguments @('up', '--detach', '--wait', 'db', 'wp') | Out-Null
        $wpContainer = ((Invoke-Compose -Name 'wp-container' -Arguments @('ps', '--quiet', 'wp')) | Select-Object -First 1).Trim()
        if ($wpContainer -eq '') {
            throw 'The candidate WordPress container did not start.'
        }

        # Every project name has a fresh pair of named volumes, so this is necessarily a new
        # WordPress database. Avoid polling WP-CLI here: every poll creates a disposable CLI
        # container and makes a failed fresh-install diagnosis needlessly slow.
        $fixturePassword = [guid]::NewGuid().ToString('N')
        Invoke-Wp -Name 'core-install' -Arguments @('core', 'install', '--title=NCI disposable candidate fixture', '--admin_user=ncli_fixture_admin', "--admin_password=$fixturePassword", '--admin_email=fixture-admin@example.invalid', '--skip-email') | Out-Null

        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'create-mu-plugin-directory' -Arguments @('exec', $wpContainer, 'mkdir', '-p', '/var/www/html/wp-content/mu-plugins') | Out-Null
        Copy-ToWp -Name 'copy-fixture-constants' -Source (Join-Path $PSScriptRoot 'ncli-fixture-constants.php') -Destination '/var/www/html/wp-content/mu-plugins/ncli-fixture-constants.php'
        Invoke-Wp -Name 'enable-debug-log' -Arguments @('config', 'set', 'WP_DEBUG_LOG', 'true', '--raw') | Out-Null
        Invoke-Wp -Name 'disable-debug-display' -Arguments @('config', 'set', 'WP_DEBUG_DISPLAY', 'false', '--raw') | Out-Null
        Copy-ToWp -Name 'copy-core-archive' -Source $coreArchive -Destination '/var/www/html/ncli-core-candidate.zip'
        Copy-ToWp -Name 'copy-core-manifest' -Source $coreManifest -Destination '/var/www/html/ncli-core-manifest.sha256'
        Copy-ToWp -Name 'copy-install-verifier' -Source (Join-Path $root 'scripts\verify-install.php') -Destination '/var/www/html/ncli-verify-install.php'
        foreach ($helper in @('wp65-bootstrap.php', 'wp65-core-assert.php', 'wp65-http-probe.php', 'wp65-toggle-progressive.php', 'wp65-uninstall-mode.php', 'wp65-uninstall-assert.php', 'wp65-extension-assert.php', 'wp65-premium-assert.php')) {
            Copy-ToWp -Name ('copy-' + [IO.Path]::GetFileNameWithoutExtension($helper)) -Source (Join-Path $PSScriptRoot $helper) -Destination ('/var/www/html/' + $helper)
        }

        Invoke-Wp -Name 'core-install-plugin' -Arguments @('plugin', 'install', '/var/www/html/ncli-core-candidate.zip', '--activate', '--force') | Out-Null
        Invoke-Wp -Name 'fixture-bootstrap' -Arguments @('eval-file', '/var/www/html/wp65-bootstrap.php') | Out-Null
        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'installed-core-manifest' -Arguments @('exec', $wpContainer, 'php', '/var/www/html/ncli-verify-install.php', '/var/www/html/wp-content/plugins/niche-clever-interactive', '/var/www/html/ncli-core-manifest.sha256') | Out-Null
        Invoke-Wp -Name 'active-plugins' -Arguments @('plugin', 'list', '--status=active', '--format=json') | Out-Null
        Invoke-Wp -Name 'wordpress-version' -Arguments @('core', 'version') | Out-Null
        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'php-version' -Arguments @('exec', $wpContainer, 'php', '-v') | Out-Null
        Invoke-Wp -Name 'mariadb-version' -Arguments @('db', 'query', 'SELECT VERSION()', '--skip-column-names') | Out-Null
        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'network-inspect' -Arguments @('network', 'inspect', ($project + '_fixture')) | Out-Null
        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'image-digests' -Arguments @('image', 'inspect', 'wordpress:6.5-php8.1-apache', 'wordpress:cli-php8.1', 'mariadb:10.11') | Out-Null

        Copy-ToWp -Name 'copy-plugin-check' -Source $pluginCheck -Destination '/var/www/html/wp-content/plugins/'
        Invoke-Wp -Name 'plugin-check-activate' -Arguments @('plugin', 'activate', 'plugin-check') | Out-Null
        Invoke-Wp -Name 'plugin-check' -Arguments @('plugin', 'check', 'niche-clever-interactive', '--format=json', '--categories=general,plugin_repo,security,performance,accessibility') | Out-Null
        $pluginCheckResult = Assert-PluginCheckResult -LogPath (Join-Path $phaseDirectory 'plugin-check.log')

        Invoke-Wp -Name 'core-assert' -Arguments @('eval-file', '/var/www/html/wp65-core-assert.php') | Out-Null
        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'http-json-fallback' -Arguments @('exec', $wpContainer, 'php', '/var/www/html/wp65-http-probe.php', 'json', [string]$Port) | Out-Null
        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'http-sse' -Arguments @('exec', $wpContainer, 'php', '/var/www/html/wp65-http-probe.php', 'sse', [string]$Port) | Out-Null
        Invoke-Wp -Name 'progressive-off' -Arguments @('eval-file', '/var/www/html/wp65-toggle-progressive.php', 'off') | Out-Null
        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'http-progressive-disabled' -Arguments @('exec', $wpContainer, 'php', '/var/www/html/wp65-http-probe.php', 'json', [string]$Port) | Out-Null
        Invoke-Wp -Name 'progressive-on' -Arguments @('eval-file', '/var/www/html/wp65-toggle-progressive.php', 'on') | Out-Null

        if ($Kind -eq 'core') {
            Invoke-Wp -Name 'extension-absent' -Arguments @('eval-file', '/var/www/html/wp65-extension-assert.php', 'absent') | Out-Null
            foreach ($extension in @('incompatible', 'malformed', 'throwing')) {
                $source = Join-Path $PSScriptRoot ('extensions\ncli-fixture-' + $extension)
                Copy-ToWp -Name ('copy-extension-' + $extension) -Source $source -Destination '/var/www/html/wp-content/plugins/'
                Invoke-Wp -Name ('activate-extension-' + $extension) -Arguments @('plugin', 'activate', ('ncli-fixture-' + $extension)) | Out-Null
                Invoke-Wp -Name ('assert-extension-' + $extension) -Arguments @('eval-file', '/var/www/html/wp65-extension-assert.php', $extension) | Out-Null
                Invoke-Wp -Name ('deactivate-extension-' + $extension) -Arguments @('plugin', 'deactivate', ('ncli-fixture-' + $extension)) | Out-Null
            }

            Invoke-Wp -Name 'uninstall-retain-mode' -Arguments @('eval-file', '/var/www/html/wp65-uninstall-mode.php', 'retain') | Out-Null
            Invoke-Wp -Name 'uninstall-retain' -Arguments @('plugin', 'uninstall', 'niche-clever-interactive', '--deactivate') | Out-Null
            Invoke-Wp -Name 'uninstall-retain-assert' -Arguments @('eval-file', '/var/www/html/wp65-uninstall-assert.php', 'retained') | Out-Null
            Invoke-Wp -Name 'core-reinstall-retained' -Arguments @('plugin', 'install', '/var/www/html/ncli-core-candidate.zip', '--activate', '--force') | Out-Null
            Invoke-Wp -Name 'uninstall-delete-mode' -Arguments @('eval-file', '/var/www/html/wp65-uninstall-mode.php', 'delete') | Out-Null
            Invoke-Wp -Name 'uninstall-delete' -Arguments @('plugin', 'uninstall', 'niche-clever-interactive', '--deactivate') | Out-Null
            Invoke-Wp -Name 'uninstall-delete-assert' -Arguments @('eval-file', '/var/www/html/wp65-uninstall-assert.php', 'deleted') | Out-Null
            Invoke-Wp -Name 'core-final-install' -Arguments @('plugin', 'install', '/var/www/html/ncli-core-candidate.zip', '--activate', '--force') | Out-Null
            Invoke-Wp -Name 'core-final-bootstrap' -Arguments @('eval-file', '/var/www/html/wp65-bootstrap.php') | Out-Null
            Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'installed-core-final-manifest' -Arguments @('exec', $wpContainer, 'php', '/var/www/html/ncli-verify-install.php', '/var/www/html/wp-content/plugins/niche-clever-interactive', '/var/www/html/ncli-core-manifest.sha256') | Out-Null
        }

        if ($Kind -eq 'premium') {
            Copy-ToWp -Name 'copy-premium-archive' -Source $premiumArchive -Destination '/var/www/html/nci-premium-compatible.zip'
            Invoke-Wp -Name 'premium-install-plugin' -Arguments @('plugin', 'install', '/var/www/html/nci-premium-compatible.zip', '--activate', '--force') | Out-Null
            Invoke-Wp -Name 'premium-assert' -Arguments @('eval-file', '/var/www/html/wp65-premium-assert.php') | Out-Null
            Invoke-Wp -Name 'active-plugins-premium' -Arguments @('plugin', 'list', '--status=active', '--format=json') | Out-Null
        }

        Invoke-NciDocker -PhaseDirectory $phaseDirectory -Name 'debug-log-clean' -Arguments @('exec', $wpContainer, 'sh', '-lc', 'if [ -f /var/www/html/wp-content/debug.log ]; then if grep -E "PHP (Warning|Fatal|Parse)|WordPress database error" /var/www/html/wp-content/debug.log; then exit 1; else echo "PASS: debug log contains no warning, fatal, parse or database-error line"; fi; else echo "PASS: no debug log was written"; fi') | Out-Null
        $receipt = [ordered]@{
            candidate = '1.0.3-r4 local provenance; public version 1.0.3'
            phase = $Kind
            project = $project
            url = $baseUrl
            network = 'internal-only fixture network'
            provider = 'not configured; deterministic mock transport only'
            core_archive_sha256 = Get-NciSha256 $coreArchive
            premium_archive_sha256 = if ($Kind -eq 'premium') { Get-NciSha256 $premiumArchive } else { $null }
            installed_manifest = 'match; see installed-core-manifest.log'
            plugin_check = $pluginCheckResult
            cleanup = if ($KeepFixture) { 'deferred for browser evidence' } else { 'completed in finally' }
        }
        $receipt | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $phaseDirectory 'receipt.json') -Encoding utf8
        $preserved = $KeepFixture.IsPresent
        $cleanup = -not $preserved
    } finally {
        if ($cleanup) {
            Invoke-Compose -Name 'compose-down' -Arguments @('down', '--volumes', '--remove-orphans') | Out-Null
        } else {
            [pscustomobject]@{ project = $project; compose = $compose; port = $Port; cleanup = "docker compose -p $project -f `"$compose`" down --volumes --remove-orphans" } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $phaseDirectory 'pending-cleanup.json') -Encoding utf8
        }
    }

    return [pscustomobject]@{ Phase = $Kind; Project = $project; Url = $baseUrl; Preserved = $preserved; Evidence = $phaseDirectory }
}

try {
    $phases = @(switch ($Phase) {
        'Core' { 'core' }
        'Premium' { 'premium' }
        default { 'core'; 'premium' }
    })
    $results = @()
    for ($index = 0; $index -lt $phases.Count; $index++) {
        $results += Invoke-NciPhase -Kind ($phases[$index]) -Port ($BasePort + $index)
    }
    $results | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath (Join-Path $EvidenceDirectory 'fixture-run-summary.json') -Encoding utf8
    $results | Format-Table -AutoSize
} finally {
    if ($null -eq $existingPort) { Remove-Item Env:NCLI_FIXTURE_PORT -ErrorAction SilentlyContinue } else { $env:NCLI_FIXTURE_PORT = $existingPort }
    if ($null -eq $existingDelay) { Remove-Item Env:NCLI_FIXTURE_STREAM_DELAY_MS -ErrorAction SilentlyContinue } else { $env:NCLI_FIXTURE_STREAM_DELAY_MS = $existingDelay }
}
