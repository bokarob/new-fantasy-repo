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

    $headerFile = Join-Path $env:TEMP ("notification-readall-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("notification-readall-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('--connect-timeout', '10', '--max-time', '30', '-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("notification-readall-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Notification read-all endpoint smoke checks for TASK-018"
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
    $runEmail = ("notification_readall_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) GET /notifications?filter=unread&limit=50 -> capture unread_count_before"
$unreadBeforeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=unread&limit=50" -Headers @("Authorization: Bearer $token")
if ($unreadBeforeResp.status -ne 200) {
    Write-Host "FAIL: unread precheck failed."
    Write-Host "Status: $($unreadBeforeResp.status)"
    Write-Host "Body: $($unreadBeforeResp.body)"
    exit 1
}
$unreadBefore = -1
try {
    $obj = $unreadBeforeResp.body | ConvertFrom-Json
    $unreadBefore = [int]$obj.data.unread_count
} catch {}
if ($unreadBefore -lt 0) {
    Write-Host "FAIL: could not parse unread_count_before."
    Write-Host "Body: $($unreadBeforeResp.body)"
    exit 1
}
Write-Host "Unread before: $unreadBefore"
Write-Host ""

Write-Host "3) POST /notifications/read-all -> expect 200 ok:true + no-store + read_count>=0"
$readAllResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/notifications/read-all" -Headers @("Authorization: Bearer $token")
$ccReadAll = Header-Value -Headers $readAllResp.headers -Name "Cache-Control"
$okReadAll = $false
$metaEtagNull = $false
$readCount = -1
if ($readAllResp.status -eq 200) {
    try {
        $obj = $readAllResp.body | ConvertFrom-Json
        $okReadAll = ($obj.data.ok -eq $true)
        $metaEtagNull = ($null -eq $obj.meta.etag)
        $readCount = [int]$obj.data.read_count
    } catch {}
}
if ($readAllResp.status -eq 200 -and $ccReadAll -eq "no-store" -and $okReadAll -and $metaEtagNull -and $readCount -ge 0) {
    Write-Host "PASS: read-all response matches Category C contract."
} else {
    Write-Host "FAIL: read-all response/header mismatch."
}
if ($unreadBefore -ge 0 -and $readCount -ne $unreadBefore) {
    Write-Host "NOTE: read_count ($readCount) differs from unread_count_before ($unreadBefore)."
}
Write-Host "Status: $($readAllResp.status)"
Write-Host "Cache-Control: $ccReadAll"
Write-Host "Body: $($readAllResp.body)"
Write-Host ""

Write-Host "4) GET /notifications?filter=unread&limit=5 -> expect unread_count==0 and no items"
$unreadAfterResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/notifications?filter=unread&limit=5" -Headers @("Authorization: Bearer $token")
$unreadAfter = -1
$itemsCount = -1
if ($unreadAfterResp.status -eq 200) {
    try {
        $obj = $unreadAfterResp.body | ConvertFrom-Json
        $unreadAfter = [int]$obj.data.unread_count
        $itemsCount = @($obj.data.items).Count
    } catch {}
}
if ($unreadAfterResp.status -eq 200 -and $unreadAfter -eq 0 -and $itemsCount -eq 0) {
    Write-Host "PASS: unread list is empty after read-all."
} else {
    Write-Host "FAIL: expected no unread notifications after read-all."
}
Write-Host "Status: $($unreadAfterResp.status)"
Write-Host "Body: $($unreadAfterResp.body)"
Write-Host ""

Write-Host "5) No token -> expect 401 AUTH_REQUIRED"
$noTokenResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/notifications/read-all"
if ($noTokenResp.status -eq 401 -and $noTokenResp.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noTokenResp.status)"
Write-Host "Body: $($noTokenResp.body)"
