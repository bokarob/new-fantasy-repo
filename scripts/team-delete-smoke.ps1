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

    $headerFile = Join-Path $env:TEMP ("team-delete-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("team-delete-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('--connect-timeout', '10', '--max-time', '30', '-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("team-delete-j-" + [guid]::NewGuid().ToString() + ".json")
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

function New-TestUserToken {
    $email = ("team_delete_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")

    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/register" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $email
        password = $Password
        alias = "deleteflow"
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
        return @{ ok = $false; email = $email; token = $null; raw = $login }
    }

    $token = $null
    try { $token = (($login.body | ConvertFrom-Json).data.tokens.access_token) } catch {}
    return @{ ok = (-not [string]::IsNullOrWhiteSpace($token)); email = $email; token = $token; raw = $login }
}

Write-Host "Team delete smoke checks for post-M4 follow-up"
Write-Host "Base URL: $BaseUrl"
Write-Host "League: $LeagueId"
Write-Host ""

Write-Host "1) Register/login fresh user"
$user = New-TestUserToken
if (-not $user.ok) {
    Write-Host "FAIL: could not acquire token."
    Write-Host "Email: $($user.email)"
    Write-Host "Status: $($user.raw.status)"
    Write-Host "Body: $($user.raw.body)"
    exit 1
}
Write-Host "PASS: token acquired for $($user.email)"
Write-Host ""

Write-Host "2) Build and create a team"
$builder = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team/builder" -Headers @("Authorization: Bearer $($user.token)")
if ($builder.status -ne 200) {
    Write-Host "FAIL: team builder not available."
    Write-Host "Status: $($builder.status)"
    Write-Host "Body: $($builder.body)"
    exit 1
}

$builderObj = $builder.body | ConvertFrom-Json
$players = @($builderObj.data.players | Select-Object -First 8)
if ($players.Count -lt 8) {
    Write-Host "FAIL: fewer than 8 players available for create/delete flow."
    exit 1
}

$playerIds = @($players | ForEach-Object { [int]$_.player_id })
$captainId = [int]$playerIds[0]
$favoriteTeamId = [int]$players[0].team.team_id
$teamname = "DeleteFlow_" + [int][double]::Parse((Get-Date -UFormat %s))

$create = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @(
    "Authorization: Bearer $($user.token)",
    "Content-Type: application/json"
) -JsonBody @{
    teamname = $teamname
    player_ids = $playerIds
    captain_player_id = $captainId
    favorite_team_id = $favoriteTeamId
}
if ($create.status -ne 200) {
    Write-Host "FAIL: team creation failed."
    Write-Host "Status: $($create.status)"
    Write-Host "Body: $($create.body)"
    exit 1
}
Write-Host "PASS: team created."
Write-Host ""

Write-Host "3) Capture baseline reads"
$teamBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @("Authorization: Bearer $($user.token)")
$teamBeforeEtag = Header-Value -Headers $teamBefore.headers -Name "ETag"
$teamsBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/me/teams" -Headers @("Authorization: Bearer $($user.token)")
if ($teamBefore.status -ne 200 -or $teamsBefore.status -ne 200) {
    Write-Host "FAIL: baseline reads failed."
    Write-Host "GET /team status: $($teamBefore.status)"
    Write-Host "GET /me/teams status: $($teamsBefore.status)"
    exit 1
}
Write-Host "PASS: baseline team + me/teams available."
Write-Host "Team ETag before delete: $teamBeforeEtag"
Write-Host ""

Write-Host "4) DELETE /leagues/$LeagueId/team -> expect 200 ok:true + no-store"
$deleteResp = Invoke-CurlRequest -Method DELETE -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @("Authorization: Bearer $($user.token)")
$deleteCc = Header-Value -Headers $deleteResp.headers -Name "Cache-Control"
$deleteOk = $false
try {
    $deleteObj = $deleteResp.body | ConvertFrom-Json
    $deleteOk = ($deleteResp.status -eq 200 -and $deleteCc -eq "no-store" -and $deleteObj.data.ok -eq $true -and $null -eq $deleteObj.meta.etag)
} catch {}
if ($deleteOk) {
    Write-Host "PASS: delete endpoint returned expected success response."
} else {
    Write-Host "FAIL: delete endpoint response/header mismatch."
}
Write-Host "Status: $($deleteResp.status)"
Write-Host "Cache-Control: $deleteCc"
Write-Host "Body: $($deleteResp.body)"
Write-Host ""
if (-not $deleteOk) { exit 1 }

Write-Host "5) GET /me/teams after delete -> removed from list"
$teamsAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/me/teams" -Headers @("Authorization: Bearer $($user.token)")
$leagueStillPresent = $false
if ($teamsAfter.status -eq 200) {
    try {
        $teamsAfterObj = $teamsAfter.body | ConvertFrom-Json
        foreach ($item in @($teamsAfterObj.data.teams)) {
            if ([int]$item.league.league_id -eq $LeagueId) {
                $leagueStillPresent = $true
                break
            }
        }
    } catch {}
}
if ($teamsAfter.status -eq 200 -and -not $leagueStillPresent) {
    Write-Host "PASS: /me/teams no longer lists the deleted team."
} else {
    Write-Host "FAIL: /me/teams still shows deleted team."
}
Write-Host "Status: $($teamsAfter.status)"
Write-Host "Body: $($teamsAfter.body)"
Write-Host ""

Write-Host "6) GET /leagues/$LeagueId/team after delete -> expect 409 NO_COMPETITOR"
$teamAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @("Authorization: Bearer $($user.token)")
if ($teamAfter.status -eq 409 -and $teamAfter.body -match '"NO_COMPETITOR"') {
    Write-Host "PASS: /team reflects deletion."
} else {
    Write-Host "FAIL: expected 409 NO_COMPETITOR after delete."
}
Write-Host "Status: $($teamAfter.status)"
Write-Host "Body: $($teamAfter.body)"
Write-Host ""

Write-Host "7) GET /home?league_id=$LeagueId and /fantasy -> dependent reads reflect no competitor"
$homeAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home?league_id=$LeagueId" -Headers @("Authorization: Bearer $($user.token)")
$fantasyAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/fantasy" -Headers @("Authorization: Bearer $($user.token)")
$homeOk = $false
$fantasyOk = $false
try {
    $homeObj = $homeAfter.body | ConvertFrom-Json
    $homeOk = ($homeAfter.status -eq 200 -and $null -eq $homeObj.data.league_context.your_team)
} catch {}
try {
    $fantasyObj = $fantasyAfter.body | ConvertFrom-Json
    $fantasyOk = ($fantasyAfter.status -eq 200 -and $null -eq $fantasyObj.data.overall.you)
} catch {}
if (-not $fantasyOk -and $fantasyAfter.status -eq 409 -and $fantasyAfter.body -match '"RANKING_NOT_AVAILABLE"') {
    $fantasyOk = $true
}
if ($homeOk -and $fantasyOk) {
    Write-Host "PASS: dependent reads no longer treat the user as a competitor."
} else {
    Write-Host "FAIL: dependent reads did not fully reflect deletion."
}
Write-Host "Home status: $($homeAfter.status)"
Write-Host "Fantasy status: $($fantasyAfter.status)"
Write-Host ""

Write-Host "8) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method DELETE -Url "$BaseUrl/leagues/$LeagueId/team"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
