param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456",
    [int]$LeagueId = 10
)

function Invoke-CurlRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string[]]$Headers = @(),
        [object]$JsonBody = $null
    )

    $headerFile = Join-Path $env:TEMP ("me-delete-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("me-delete-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('--connect-timeout', '10', '--max-time', '30', '-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("me-delete-j-" + [guid]::NewGuid().ToString() + ".json")
            ($JsonBody | ConvertTo-Json -Compress -Depth 6) | Set-Content -Path $jsonFile -NoNewline
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

function New-TestSession {
    $email = ("me_delete_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")

    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/register" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $email
        password = $Password
        alias = "profiledelete"
        lang = "en"
    })
    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/otp/verify" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $email
        otp = $Otp
        purpose = "register"
    })

    $login = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $email
        password = $Password
    }
    if ($login.status -ne 200) {
        return @{ ok = $false; email = $email; token = $null; refresh = $null; raw = $login }
    }

    $token = $null
    $refresh = $null
    try {
        $loginObj = $login.body | ConvertFrom-Json
        $token = $loginObj.data.tokens.access_token
        $refresh = $loginObj.data.tokens.refresh_token
    } catch {}
    return @{
        ok = (-not [string]::IsNullOrWhiteSpace($token) -and -not [string]::IsNullOrWhiteSpace($refresh))
        email = $email
        token = $token
        refresh = $refresh
        raw = $login
    }
}

Write-Host "Profile delete smoke checks for post-M4 follow-up"
Write-Host "Base URL: $BaseUrl"
Write-Host "League: $LeagueId"
Write-Host ""

Write-Host "1) Register/login fresh user"
$session = New-TestSession
if (-not $session.ok) {
    Write-Host "FAIL: could not acquire login session."
    Write-Host "Email: $($session.email)"
    Write-Host "Status: $($session.raw.status)"
    Write-Host "Body: $($session.raw.body)"
    exit 1
}
Write-Host "PASS: session acquired for $($session.email)"
Write-Host ""

Write-Host "2) Create a team so deletion covers associated competitor data"
$builder = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team/builder" -Headers @("Authorization: Bearer $($session.token)")
if ($builder.status -ne 200) {
    Write-Host "FAIL: team builder unavailable."
    Write-Host "Status: $($builder.status)"
    Write-Host "Body: $($builder.body)"
    exit 1
}

$players = @((($builder.body | ConvertFrom-Json).data.players) | Select-Object -First 8)
$create = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @(
    "Authorization: Bearer $($session.token)",
    "Content-Type: application/json"
) -JsonBody @{
    teamname = ("ProfileDelete_" + [int][double]::Parse((Get-Date -UFormat %s)))
    player_ids = @($players | ForEach-Object { [int]$_.player_id })
    captain_player_id = [int]$players[0].player_id
    favorite_team_id = [int]$players[0].team.team_id
}
if ($create.status -ne 200) {
    Write-Host "FAIL: prerequisite team creation failed."
    Write-Host "Status: $($create.status)"
    Write-Host "Body: $($create.body)"
    exit 1
}
Write-Host "PASS: prerequisite team created."
Write-Host ""

Write-Host "3) DELETE /me -> expect 200 ok:true + no-store"
$deleteResp = Invoke-CurlRequest -Method DELETE -Url "$BaseUrl/me" -Headers @("Authorization: Bearer $($session.token)")
$deleteCc = Header-Value -Headers $deleteResp.headers -Name "Cache-Control"
$deleteOk = $false
try {
    $deleteObj = $deleteResp.body | ConvertFrom-Json
    $deleteOk = ($deleteResp.status -eq 200 -and $deleteCc -eq "no-store" -and $deleteObj.data.ok -eq $true -and $null -eq $deleteObj.meta.etag)
} catch {}
if ($deleteOk) {
    Write-Host "PASS: profile delete returned expected success response."
} else {
    Write-Host "FAIL: profile delete response/header mismatch."
}
Write-Host "Status: $($deleteResp.status)"
Write-Host "Cache-Control: $deleteCc"
Write-Host "Body: $($deleteResp.body)"
Write-Host ""
if (-not $deleteOk) { exit 1 }

Write-Host "4) Repeat DELETE /me with same access token -> expect idempotent 200"
$deleteAgain = Invoke-CurlRequest -Method DELETE -Url "$BaseUrl/me" -Headers @("Authorization: Bearer $($session.token)")
if ($deleteAgain.status -eq 200 -and $deleteAgain.body -match '"ok"\s*:\s*true') {
    Write-Host "PASS: repeated delete stayed idempotent."
} else {
    Write-Host "FAIL: repeated delete was not idempotent."
}
Write-Host "Status: $($deleteAgain.status)"
Write-Host "Body: $($deleteAgain.body)"
Write-Host ""

Write-Host "5) GET /me and /home with deleted access token -> expect 401 AUTH_INVALID_TOKEN"
$meAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/me" -Headers @("Authorization: Bearer $($session.token)")
$homeAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $($session.token)")
$meInvalid = ($meAfter.status -eq 401 -and $meAfter.body -match '"AUTH_INVALID_TOKEN"')
$homeInvalid = ($homeAfter.status -eq 401 -and $homeAfter.body -match '"AUTH_INVALID_TOKEN"')
if ($meInvalid -and $homeInvalid) {
    Write-Host "PASS: deleted profile no longer resolves as an authenticated user."
} else {
    Write-Host "FAIL: deleted access token still behaved like an active profile."
}
Write-Host "GET /me status: $($meAfter.status)"
Write-Host "GET /home status: $($homeAfter.status)"
Write-Host ""

Write-Host "6) Refresh token after delete -> expect 401 AUTH_INVALID_TOKEN"
$refreshAfter = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/token/refresh" -Headers @("Content-Type: application/json") -JsonBody @{
    refresh_token = $session.refresh
}
if ($refreshAfter.status -eq 401 -and $refreshAfter.body -match '"AUTH_INVALID_TOKEN"') {
    Write-Host "PASS: refresh token was invalidated."
} else {
    Write-Host "FAIL: refresh token still worked after profile delete."
}
Write-Host "Status: $($refreshAfter.status)"
Write-Host "Body: $($refreshAfter.body)"
Write-Host ""

Write-Host "7) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method DELETE -Url "$BaseUrl/me"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
