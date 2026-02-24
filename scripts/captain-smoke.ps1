param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "phase_d_auth_test@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456",
    [int]$InvalidLeagueId = 999999
)

function Invoke-CurlRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string[]]$Headers = @(),
        [object]$JsonBody = $null
    )

    $headerFile = Join-Path $env:TEMP ("captain-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("captain-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }

        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("captain-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
            ($JsonBody | ConvertTo-Json -Compress) | Set-Content -Path $jsonFile -NoNewline
            $args += @('--data-binary', "@$jsonFile")
        }

        & curl.exe @args | Out-Null

        $headersRaw = if (Test-Path $headerFile) { Get-Content -Raw $headerFile } else { "" }
        $bodyRaw = if (Test-Path $bodyFile) { Get-Content -Raw $bodyFile } else { "" }
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
    param(
        [string]$Headers,
        [string]$Name
    )
    $match = [regex]::Match($Headers, "(?im)^" + [regex]::Escape($Name) + ":\s*(.+)$")
    if ($match.Success) {
        return $match.Groups[1].Value.Trim()
    }
    return $null
}

Write-Host "Captain smoke checks for TASK-005"
Write-Host "Base URL: $BaseUrl"
Write-Host ""
Write-Host "Prereq: app/db running and AUTH_FIXED_OTP=$Otp if registration fallback is needed."
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
    $runEmail = ("captain_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/register" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $runEmail
        password = $Password
        alias = "phase_d"
        lang = "en"
    })
    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/otp/verify" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $runEmail
        otp = $Otp
        purpose = "register"
    })

    $login = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $runEmail
        password = $Password
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

Write-Host "2) Pick league_id from /home (prefer one with competitor)"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = $null
if ($homeResp.status -eq 200) {
    try {
        $homeObj = $homeResp.body | ConvertFrom-Json
        $leagues = @($homeObj.data.league_selector.leagues)
        foreach ($l in $leagues) {
            if ($null -ne $l.competitor -and $null -ne $l.competitor.competitor_id) {
                $leagueId = [int]$l.league_id
                break
            }
        }
        if (-not $leagueId -and $leagues.Count -gt 0) {
            $leagueId = [int]$leagues[0].league_id
        }
    } catch {}
}
if (-not $leagueId) {
    $leagueId = 1
}
Write-Host "Using league_id=$leagueId"
Write-Host ""

Write-Host "3) Fetch /team and capture roster + captain + ETag"
$teamBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
if ($teamBefore.status -ne 200) {
    Write-Host "FAIL: expected 200 from /team, got $($teamBefore.status)"
    Write-Host "Body: $($teamBefore.body)"
    exit 1
}
$etagBefore = Header-Value -Headers $teamBefore.headers -Name "ETag"
$teamBeforeObj = $teamBefore.body | ConvertFrom-Json
$positions = @($teamBeforeObj.data.roster.positions)
$currentCaptain = [int]$teamBeforeObj.data.roster.captain_player_id

$benchPlayer = $null
$starterPlayer = $null
foreach ($pos in $positions) {
    $posNum = [int]$pos.pos
    $playerId = [int]$pos.player.player_id
    if (($posNum -eq 7 -or $posNum -eq 8) -and -not $benchPlayer) {
        $benchPlayer = $playerId
    }
    if ($posNum -ge 1 -and $posNum -le 6 -and $playerId -ne $currentCaptain -and -not $starterPlayer) {
        $starterPlayer = $playerId
    }
}
if (-not $benchPlayer) {
    Write-Host "FAIL: could not find bench player in pos 7/8."
    exit 1
}
if (-not $starterPlayer) {
    foreach ($pos in $positions) {
        $posNum = [int]$pos.pos
        $playerId = [int]$pos.player.player_id
        if ($posNum -ge 1 -and $posNum -le 6) {
            $starterPlayer = $playerId
            break
        }
    }
}
if (-not $starterPlayer) {
    Write-Host "FAIL: could not find starter player in pos 1..6."
    exit 1
}
Write-Host "Initial captain: $currentCaptain"
Write-Host "Bench candidate: $benchPlayer"
Write-Host "Starter candidate: $starterPlayer"
Write-Host "ETag before: $etagBefore"
Write-Host ""

Write-Host "4) Negative case: bench captain -> expect 422 CAPTAIN_NOT_STARTER"
$neg = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/team/captain" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    captain_player_id = $benchPlayer
}
if ($neg.status -eq 422 -and $neg.body -match '"CAPTAIN_NOT_STARTER"') {
    Write-Host "PASS: bench player rejected with CAPTAIN_NOT_STARTER."
} else {
    Write-Host "FAIL: expected 422 CAPTAIN_NOT_STARTER."
}
Write-Host "Status: $($neg.status)"
Write-Host "Body: $($neg.body)"
Write-Host ""

Write-Host "5) Positive case: starter captain -> expect 200 ok:true + no-store"
$pos = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/team/captain" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    captain_player_id = $starterPlayer
}
$ccPos = Header-Value -Headers $pos.headers -Name "Cache-Control"
$okTrue = $false
try {
    $posObj = $pos.body | ConvertFrom-Json
    $okTrue = ($posObj.data.ok -eq $true)
} catch {}
if ($pos.status -eq 200 -and $okTrue -and $ccPos -eq "no-store") {
    Write-Host "PASS: captain update success + no-store."
} else {
    Write-Host "FAIL: captain update did not meet expected status/body/headers."
}
Write-Host "Status: $($pos.status)"
Write-Host "Cache-Control: $ccPos"
Write-Host "Body: $($pos.body)"
Write-Host ""

Write-Host "6) Re-fetch /team -> captain changed + ETag changed"
$teamAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
$etagAfter = Header-Value -Headers $teamAfter.headers -Name "ETag"
$captainAfter = $null
if ($teamAfter.status -eq 200) {
    try { $captainAfter = [int](($teamAfter.body | ConvertFrom-Json).data.roster.captain_player_id) } catch {}
}
$captainChanged = ($captainAfter -eq $starterPlayer)
$etagChanged = ($etagBefore -ne $null -and $etagAfter -ne $null -and $etagBefore -ne $etagAfter)
if ($teamAfter.status -eq 200 -and $captainChanged -and $etagChanged) {
    Write-Host "PASS: /team reflects new captain and ETag changed."
} else {
    Write-Host "FAIL: expected updated captain and changed ETag."
}
Write-Host "Status: $($teamAfter.status)"
Write-Host "Captain after: $captainAfter"
Write-Host "ETag after: $etagAfter"
Write-Host ""

Write-Host "7) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/team/captain" -Headers @("Content-Type: application/json") -JsonBody @{
    captain_player_id = $starterPlayer
}
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
Write-Host ""

Write-Host "8) Invalid league -> expect 404 LEAGUE_NOT_FOUND"
$invalid = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$InvalidLeagueId/team/captain" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    captain_player_id = $starterPlayer
}
if ($invalid.status -eq 404 -and $invalid.body -match '"LEAGUE_NOT_FOUND"') {
    Write-Host "PASS: invalid league returned 404 LEAGUE_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 LEAGUE_NOT_FOUND."
}
Write-Host "Status: $($invalid.status)"
Write-Host "Body: $($invalid.body)"
