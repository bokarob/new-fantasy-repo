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

    $headerFile = Join-Path $env:TEMP ("me-patch-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("me-patch-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('--connect-timeout', '10', '--max-time', '30', '-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("me-patch-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Me PATCH smoke checks for TASK-012"
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
    $runEmail = ("me_patch_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) GET /me -> capture alias + ETag"
$me1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/me" -Headers @("Authorization: Bearer $token")
$etag1 = Header-Value -Headers $me1.headers -Name "ETag"
$aliasBefore = $null
if ($me1.status -eq 200) {
    try {
        $aliasBefore = ($me1.body | ConvertFrom-Json).data.me.alias
    } catch {}
}
if ($me1.status -eq 200 -and $etag1 -and $aliasBefore) {
    Write-Host "PASS: baseline alias and ETag captured."
} else {
    Write-Host "FAIL: baseline GET /me failed."
    Write-Host "Status: $($me1.status)"
    Write-Host "ETag: $etag1"
    Write-Host "Body: $($me1.body)"
    exit 1
}
Write-Host "Alias before: $aliasBefore"
Write-Host "ETag before: $etag1"
Write-Host ""

Write-Host "3) PATCH /me alias -> expect 200 + ok:true + no-store + meta.etag null"
$newAlias = ("patch" + [int][double]::Parse((Get-Date -UFormat %s)))
$patch1 = Invoke-CurlRequest -Method PATCH -Url "$BaseUrl/me" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    alias = $newAlias
}
$ccPatch = Header-Value -Headers $patch1.headers -Name "Cache-Control"
$patchOk = $false
if ($patch1.status -eq 200) {
    try {
        $objPatch = $patch1.body | ConvertFrom-Json
        $patchOk = ($objPatch.data.ok -eq $true) -and ($null -eq $objPatch.meta.etag)
    } catch {}
}
if ($patch1.status -eq 200 -and $ccPatch -eq "no-store" -and $patchOk) {
    Write-Host "PASS: PATCH returned expected success response."
} else {
    Write-Host "FAIL: PATCH did not match expected success contract."
}
Write-Host "Status: $($patch1.status)"
Write-Host "Cache-Control: $ccPatch"
Write-Host "Body: $($patch1.body)"
Write-Host ""

Write-Host "4) GET /me -> alias changed + ETag differs"
$me2 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/me" -Headers @("Authorization: Bearer $token")
$etag2 = Header-Value -Headers $me2.headers -Name "ETag"
$aliasAfter = $null
if ($me2.status -eq 200) {
    try {
        $aliasAfter = ($me2.body | ConvertFrom-Json).data.me.alias
    } catch {}
}
$aliasChanged = $aliasAfter -eq $newAlias
$etagChanged = $etag2 -and ($etag1 -ne $etag2)
if ($me2.status -eq 200 -and $aliasChanged -and $etagChanged) {
    Write-Host "PASS: alias updated and ETag changed."
} else {
    Write-Host "FAIL: alias/ETag post-check failed."
}
Write-Host "Status: $($me2.status)"
Write-Host "Alias after: $aliasAfter"
Write-Host "ETag after: $etag2"
Write-Host ""

Write-Host "5) PATCH {} -> expect 400 BAD_REQUEST"
$patchEmpty = Invoke-CurlRequest -Method PATCH -Url "$BaseUrl/me" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{}
if ($patchEmpty.status -eq 400 -and $patchEmpty.body -match '"BAD_REQUEST"') {
    Write-Host "PASS: empty PATCH returned 400 BAD_REQUEST."
} else {
    Write-Host "FAIL: expected 400 BAD_REQUEST for empty PATCH."
}
Write-Host "Status: $($patchEmpty.status)"
Write-Host "Body: $($patchEmpty.body)"
Write-Host ""

Write-Host "6) PATCH /me without token -> expect 401 AUTH_REQUIRED"
$patchNoToken = Invoke-CurlRequest -Method PATCH -Url "$BaseUrl/me" -Headers @("Content-Type: application/json") -JsonBody @{
    alias = "NoTokenCheck"
}
if ($patchNoToken.status -eq 401 -and $patchNoToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED for missing token."
}
Write-Host "Status: $($patchNoToken.status)"
Write-Host "Body: $($patchNoToken.body)"
