<#
.SYNOPSIS
    (Re)generates the local TLS CA/certificate used by the Caddy service for
    LAN/phone access (Phase 7).

.DESCRIPTION
    Run this once, and again any time your LAN IP changes (DHCP can reassign
    it) or the certificate expires. It does NOT install the CA into any trust
    store - Windows requires an interactive confirmation dialog for that
    (CryptUI blocks it entirely in a non-interactive/scripted session, by
    design: silently trusting a new root CA is exactly the kind of thing that
    dialog exists to prevent), so there's no way to script around it safely.
    After running this, double-click docker\certs\ca-cert.cer yourself:
        Install Certificate -> Current User -> "Place all certificates in the
        following store" -> Trusted Root Certification Authorities -> Yes to
        the security warning.
    Then restart Caddy to pick up the new certificate:
        docker compose restart caddy
    And install the same docker\certs\ca-cert.cer on each phone that will
    scan QR codes or use the camera scanner - see README.md's "Phone setup"
    section for per-OS steps.

.PARAMETER LanIp
    Overrides auto-detection if you need a specific interface's IP.
#>
param(
    [string]$LanIp
)

$ErrorActionPreference = 'Stop'
$certDir = $PSScriptRoot

if (-not $LanIp) {
    $LanIp = (Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object { $_.InterfaceAlias -notmatch 'Loopback|vEthernet|WSL' -and $_.IPAddress -notlike '169.254.*' } |
        Select-Object -First 1 -ExpandProperty IPAddress)
}

if (-not $LanIp) {
    throw "Could not auto-detect a LAN IP. Re-run with -LanIp <your machine's IP address>."
}

Write-Output "Generating certificate for localhost, 127.0.0.1, and $LanIp ..."

$sanConfPath = Join-Path $certDir 'san.cnf'
@"
[req]
distinguished_name = req_distinguished_name
prompt = no

[req_distinguished_name]
CN = Omni POS Local Dev

# Applied to the CA's own self-signed cert - marks it as an actual
# certificate authority. Without this, Windows/.NET correctly refuse to
# treat it as a trust anchor even if it's sitting in the Root store.
[v3_ca]
basicConstraints = critical, CA:TRUE
keyUsage = critical, keyCertSign, cRLSign

# Applied to the leaf/server cert when the CA signs it.
[v3_req]
basicConstraints = CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1 = localhost
IP.1 = 127.0.0.1
IP.2 = $LanIp
"@ | Set-Content -Path $sanConfPath -Encoding ascii

$env:MSYS_NO_PATHCONV = '1'
$env:OPENSSL_CONF = $sanConfPath

Push-Location $certDir
try {
    Remove-Item -Force -ErrorAction SilentlyContinue ca-key.pem, ca-cert.pem, server-key.pem, server-cert.pem, server.csr, ca-cert.srl

    & openssl genrsa -out ca-key.pem 4096
    & openssl req -x509 -new -nodes -key ca-key.pem -sha256 -days 3650 -out ca-cert.pem -subj "/CN=Omni POS Local Dev CA" -config $sanConfPath -extensions v3_ca
    & openssl genrsa -out server-key.pem 2048
    & openssl req -new -key server-key.pem -out server.csr -config $sanConfPath
    & openssl x509 -req -in server.csr -CA ca-cert.pem -CAkey ca-key.pem -CAcreateserial -out server-cert.pem -days 825 -sha256 -extfile $sanConfPath -extensions v3_req

    if ($LASTEXITCODE -ne 0) {
        throw "openssl failed - see output above."
    }

    # Windows Explorer only offers the double-click "Install Certificate"
    # wizard for a recognized certificate extension - .pem doesn't trigger
    # it, .cer does. Same file, just copied so double-clicking works.
    Copy-Item ca-cert.pem ca-cert.cer -Force
} finally {
    Pop-Location
}

$envPath = Join-Path (Split-Path $certDir -Parent | Split-Path -Parent) '.env'
$envUpdated = $false
if (Test-Path $envPath) {
    $envContent = Get-Content $envPath -Raw
    $newContent = $envContent `
        -replace '(?m)^APP_URL=.*$', "APP_URL=https://$LanIp" `
        -replace '(?m)^VITE_REVERB_PORT=.*$', 'VITE_REVERB_PORT=8443' `
        -replace '(?m)^VITE_REVERB_SCHEME=.*$', 'VITE_REVERB_SCHEME=https'
    if ($newContent -ne $envContent) {
        Set-Content -Path $envPath -Value $newContent -NoNewline
        $envUpdated = $true
    }
}

Write-Output ""
Write-Output "Certificate generated."
if ($envUpdated) {
    Write-Output "Updated .env: APP_URL, VITE_REVERB_PORT and VITE_REVERB_SCHEME now point at"
    Write-Output "https://$LanIp (QR codes and receipts will use this from now on)."
}
Write-Output ""
Write-Output "Two manual steps left (Windows requires a human click for both - there's"
Write-Output "no safe way to script past either):"
Write-Output ""
Write-Output "  1. Double-click docker\certs\ca-cert.cer, then Install Certificate ->"
Write-Output "     Current User -> 'Place all certificates in the following store' ->"
Write-Output "     Trusted Root Certification Authorities -> Yes to the warning."
Write-Output "  2. Install the same file on each phone that will scan QR codes or use"
Write-Output "     the camera scanner - see README.md's 'Phone setup' section."
Write-Output ""
Write-Output "Then apply the .env change and pick up the new certificate:"
Write-Output "    docker compose exec laravel.test php artisan config:clear"
Write-Output "    docker compose restart caddy laravel.test"
Write-Output "    docker compose up vite"
