param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "phase_d_auth_test@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456"
)

function Invoke-CurlRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string[]]$Headers = @(),
        [object]$JsonBody = $null
    )

    $headerFile = Join-Path $env:TEMP ("contact-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("contact-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('--connect-timeout', '10', '--max-time', '30', '-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("contact-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
            ($JsonBody | ConvertTo-Json -Compress) | Set-Content -Path $jsonFile -NoNewline
            $args += @('--data-binary', "@$jsonFile")
        }

        & curl.exe @args | Out-Null

        $headersRaw = if (Test-Path $headerFile) { [string](Get-Content -Raw $headerFile) } else { "" }
        $bodyRaw = if (Test-Path $bodyFile) { [string](Get-Content -Raw $bodyFile) } else { "" }
        $status = 0
        $statusMatches = [regex]::Matches($headersRaw, "HTTP/\d\.\d\s+(\d+)")
        if ($statusMatches.Count -gt 0) {
            $status = [int]$statusMatches[$statusMatches.Count - 1].Groups[1].Value
        }

        return @{
            status = $status
            headers = $headersRaw
            body = $bodyRaw.Trim()
        }
    } finally {
        Remove-Item -Path $headerFile -Force -ErrorAction SilentlyContinue
        Remove-Item -Path $bodyFile -Force -ErrorAction SilentlyContinue
        if ($null -ne $jsonFile) {
            Remove-Item -Path $jsonFile -Force -ErrorAction SilentlyContinue
        }
    }
}

function Header-Value {
    param([string]$Headers, [string]$Name)
    $m = [regex]::Match($Headers, "(?im)^" + [regex]::Escape($Name) + ":\s*(.+)$")
    if ($m.Success) { return $m.Groups[1].Value.Trim() }
    return $null
}

Write-Host "Contact endpoint smoke checks for TASK-014"
Write-Host "Base URL: $BaseUrl"
Write-Host ""

Write-Host "1) Login (register+verify fallback)"
$runEmail = $Email
$login = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
    email = $runEmail
    password = $Password
}
$token = $null
if ($login.status -eq 200) {
    try { $token = (($login.body | ConvertFrom-Json).data.tokens.access_token) } catch {}
}
if (-not $token) {
    $runEmail = ("contact_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/register" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $runEmail; password = $Password; alias = "phase_d"; lang = "en"
    })
    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/otp/verify" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $runEmail; otp = $Otp; purpose = "register"
    })
    $login = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $runEmail; password = $Password
    }
    if ($login.status -eq 200) {
        try { $token = (($login.body | ConvertFrom-Json).data.tokens.access_token) } catch {}
    }
}
if (-not $token) {
    Write-Host "FAIL: could not acquire access token."
    Write-Host "Status: $($login.status)"
    Write-Host "Body: $($login.body)"
    exit 1
}
Write-Host "PASS: access token acquired."
Write-Host ""

Write-Host "2) POST /contact valid -> expect 200 ok:true + no-store"
$valid = Invoke-CurlRequest -Method POST -Url "$BaseUrl/contact" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    subject = "Bug report"
    message = "I cannot open the Team tab."
    context = @{
        app_version = "1.0.0"
        league_id = 1
    }
}
$cc = Header-Value -Headers $valid.headers -Name "Cache-Control"
$ok = $false
if ($valid.status -eq 200) {
    try {
        $obj = $valid.body | ConvertFrom-Json
        $ok = ($obj.data.ok -eq $true) -and ($null -eq $obj.meta.etag)
    } catch {}
}
if ($valid.status -eq 200 -and $cc -eq "no-store" -and $ok) {
    Write-Host "PASS: valid /contact request succeeded."
} else {
    Write-Host "FAIL: valid /contact request did not match expected response."
}
Write-Host "Status: $($valid.status)"
Write-Host "Cache-Control: $cc"
Write-Host "Body: $($valid.body)"
Write-Host ""

Write-Host "3) POST missing message -> expect 422 VALIDATION_ERROR"
$missing = Invoke-CurlRequest -Method POST -Url "$BaseUrl/contact" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    subject = "Missing message"
    context = @{ app_version = "1.0.0" }
}
if ($missing.status -eq 422 -and $missing.body -match '"VALIDATION_ERROR"') {
    Write-Host "PASS: missing message returned 422 VALIDATION_ERROR."
} else {
    Write-Host "FAIL: expected 422 VALIDATION_ERROR for missing message."
}
Write-Host "Status: $($missing.status)"
Write-Host "Body: $($missing.body)"
Write-Host ""

Write-Host "4) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/contact" -Headers @("Content-Type: application/json") -JsonBody @{
    message = "No auth"
}
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
