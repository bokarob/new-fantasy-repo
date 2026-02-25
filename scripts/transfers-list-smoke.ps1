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

    $headerFile = Join-Path $env:TEMP ("transfers-list-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("transfers-list-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("transfers-list-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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
    param([string]$Headers, [string]$Name)
    $m = [regex]::Match($Headers, "(?im)^" + [regex]::Escape($Name) + ":\s*(.+)$")
    if ($m.Success) { return $m.Groups[1].Value.Trim() }
    return $null
}

Write-Host "Transfers list smoke checks for TASK-007"
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
    $runEmail = ("transfers_list_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Determine league_id from /home (first with competitor)"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = 1
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
        if ($leagueId -eq 1 -and $leagues.Count -gt 0) {
            $leagueId = [int]$leagues[0].league_id
        }
    } catch {}
}
Write-Host "Using league_id=$leagueId"
Write-Host ""

Write-Host "3) GET /leagues/{league_id}/transfers -> expect 200 + Category A headers"
$list1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/transfers" -Headers @("Authorization: Bearer $token")
$cc1 = Header-Value -Headers $list1.headers -Name "Cache-Control"
$etag1 = Header-Value -Headers $list1.headers -Name "ETag"
$total1 = -1
$ok1 = $false
if ($list1.status -eq 200) {
    try {
        $obj1 = $list1.body | ConvertFrom-Json
        $total1 = [int]$obj1.data.total
        $ok1 = ($cc1 -eq "private, must-revalidate") -and ($etag1 -ne $null) -and ($etag1 -ne "")
    } catch {}
}
if ($ok1) {
    Write-Host "PASS: transfers list returned 200 with Cache-Control + ETag."
} else {
    Write-Host "FAIL: expected 200 with Category A headers."
}
Write-Host "Status: $($list1.status)"
Write-Host "Cache-Control: $cc1"
Write-Host "ETag: $etag1"
Write-Host "Total before: $total1"
Write-Host ""

Write-Host "4) Repeat with If-None-Match -> expect 304"
$list304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/transfers" -Headers @(
    "Authorization: Bearer $token",
    "If-None-Match: $etag1"
)
if ($list304.status -eq 304) {
    Write-Host "PASS: conditional request returned 304 Not Modified."
} else {
    Write-Host "FAIL: expected 304 Not Modified."
}
Write-Host "Status: $($list304.status)"
Write-Host "Body length: $($list304.body.Length)"
Write-Host ""

$confirmOk = $false
Write-Host "5) Optional: create one transfer via /transfers/confirm"
$teamResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
if ($teamResp.status -eq 200) {
    $outgoing = $null
    $incoming = $null
    try {
        $teamObj = $teamResp.body | ConvertFrom-Json
        $rosterIds = @($teamObj.data.roster.positions | ForEach-Object { [int]$_.player.player_id })
        if ($rosterIds.Count -gt 0) {
            $outgoing = $rosterIds[0]
            for ($i = 1; $i -le 300; $i++) {
                if ($rosterIds -notcontains $i) { $incoming = $i; break }
            }
            if ($null -eq $incoming) { $incoming = 99999 }
        }
    } catch {}

    if ($null -ne $outgoing -and $null -ne $incoming) {
        $confirmResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/transfers/confirm" -Headers @(
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ) -JsonBody @{
            outgoing_player_ids = @($outgoing)
            incoming_player_ids = @($incoming)
        }
        if ($confirmResp.status -eq 200) {
            $confirmOk = $true
            Write-Host "PASS: transfer confirm succeeded (status 200)."
        } else {
            Write-Host "INFO: transfer confirm did not succeed in this environment."
            Write-Host "Status: $($confirmResp.status)"
            Write-Host "Body: $($confirmResp.body)"
        }
    } else {
        Write-Host "INFO: could not derive transfer candidate from /team."
    }
} else {
    Write-Host "INFO: /team unavailable, skipping confirm attempt."
    Write-Host "Status: $($teamResp.status)"
}
Write-Host ""

Write-Host "6) GET /transfers after confirm attempt"
$list2 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/transfers" -Headers @("Authorization: Bearer $token")
$etag2 = Header-Value -Headers $list2.headers -Name "ETag"
$total2 = -1
if ($list2.status -eq 200) {
    try { $total2 = [int](($list2.body | ConvertFrom-Json).data.total) } catch {}
}
if ($confirmOk) {
    $etagChanged = ($etag1 -ne $null -and $etag2 -ne $null -and $etag1 -ne $etag2)
    $totalNotLower = ($total2 -ge $total1)
    if ($list2.status -eq 200 -and $etagChanged -and $totalNotLower) {
        Write-Host "PASS: transfers list refreshed after confirm (ETag changed, total not lower)."
    } else {
        Write-Host "FAIL: expected ETag change and non-decreasing total after confirm."
    }
} else {
    Write-Host "INFO: confirm was skipped/failed; post-confirm assertions skipped."
}
Write-Host "Status: $($list2.status)"
Write-Host "ETag after: $etag2"
Write-Host "Total after: $total2"
Write-Host ""

Write-Host "7) Invalid league -> expect 404 LEAGUE_NOT_FOUND"
$invalid = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$InvalidLeagueId/transfers" -Headers @("Authorization: Bearer $token")
if ($invalid.status -eq 404 -and $invalid.body -match '"LEAGUE_NOT_FOUND"') {
    Write-Host "PASS: invalid league returned 404 LEAGUE_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 LEAGUE_NOT_FOUND."
}
Write-Host "Status: $($invalid.status)"
Write-Host "Body: $($invalid.body)"
Write-Host ""

Write-Host "8) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/transfers"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
