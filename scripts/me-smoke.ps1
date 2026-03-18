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

    $headerFile = Join-Path $env:TEMP ("me-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("me-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('--connect-timeout', '10', '--max-time', '30', '-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("me-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Me endpoint smoke checks for TASK-011"
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
    $runEmail = ("me_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) GET /me -> expect 200 + Category A headers"
$me1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/me" -Headers @("Authorization: Bearer $token")
$cc1 = Header-Value -Headers $me1.headers -Name "Cache-Control"
$etag1 = Header-Value -Headers $me1.headers -Name "ETag"
$hasMe = $false
$metaEtagMatches = $false
$langNormalized = $false
if ($me1.status -eq 200) {
    try {
        $obj = $me1.body | ConvertFrom-Json
        $hasMe = $null -ne $obj.data.me -and $null -ne $obj.data.me.profile_id -and $null -ne $obj.data.me.created_at
        $metaEtagMatches = $obj.meta.etag -eq $etag1
        $langValue = [string]$obj.data.me.lang
        $langNormalized = ($langValue -eq $langValue.ToLowerInvariant())
    } catch {}
}
if ($me1.status -eq 200 -and $cc1 -eq "private, must-revalidate" -and $etag1 -and $hasMe -and $metaEtagMatches -and $langNormalized) {
    Write-Host "PASS: /me returned 200 with expected envelope and cache headers."
} else {
    Write-Host "FAIL: /me did not meet expected response/header requirements."
}
Write-Host "Status: $($me1.status)"
Write-Host "Cache-Control: $cc1"
Write-Host "ETag: $etag1"
Write-Host "Body: $($me1.body)"
Write-Host ""

if ($etag1) {
    Write-Host "3) Repeat with If-None-Match -> expect 304"
    $me304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/me" -Headers @(
        "Authorization: Bearer $token",
        "If-None-Match: $etag1"
    )
    if ($me304.status -eq 304) {
        Write-Host "PASS: conditional request returned 304 Not Modified."
    } else {
        Write-Host "FAIL: expected 304 Not Modified."
    }
    $etag304 = Header-Value -Headers $me304.headers -Name "ETag"
    Write-Host "If-None-Match sent: $etag1"
    Write-Host "Status: $($me304.status)"
    Write-Host "ETag returned: $etag304"
    Write-Host "Body length: $($me304.body.Length)"
    Write-Host ""
}

Write-Host "4) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/me"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
