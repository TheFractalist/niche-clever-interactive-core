[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$OutputDirectory
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$output = [System.IO.Path]::GetFullPath($OutputDirectory)
if ($output.StartsWith($root, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Candidate output must be outside the Core repository.'
}

$version = '1.0.0-rc.1'
$packageName = 'niche-clever-interactive-core'
$archiveName = "$packageName-$version.zip"
$stage = Join-Path $output "$packageName-$version-staging"
$packageRoot = Join-Path $stage $packageName
$archive = Join-Path $output $archiveName
$checksum = Join-Path $output "$archiveName.sha256"

New-Item -ItemType Directory -Path $output -Force | Out-Null
if (Test-Path -LiteralPath $stage) {
    Remove-Item -LiteralPath $stage -Recurse -Force
}
Remove-Item -LiteralPath $archive -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath $checksum -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null

$files = @(
    'niche-clever-interactive-core.php',
    'LICENSE',
    'README.md',
    'CHANGELOG.md'
)
foreach ($file in $files) {
    Copy-Item -LiteralPath (Join-Path $root $file) -Destination (Join-Path $packageRoot $file)
}
foreach ($directory in @('assets', 'blocks', 'src')) {
    Copy-Item -LiteralPath (Join-Path $root $directory) -Destination (Join-Path $packageRoot $directory) -Recurse
}

$denyPath = '(?i)(^|/)(\.git|tests|fixtures|docs|provenance|scripts|vendor|node_modules|artifacts)(/|$)|wpip|askfolio|\.env|\.sql$'
$denyContent = @('wpip_', 'wp-interactive-portfolio', 'askfolio.php', 'sk-', 'BEGIN OPENAI')
$filesInPackage = Get-ChildItem -LiteralPath $packageRoot -Recurse -File
foreach ($file in $filesInPackage) {
    $relative = $file.FullName.Substring($packageRoot.Length + 1).Replace('\', '/')
    if ($relative -match $denyPath) {
        throw "Package denylist path matched: $relative"
    }
    if ($file.Extension -in @('.php', '.js', '.css')) {
        $content = [System.IO.File]::ReadAllText($file.FullName)
        foreach ($token in $denyContent) {
            if ($content.IndexOf($token, [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
                throw "Package denylist content matched in $relative"
            }
        }
    }
}

Compress-Archive -LiteralPath $packageRoot -DestinationPath $archive -CompressionLevel Optimal
$hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $archive).Hash.ToLowerInvariant()
"$hash  $archiveName" | Set-Content -LiteralPath $checksum -NoNewline
Remove-Item -LiteralPath $stage -Recurse -Force

[pscustomobject]@{
    Archive = $archive
    Checksum = $checksum
    Sha256 = $hash
    FileCount = $filesInPackage.Count
} | ConvertTo-Json -Compress
