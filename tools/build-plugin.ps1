$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Dist = Join-Path $Root "dist"
$TempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$Stage = Join-Path $TempRoot "verifact-wordpress-3.5.0\verifact"
$Zip = Join-Path $Dist "verifact-3.5.0.zip"
$ResolvedStage = [IO.Path]::GetFullPath($Stage)
if (-not $ResolvedStage.StartsWith($TempRoot,[StringComparison]::OrdinalIgnoreCase)) { throw "Unsafe release stage path" }
if (Test-Path -LiteralPath (Split-Path -Parent $Stage)) { Remove-Item -LiteralPath (Split-Path -Parent $Stage) -Recurse -Force }
New-Item -ItemType Directory -Path $Stage,$Dist -Force | Out-Null
if (Test-Path -LiteralPath $Zip) {
    $Stamp = [DateTime]::UtcNow.ToString("yyyyMMddTHHmmssZ")
    $BackupDir = Join-Path $Dist "backup-files"
    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
    Copy-Item -LiteralPath $Zip -Destination (Join-Path $BackupDir "verifact-3.5.0.zip.backup.$Stamp")
}
@("verifact-plugin.php", "uninstall.php", "readme.txt", "Installation.md", "LICENSE", "assets", "languages", "includes") | ForEach-Object {
    Copy-Item -LiteralPath (Join-Path $Root $_) -Destination $Stage -Recurse -Force
}
Get-ChildItem -LiteralPath $Stage -Recurse -Directory | Where-Object Name -eq "backup-files" | Remove-Item -Recurse -Force
Get-ChildItem -LiteralPath $Stage -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName | Out-Host
    if ($LASTEXITCODE -ne 0) { throw "PHP lint failed: $($_.FullName)" }
}
Compress-Archive -LiteralPath $Stage -DestinationPath $Zip -CompressionLevel Optimal -Force
Remove-Item -LiteralPath (Split-Path -Parent $Stage) -Recurse -Force
Write-Output $Zip
