$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Dist = Join-Path $Root "dist"
$Stage = Join-Path $Dist "verifact"
if (Test-Path -LiteralPath $Dist) { Remove-Item -LiteralPath $Dist -Recurse -Force }
New-Item -ItemType Directory -Path $Stage -Force | Out-Null
@("verifact-plugin.php", "uninstall.php", "readme.txt", "LICENSE", "assets", "languages", "includes") | ForEach-Object {
    Copy-Item -LiteralPath (Join-Path $Root $_) -Destination $Stage -Recurse -Force
}
Get-ChildItem -LiteralPath $Stage -Recurse -Directory | Where-Object Name -eq "backup-files" | Remove-Item -Recurse -Force
Get-ChildItem -LiteralPath $Stage -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName | Out-Host
    if ($LASTEXITCODE -ne 0) { throw "PHP lint failed: $($_.FullName)" }
}
Compress-Archive -LiteralPath $Stage -DestinationPath (Join-Path $Dist "verifact.zip") -CompressionLevel Optimal
Write-Output (Join-Path $Dist "verifact.zip")