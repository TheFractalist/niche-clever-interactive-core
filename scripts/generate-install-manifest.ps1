[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$InputDirectory,

    [Parameter(Mandatory = $true)]
    [string]$OutputFile,

    [Parameter(Mandatory = $true)]
    [string]$CandidateLabel
)

$ErrorActionPreference = 'Stop'
$source = (Resolve-Path -LiteralPath $InputDirectory).Path
if (-not (Test-Path -LiteralPath $source -PathType Container)) {
    throw "InputDirectory is not a directory: $InputDirectory"
}

$textExtensions = @('.php', '.js', '.css', '.md', '.txt', '.json')
$rows = foreach ($file in Get-ChildItem -LiteralPath $source -Recurse -File) {
    $relative = $file.FullName.Substring($source.Length).TrimStart('\', '/') -replace '\\', '/'
    $bytes = [System.IO.File]::ReadAllBytes($file.FullName)
    if ($textExtensions -contains $file.Extension.ToLowerInvariant()) {
        $text = [System.Text.Encoding]::UTF8.GetString($bytes).Replace("`r`n", "`n")
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($text)
    }
    $hash = [System.Security.Cryptography.SHA256]::Create().ComputeHash($bytes)
    $hex = [System.BitConverter]::ToString($hash).Replace('-', '').ToLowerInvariant()
    '{0}  {1}' -f $hex, $relative
}

$parent = Split-Path -Parent $OutputFile
if (-not (Test-Path -LiteralPath $parent -PathType Container)) {
    New-Item -ItemType Directory -Path $parent -Force | Out-Null
}
# Windows PowerShell 5.1 has no utf8NoBOM encoding, so write BOM-free UTF-8 through .NET.
$lines = @("# $CandidateLabel") + @($rows | Sort-Object)
[System.IO.File]::WriteAllText([System.IO.Path]::GetFullPath($OutputFile), ($lines -join "`r`n") + "`r`n", (New-Object System.Text.UTF8Encoding $false))
Write-Output "Manifest: $OutputFile"
Write-Output "Files:    $(@($rows).Count)"
