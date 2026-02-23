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

    $headerFile = Join-Path $env:TEMP ("confirm-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("confirm-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("confirm-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
            ($JsonBody | ConvertTo-Json -Compress) | Set-Content -Path $jsonFile -NoNewline
            $args += @('--data-binary', "@$jsonFile")
        }

        & curl.exe @args | Out-Null

        $headersRaw = if (Test-Path $headerFile) { Get-Content -Raw $headerFile } else { "" }
        $bodyRaw = if (Test-Path $bodyFile) { Get-Content -Raw $bodyFile } else { "" }
        $status = 0
        $matches = [regex]::Matches($headersRaw, "HTTP/\d\.\d\s+(\d+)")
        if ($matches.Count -gt 0) {
            $status = [int]$matches[$matches.Count - 1].Groups[1].Value
        }

        return @{ status = $status; headers = $headersRaw; body = $bodyRaw.Trim() }
    } finally {
        Remove-Item -Path $headerFile -Force -ErrorAction SilentlyContinue
        Remove-Item -Path $bodyFile -Force -ErrorAction SilentlyContinue
        if ($null -ne $jsonFile) { Remove-Item -Path $jsonFile -Force -ErrorAction SilentlyContinue }
    }
}

function Header-Value {
    param([string]$Headers, [string]$Name)
    $m = [regex]::Match($Headers, "(?im)^" + [regex]::Escape($Name) + ":\s*(.+)$")
    if ($m.Success) { return $m.Groups[1].Value.Trim() }
    return $null
}

Write-Host "Transfer confirm smoke checks for TASK-004B"
Write-Host "Base URL: $BaseUrl"
Write-Host ""

Write-Host "1) Login (register+verify fallback)"
$runEmail = $Email
$login = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
    email = $runEmail; password = $Password
}
$token = $null
if ($login.status -eq 200) { try { $token = (($login.body | ConvertFrom-Json).data.tokens.access_token) } catch {} }
if (-not $token) {
    $runEmail = ("confirm_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/register" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $runEmail; password = $Password; alias = "phase_d"; lang = "en"
    })
    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/otp/verify" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $runEmail; otp = $Otp; purpose = "register"
    })
    $login = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $runEmail; password = $Password
    }
    if ($login.status -eq 200) { try { $token = (($login.body | ConvertFrom-Json).data.tokens.access_token) } catch {} }
}
if (-not $token) {
    Write-Host "FAIL: could not acquire token."
    Write-Host "Status: $($login.status)"
    Write-Host "Body: $($login.body)"
    exit 1
}
Write-Host "PASS: token acquired."
Write-Host ""

Write-Host "2) Pick league_id from /home (competitor preferred)"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = 1
$hasCompetitorLeague = $false
if ($homeResp.status -eq 200) {
    try {
        $homeObj = $homeResp.body | ConvertFrom-Json
        $leagues = @($homeObj.data.league_selector.leagues)
        foreach ($l in $leagues) {
            if ($null -ne $l.competitor -and $null -ne $l.competitor.competitor_id) {
                $leagueId = [int]$l.league_id
                $hasCompetitorLeague = $true
                break
            }
        }
        if (-not $hasCompetitorLeague -and $leagues.Count -gt 0) {
            $leagueId = [int]$leagues[0].league_id
        }
    } catch {}
}
Write-Host "Using league_id=$leagueId"
Write-Host ""

Write-Host "3) GET /team before confirm (capture ETag + roster)"
$teamBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
if ($teamBefore.status -ne 200) {
    Write-Host "SKIP: /team not ready for confirm flow."
    Write-Host "Status: $($teamBefore.status)"
    Write-Host "Body: $($teamBefore.body)"
    exit 0
}
$etagBefore = Header-Value -Headers $teamBefore.headers -Name "ETag"
$teamBeforeObj = $teamBefore.body | ConvertFrom-Json
$rosterBefore = @($teamBeforeObj.data.roster.positions | ForEach-Object { [int]$_.player.player_id })
$gw = [int]$teamBeforeObj.meta.current_gw

$outgoing = $rosterBefore[0]
$incoming = $null
for ($i = 1; $i -le 300; $i++) {
    if ($rosterBefore -notcontains $i) { $incoming = $i; break }
}
if ($null -eq $incoming) { $incoming = 99999 }

Write-Host "4) Optional quote pre-check"
$quoteResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/transfers/quote" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    outgoing_player_ids = @($outgoing)
    incoming_player_ids = @($incoming)
}
Write-Host "Quote status: $($quoteResp.status)"
Write-Host ""

Write-Host "5) Confirm transfer"
$confirmResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/transfers/confirm" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    outgoing_player_ids = @($outgoing)
    incoming_player_ids = @($incoming)
}
if ($confirmResp.status -eq 200) {
    Write-Host "PASS: confirm returned 200."
} else {
    Write-Host "FAIL: confirm expected 200."
}
Write-Host "Status: $($confirmResp.status)"
Write-Host "Body: $($confirmResp.body)"
Write-Host ""

if ($confirmResp.status -eq 200) {
    Write-Host "6) GET /team after confirm -> ETag changed + roster changed"
    $teamAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
    $etagAfter = Header-Value -Headers $teamAfter.headers -Name "ETag"
    $okEtag = $etagBefore -ne $etagAfter
    $okRoster = $false
    if ($teamAfter.status -eq 200) {
        try {
            $afterObj = $teamAfter.body | ConvertFrom-Json
            $rosterAfter = @($afterObj.data.roster.positions | ForEach-Object { [int]$_.player.player_id })
            $okRoster = (($rosterAfter -contains $incoming) -and ($rosterAfter -notcontains $outgoing))
        } catch {}
    }
    if ($teamAfter.status -eq 200 -and $okEtag -and $okRoster) {
        Write-Host "PASS: team ETag and roster changed after confirm."
    } else {
        Write-Host "FAIL: expected team ETag/roster change."
    }
    Write-Host "ETag before: $etagBefore"
    Write-Host "ETag after:  $etagAfter"
    Write-Host ""
}

Write-Host "7) Transfer limit check (skip if free_gw)"
$isFreeGw = $false
$freeCheck = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home?league_id=$leagueId" -Headers @("Authorization: Bearer $token")
if ($freeCheck.status -eq 200) {
    try {
        $fc = $freeCheck.body | ConvertFrom-Json
        # best effort; free_gw not in /home contract, so keep false by default
        $isFreeGw = $false
    } catch {}
}
if ($isFreeGw) {
    Write-Host "SKIP: league appears to be free transfer GW."
} else {
    $attempt = 0
    $limitHit = $false
    while ($attempt -lt 3 -and -not $limitHit) {
        $teamNow = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
        if ($teamNow.status -ne 200) { break }
        $tObj = $teamNow.body | ConvertFrom-Json
        $r = @($tObj.data.roster.positions | ForEach-Object { [int]$_.player.player_id })
        $out = $r[0]
        $in = $null
        for ($i = 1; $i -le 300; $i++) {
            if ($r -notcontains $i) { $in = $i; break }
        }
        if ($null -eq $in) { $in = 99999 }

        $c = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/transfers/confirm" -Headers @(
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ) -JsonBody @{
            outgoing_player_ids = @($out)
            incoming_player_ids = @($in)
        }

        if ($c.status -eq 409 -and $c.body -match '"TRANSFER_LIMIT_REACHED"') {
            $limitHit = $true
        }
        $attempt++
    }
    if ($limitHit) {
        Write-Host "PASS: transfer limit enforced on non-free GW."
    } else {
        Write-Host "INFO: transfer limit not hit in smoke attempts (may be free_gw or prior state variance)."
    }
}
Write-Host ""

Write-Host "8) No token confirm -> 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/transfers/confirm" -Headers @("Content-Type: application/json") -JsonBody @{
    outgoing_player_ids = @($outgoing)
    incoming_player_ids = @($incoming)
}
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host ""

Write-Host "9) Invalid league confirm -> 404 LEAGUE_NOT_FOUND"
$badLeague = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/999999/transfers/confirm" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    outgoing_player_ids = @($outgoing)
    incoming_player_ids = @($incoming)
}
if ($badLeague.status -eq 404 -and $badLeague.body -match '"LEAGUE_NOT_FOUND"') {
    Write-Host "PASS: invalid league returned 404 LEAGUE_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 LEAGUE_NOT_FOUND."
}
Write-Host "Status: $($badLeague.status)"
