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

    $headerFile = Join-Path $env:TEMP ("notification-read-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("notification-read-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('--connect-timeout', '10', '--max-time', '30', '-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("notification-read-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Notification read endpoint smoke checks for TASK-017"
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
    $runEmail = ("notification_read_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) GET /notifications?filter=unread&limit=1"
$unreadOne = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=unread&limit=1" -Headers @("Authorization: Bearer $token")
if ($unreadOne.status -ne 200) {
    Write-Host "FAIL: unread precheck failed."
    Write-Host "Status: $($unreadOne.status)"
    Write-Host "Body: $($unreadOne.body)"
    exit 1
}

$notificationId = $null
try {
    $obj = $unreadOne.body | ConvertFrom-Json
    if ($obj.data.items -is [array] -and $obj.data.items.Count -gt 0) {
        $notificationId = [int]$obj.data.items[0].notification_id
    }
} catch {}

if (-not $notificationId) {
    Write-Host "SKIP: no unread notifications available; nothing to mark as read."
    exit 0
}
Write-Host "Using notification_id=$notificationId"
Write-Host ""

$allBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=all&limit=5" -Headers @("Authorization: Bearer $token")
if ($allBefore.status -ne 200) {
    Write-Host "FAIL: could not fetch all notifications before read."
    Write-Host "Status: $($allBefore.status)"
    Write-Host "Body: $($allBefore.body)"
    exit 1
}
$etagBefore = Header-Value -Headers $allBefore.headers -Name "ETag"
Write-Host "Captured ETag before read: $etagBefore"
Write-Host ""

Write-Host "3) POST /notifications/{id}/read -> expect 200 ok:true + no-store + meta.etag=null"
$readResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/notifications/$notificationId/read" -Headers @("Authorization: Bearer $token")
$ccRead = Header-Value -Headers $readResp.headers -Name "Cache-Control"
$okRead = $false
$metaEtagNull = $false
if ($readResp.status -eq 200) {
    try {
        $obj = $readResp.body | ConvertFrom-Json
        $okRead = ($obj.data.ok -eq $true)
        $metaEtagNull = ($null -eq $obj.meta.etag)
    } catch {}
}
if ($readResp.status -eq 200 -and $ccRead -eq "no-store" -and $okRead -and $metaEtagNull) {
    Write-Host "PASS: read endpoint returned expected Category C response."
} else {
    Write-Host "FAIL: read endpoint response/header mismatch."
}
Write-Host "Status: $($readResp.status)"
Write-Host "Cache-Control: $ccRead"
Write-Host "Body: $($readResp.body)"
Write-Host ""

Write-Host "4) GET unread list after read -> verify id not present"
$unreadAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=unread&limit=5" -Headers @("Authorization: Bearer $token")
$idStillPresent = $false
if ($unreadAfter.status -eq 200) {
    try {
        $obj = $unreadAfter.body | ConvertFrom-Json
        foreach ($it in @($obj.data.items)) {
            if ([int]$it.notification_id -eq $notificationId) {
                $idStillPresent = $true
                break
            }
        }
    } catch {}
}
if ($unreadAfter.status -eq 200 -and -not $idStillPresent) {
    Write-Host "PASS: notification no longer appears in unread list."
} else {
    Write-Host "FAIL: notification still present in unread list."
}
Write-Host "Status: $($unreadAfter.status)"
Write-Host "Body: $($unreadAfter.body)"
Write-Host ""

Write-Host "5) Revalidate all list with previous ETag -> expect changed state (not 304)"
$allAfterCond = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=all&limit=5" -Headers @(
    "Authorization: Bearer $token",
    "If-None-Match: $etagBefore"
)
$etagAfter = Header-Value -Headers $allAfterCond.headers -Name "ETag"
if ($allAfterCond.status -eq 200 -and $etagAfter -and $etagAfter -ne $etagBefore) {
    Write-Host "PASS: list changed and ETag rotated after read."
} else {
    Write-Host "FAIL: expected changed list/etag after read."
}
Write-Host "Status: $($allAfterCond.status)"
Write-Host "ETag before: $etagBefore"
Write-Host "ETag after: $etagAfter"
Write-Host "Body: $($allAfterCond.body)"
Write-Host ""

Write-Host "6) Invalid id (999999999) -> expect 404 NOTIFICATION_NOT_FOUND"
$missing = Invoke-CurlRequest -Method POST -Url "$BaseUrl/notifications/999999999/read" -Headers @("Authorization: Bearer $token")
if ($missing.status -eq 404 -and $missing.body -match '"NOTIFICATION_NOT_FOUND"') {
    Write-Host "PASS: invalid notification id returned NOTIFICATION_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 NOTIFICATION_NOT_FOUND."
}
Write-Host "Status: $($missing.status)"
Write-Host "Body: $($missing.body)"
Write-Host ""

Write-Host "7) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/notifications/$notificationId/read"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
