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

    $headerFile = Join-Path $env:TEMP ("notifications-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("notifications-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('--connect-timeout', '10', '--max-time', '30', '-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("notifications-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Notifications endpoint smoke checks for TASK-016"
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
    $runEmail = ("notifications_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) GET /notifications?filter=all&limit=5 -> expect 200 + Category A headers"
$n1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=all&limit=5" -Headers @("Authorization: Bearer $token")
$cc1 = Header-Value -Headers $n1.headers -Name "Cache-Control"
$etag1 = Header-Value -Headers $n1.headers -Name "ETag"
$metaEtagMatches = $false
$hasItemsArray = $false
if ($n1.status -eq 200) {
    try {
        $obj = $n1.body | ConvertFrom-Json
        $metaEtagMatches = $obj.meta.etag -eq $etag1
        $hasItemsArray = $obj.data.items -is [array]
    } catch {}
}
if ($n1.status -eq 200 -and $cc1 -eq "private, must-revalidate" -and $etag1 -and $metaEtagMatches -and $hasItemsArray) {
    Write-Host "PASS: /notifications all returned expected envelope and cache headers."
} else {
    Write-Host "FAIL: /notifications all did not meet expected response/header requirements."
}
Write-Host "Status: $($n1.status)"
Write-Host "Cache-Control: $cc1"
Write-Host "ETag: $etag1"
Write-Host "Body: $($n1.body)"
Write-Host ""

if ($etag1) {
    Write-Host "3) Repeat with If-None-Match -> expect 304"
    $n304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=all&limit=5" -Headers @(
        "Authorization: Bearer $token",
        "If-None-Match: $etag1"
    )
    if ($n304.status -eq 304) {
        Write-Host "PASS: conditional request returned 304 Not Modified."
    } else {
        Write-Host "FAIL: expected 304 Not Modified."
    }
    Write-Host "Status: $($n304.status)"
    Write-Host "Body length: $($n304.body.Length)"
    Write-Host ""
}

Write-Host "4) GET /notifications?filter=unread&limit=5 -> expect items all is_read=false"
$nu = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=unread&limit=5" -Headers @("Authorization: Bearer $token")
$unreadOnly = $false
if ($nu.status -eq 200) {
    try {
        $obj = $nu.body | ConvertFrom-Json
        $items = @($obj.data.items)
        $unreadOnly = $true
        foreach ($it in $items) {
            if ($it.is_read -ne $false) {
                $unreadOnly = $false
                break
            }
        }
    } catch {}
}
if ($nu.status -eq 200 -and $unreadOnly) {
    Write-Host "PASS: unread filter returned only unread items."
} else {
    Write-Host "FAIL: unread filter response invalid."
}
Write-Host "Status: $($nu.status)"
Write-Host "Body: $($nu.body)"
Write-Host ""

Write-Host "5) Invalid filter -> expect 400 BAD_REQUEST"
$badFilter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=nope" -Headers @("Authorization: Bearer $token")
if ($badFilter.status -eq 400 -and $badFilter.body -match '"BAD_REQUEST"') {
    Write-Host "PASS: invalid filter returned 400 BAD_REQUEST."
} else {
    Write-Host "FAIL: expected 400 BAD_REQUEST for invalid filter."
}
Write-Host "Status: $($badFilter.status)"
Write-Host "Body: $($badFilter.body)"
Write-Host ""

Write-Host "6) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
