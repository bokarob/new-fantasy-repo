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

    $headerFile = Join-Path $env:TEMP ("team-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("team-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }

        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("team-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Team smoke checks for TASK-003"
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
    $runEmail = ("team_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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
$expectNoCompetitor = $false
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
            $expectNoCompetitor = $true
        }
    } catch {}
}

if (-not $leagueId) {
    Write-Host "SKIP: no leagues found in /home response; cannot run 200/304/no-competitor check."
    Write-Host ""
} else {
    Write-Host "3) GET /leagues/$leagueId/team"
    $team = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
    $etag = Header-Value -Headers $team.headers -Name "ETag"
    $cc = Header-Value -Headers $team.headers -Name "Cache-Control"

    if ($team.status -eq 200) {
        $okPositions = $false
        try {
            $teamObj = $team.body | ConvertFrom-Json
            $okPositions = @($teamObj.data.roster.positions).Count -eq 8
        } catch {}

        if ($okPositions -and $etag -and $cc -eq "private, must-revalidate") {
            Write-Host "PASS: team payload 200 + headers + 8 positions."
        } else {
            Write-Host "FAIL: team payload missing expected fields/headers."
        }
        Write-Host "Status: $($team.status)"
        Write-Host "ETag: $etag"
        Write-Host "Cache-Control: $cc"
        Write-Host ""

        Write-Host "4) Revalidate with If-None-Match -> expect 304"
        $team304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @(
            "Authorization: Bearer $token",
            "If-None-Match: $etag"
        )
        if ($team304.status -eq 304) {
            Write-Host "PASS: returned 304."
        } else {
            Write-Host "FAIL: expected 304, got $($team304.status)."
        }
        Write-Host ""
    } elseif ($team.status -eq 409 -and $expectNoCompetitor) {
        Write-Host "PASS: returned 409 for league without competitor (expected path)."
        Write-Host "Body: $($team.body)"
        Write-Host ""
    } else {
        Write-Host "FAIL: unexpected status for /team: $($team.status)"
        Write-Host "Body: $($team.body)"
        Write-Host ""
    }
}

Write-Host "5) Invalid league -> expect 404 LEAGUE_NOT_FOUND"
$invalid = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$InvalidLeagueId/team" -Headers @("Authorization: Bearer $token")
if ($invalid.status -eq 404 -and $invalid.body -match '"LEAGUE_NOT_FOUND"') {
    Write-Host "PASS: invalid league returned 404 LEAGUE_NOT_FOUND."
} else {
    Write-Host "FAIL: invalid league expected 404 LEAGUE_NOT_FOUND."
}
Write-Host "Status: $($invalid.status)"
Write-Host "Body: $($invalid.body)"
Write-Host ""

Write-Host "6) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$InvalidLeagueId/team"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
