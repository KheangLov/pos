<#
.SYNOPSIS
    Builds a shippable Omni POS release: a sealed application image plus the
    files a customer site needs to run it.

.DESCRIPTION
    Produces dist/<version>/ containing the image tarball, compose file,
    environment template and launcher scripts. The application source is baked
    into the image, so nothing readable is copied onto the customer's disk.

    After building, the script inspects the image and fails the build if
    anything that must never ship (the .git directory, demo seeders, tests, the
    vendor-side licence tooling, dev dependencies) is present.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1 -Version 1.0.0
#>
[CmdletBinding()]
param(
    [string] $Version = '1.0.0',
    [string] $ImageName = 'omnipos',
    [switch] $SkipVerify
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$root = Split-Path -Parent $PSScriptRoot
$tag = "$ImageName`:$Version"
$outDir = Join-Path $root "dist\$Version"

function Write-Step {
    param([string] $Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Fail {
    param([string] $Message)
    Write-Host "BUILD FAILED: $Message" -ForegroundColor Red
    exit 1
}

Write-Step "Building $tag"

Push-Location $root
try {
    docker build --file Dockerfile.prod --tag $tag --tag "$ImageName`:latest" .
    if ($LASTEXITCODE -ne 0) { Fail "docker build returned $LASTEXITCODE" }
}
finally {
    Pop-Location
}

# -------------------------------------------------------------------------
# Verify the image really is clean. A .dockerignore entry is easy to break by
# accident, so the guarantee is checked rather than assumed.
# -------------------------------------------------------------------------
if (-not $SkipVerify) {
    Write-Step "Verifying nothing sensitive shipped"

    $mustNotExist = @(
        '/var/www/html/.git',
        '/var/www/html/.env',
        '/var/www/html/tests',
        '/var/www/html/installer',
        '/var/www/html/database/seeders/BrewHavenSeeder.php',
        '/var/www/html/database/seeders/TechHubSeeder.php',
        '/var/www/html/database/seeders/SalesHistorySeeder.php',
        '/var/www/html/database/factories',
        '/var/www/html/app/Console/Commands/Vendor',
        '/var/www/html/scripts',
        '/var/www/html/Dockerfile.prod',
        '/var/www/html/dist',
        '/var/www/html/vendor/laravel/telescope',
        '/var/www/html/vendor/barryvdh/laravel-debugbar',
        '/var/www/html/vendor/phpunit',
        '/var/www/html/vendor/fakerphp'
    )

    # Built from a single-quoted PowerShell string on purpose. Windows
    # PowerShell mangles embedded double quotes when handing arguments to a
    # native executable, so the shell snippet below contains no quotes at all
    # and relies on these paths having no spaces.
    $checkScript = 'for p in ' + ($mustNotExist -join ' ') +
        '; do if [ -e $p ]; then echo LEAK $p; fi; done; echo DONE'

    $result = docker run --rm --entrypoint sh $tag -c $checkScript
    if ($LASTEXITCODE -ne 0) { Fail "verification container returned $LASTEXITCODE" }
    if ($result -notcontains 'DONE') { Fail 'verification script did not run to completion' }

    $leaks = @($result | Where-Object { $_ -like 'LEAK *' })
    if ($leaks.Count -gt 0) {
        foreach ($leak in $leaks) { Write-Host "  $leak" -ForegroundColor Red }
        Fail "$($leaks.Count) path(s) that must never ship are present in the image"
    }

    # The licence public key can ship; the secret key never can.
    $secretScript = 'grep -rl LICENSE_SECRET_KEY /var/www/html --exclude-dir=vendor 2>/dev/null; echo DONE'
    $secretScan = docker run --rm --entrypoint sh $tag -c $secretScript
    $secretHits = @($secretScan | Where-Object { $_ -like '/var/www/html/*' })
    if ($secretHits.Count -gt 0) {
        foreach ($hit in $secretHits) { Write-Host "  SECRET REFERENCE $hit" -ForegroundColor Red }
        Fail "the image references a licence signing secret"
    }

    Write-Host "  Clean." -ForegroundColor Green
}

# -------------------------------------------------------------------------
# Assemble the deliverable
# -------------------------------------------------------------------------
Write-Step "Assembling dist\$Version"

if (Test-Path $outDir) { Remove-Item -Recurse -Force $outDir }
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$imageTar = Join-Path $outDir "$ImageName-$Version.tar"
docker save --output $imageTar $tag
if ($LASTEXITCODE -ne 0) { Fail "docker save returned $LASTEXITCODE" }

foreach ($file in @('compose.prod.yaml', '.env.production.example', 'start-prod.bat', 'stop-prod.bat')) {
    Copy-Item (Join-Path $root $file) -Destination $outDir
}

# Pin the compose file to the exact version being shipped so a site can never
# silently run an image other than the one it was delivered.
$composePath = Join-Path $outDir 'compose.prod.yaml'
(Get-Content $composePath -Raw).Replace('${OMNIPOS_VERSION:-latest}', $Version) |
    Set-Content $composePath -Encoding utf8

$hash = (Get-FileHash -Algorithm SHA256 -Path $imageTar).Hash
"$hash  $ImageName-$Version.tar" | Set-Content (Join-Path $outDir 'SHA256SUMS.txt') -Encoding utf8

$size = [math]::Round((Get-Item $imageTar).Length / 1MB, 1)

Write-Step "Release ready"
Write-Host "  Output:  $outDir"
Write-Host "  Image:   $ImageName-$Version.tar ($size MB)"
Write-Host "  SHA256:  $hash"
Write-Host ""
Write-Host "  Next, on the customer machine:" -ForegroundColor Yellow
Write-Host "    1. docker load --input $ImageName-$Version.tar"
Write-Host "    2. copy .env.production.example to .env.production and fill it in"
Write-Host "    3. start-prod.bat"
Write-Host "    4. docker compose -f compose.prod.yaml exec app php artisan license:show"
Write-Host "       ...then issue a licence for the fingerprint it prints."
Write-Host ""
