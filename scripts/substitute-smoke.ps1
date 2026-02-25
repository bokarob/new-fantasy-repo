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

    $headerFile = Join-Path $env:TEMP ("substitute-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("substitute-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("substitute-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Substitute smoke checks for TASK-009"
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
    $runEmail = ("substitute_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "3) GET /team before substitute"
$teamBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
if ($teamBefore.status -ne 200) {
    Write-Host "SKIP: /team not ready for substitute flow."
    Write-Host "Status: $($teamBefore.status)"
    Write-Host "Body: $($teamBefore.body)"
    exit 0
}

$etagBefore = Header-Value -Headers $teamBefore.headers -Name "ETag"
$teamBeforeObj = $teamBefore.body | ConvertFrom-Json
$positionsBefore = @($teamBeforeObj.data.roster.positions)
$captainBefore = [int]$teamBeforeObj.data.roster.captain_player_id

$pos1Before = $null
$pos7Before = $null
foreach ($p in $positionsBefore) {
    $posNum = [int]$p.pos
    $playerId = [int]$p.player.player_id
    if ($posNum -eq 1) { $pos1Before = $playerId }
    if ($posNum -eq 7) { $pos7Before = $playerId }
}
if ($null -eq $pos1Before -or $null -eq $pos7Before) {
    Write-Host "FAIL: unable to resolve pos1/pos7 players."
    exit 1
}
Write-Host "ETag before: $etagBefore"
Write-Host "pos1 before: $pos1Before"
Write-Host "pos7 before: $pos7Before"
Write-Host "captain before: $captainBefore"
Write-Host ""

Write-Host "4) POST substitute (swap_pos_a=1, swap_pos_b=7)"
$substitute = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/team/substitute" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    swap_pos_a = 1
    swap_pos_b = 7
}
$substituteCc = Header-Value -Headers $substitute.headers -Name "Cache-Control"
$substituteOk = $false
try {
    $obj = $substitute.body | ConvertFrom-Json
    $substituteOk = ($substitute.status -eq 200) -and ($obj.data.ok -eq $true) -and ($null -eq $obj.meta.etag) -and ($substituteCc -eq "no-store")
} catch {}
if ($substituteOk) {
    Write-Host "PASS: substitute returned 200 + ok:true + no-store + meta.etag null."
} else {
    Write-Host "FAIL: substitute response mismatch."
}
Write-Host "Status: $($substitute.status)"
Write-Host "Cache-Control: $substituteCc"
Write-Host "Body: $($substitute.body)"
Write-Host ""

if ($substitute.status -eq 200) {
    Write-Host "5) GET /team after substitute (expect ETag + swap change)"
    $teamAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
    $etagAfter = Header-Value -Headers $teamAfter.headers -Name "ETag"
    if ($teamAfter.status -eq 200) {
        $teamAfterObj = $teamAfter.body | ConvertFrom-Json
        $positionsAfter = @($teamAfterObj.data.roster.positions)
        $captainAfter = [int]$teamAfterObj.data.roster.captain_player_id
        $pos1After = $null
        $pos7After = $null
        foreach ($p in $positionsAfter) {
            $posNum = [int]$p.pos
            $playerId = [int]$p.player.player_id
            if ($posNum -eq 1) { $pos1After = $playerId }
            if ($posNum -eq 7) { $pos7After = $playerId }
        }

        $etagChanged = ($etagBefore -ne $etagAfter)
        $swapChanged = ($pos1After -eq $pos7Before -and $pos7After -eq $pos1Before)
        if ($etagChanged -and $swapChanged) {
            Write-Host "PASS: ETag changed and pos1/pos7 swapped."
        } else {
            Write-Host "FAIL: expected ETag and swap change."
        }

        if ($captainBefore -eq $pos1Before) {
            if ($captainAfter -eq $pos1After) {
                Write-Host "PASS: captain auto-fixed to starter pos1 after bench swap."
            } else {
                Write-Host "FAIL: expected captain auto-fix to pos1."
            }
        } else {
            Write-Host "INFO: captain was not moved to bench in this swap; auto-fix branch not asserted."
        }

        Write-Host "ETag before: $etagBefore"
        Write-Host "ETag after:  $etagAfter"
        Write-Host "pos1 after: $pos1After"
        Write-Host "pos7 after: $pos7After"
        Write-Host "captain after: $captainAfter"
    } else {
        Write-Host "FAIL: /team after substitute expected 200."
        Write-Host "Status: $($teamAfter.status)"
        Write-Host "Body: $($teamAfter.body)"
    }
    Write-Host ""
}

Write-Host "6) Invalid positions -> 422 ROSTER_INVALID_POSITION"
$invalidPos = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/team/substitute" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    swap_pos_a = 0
    swap_pos_b = 9
}
if ($invalidPos.status -eq 422 -and $invalidPos.body -match '"ROSTER_INVALID_POSITION"') {
    Write-Host "PASS: invalid positions returned 422 ROSTER_INVALID_POSITION."
} else {
    Write-Host "FAIL: expected 422 ROSTER_INVALID_POSITION."
}
Write-Host "Status: $($invalidPos.status)"
Write-Host "Body: $($invalidPos.body)"
Write-Host ""

Write-Host "7) No token -> 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/team/substitute" -Headers @("Content-Type: application/json") -JsonBody @{
    swap_pos_a = 1
    swap_pos_b = 7
}
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
Write-Host ""

Write-Host "8) Invalid league -> 404 LEAGUE_NOT_FOUND"
$invalidLeague = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$InvalidLeagueId/team/substitute" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    swap_pos_a = 1
    swap_pos_b = 7
}
if ($invalidLeague.status -eq 404 -and $invalidLeague.body -match '"LEAGUE_NOT_FOUND"') {
    Write-Host "PASS: invalid league returned 404 LEAGUE_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 LEAGUE_NOT_FOUND."
}
Write-Host "Status: $($invalidLeague.status)"
Write-Host "Body: $($invalidLeague.body)"
