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

    $headerFile = Join-Path $env:TEMP ("market-players-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("market-players-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("market-players-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Market players smoke checks for TASK-008"
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
    $runEmail = ("market_players_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Pick league_id from /home (first with competitor)"
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

Write-Host "3) GET /market/players?limit=10 -> expect 200 + Category A headers + items > 0"
$market1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/market/players?limit=10" -Headers @("Authorization: Bearer $token")
$cc1 = Header-Value -Headers $market1.headers -Name "Cache-Control"
$etag1 = Header-Value -Headers $market1.headers -Name "ETag"
$itemsCount = 0
$ok3 = $false
if ($market1.status -eq 200) {
    try {
        $obj1 = $market1.body | ConvertFrom-Json
        $itemsCount = @($obj1.data.items).Count
        $ok3 = ($cc1 -eq "private, must-revalidate") -and ($etag1 -ne $null) -and ($etag1 -ne "") -and ($itemsCount -gt 0)
    } catch {}
}
if ($ok3) {
    Write-Host "PASS: market list returned 200 + headers + non-empty items."
} else {
    Write-Host "FAIL: expected 200 + Category A headers + items."
}
Write-Host "Status: $($market1.status)"
Write-Host "Cache-Control: $cc1"
Write-Host "ETag: $etag1"
Write-Host "items count: $itemsCount"
Write-Host ""

Write-Host "4) Revalidate with If-None-Match -> expect 304"
$market304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/market/players?limit=10" -Headers @(
    "Authorization: Bearer $token",
    "If-None-Match: $etag1"
)
if ($market304.status -eq 304) {
    Write-Host "PASS: conditional request returned 304 Not Modified."
} else {
    Write-Host "FAIL: expected 304 Not Modified."
}
Write-Host "Status: $($market304.status)"
Write-Host "Body length: $($market304.body.Length)"
Write-Host ""

Write-Host "5) Contextual query with outgoing_player_ids[] and ALREADY_OWNED check"
$teamResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
$ok5 = $false
if ($teamResp.status -eq 200) {
    try {
        $teamObj = $teamResp.body | ConvertFrom-Json
        $positions = @($teamObj.data.roster.positions)
        if ($positions.Count -ge 2) {
            $outgoingId = [int]$positions[0].player.player_id
            $ownedName = [string]$positions[1].player.name
            $encodedName = [System.Uri]::EscapeDataString($ownedName)
            $ctxUrl = "$BaseUrl/leagues/$leagueId/market/players?limit=50&q=$encodedName&outgoing_player_ids[]=$outgoingId"
            $ctxResp = Invoke-CurlRequest -Method GET -Url $ctxUrl -Headers @("Authorization: Bearer $token")
            if ($ctxResp.status -eq 200) {
                $ctxObj = $ctxResp.body | ConvertFrom-Json
                $hasContext = ($null -ne $ctxObj.data.context -and $null -ne $ctxObj.data.context.available_credits)
                $hasOwnedReason = $false
                foreach ($it in @($ctxObj.data.items)) {
                    $reasons = @($it.availability.disabled_reasons)
                    if ($reasons -contains "ALREADY_OWNED") {
                        $hasOwnedReason = $true
                        break
                    }
                }
                $ok5 = $hasContext -and $hasOwnedReason
            }
        }
    } catch {}
}
if ($ok5) {
    Write-Host "PASS: contextual response includes available_credits and ALREADY_OWNED reason."
} else {
    Write-Host "FAIL: contextual expectations not met."
}
Write-Host ""

Write-Host "6) Invalid sort -> expect 400 BAD_REQUEST"
$badSort = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/market/players?sort=zzz_invalid" -Headers @("Authorization: Bearer $token")
if ($badSort.status -eq 400 -and $badSort.body -match '"BAD_REQUEST"') {
    Write-Host "PASS: invalid sort returned 400 BAD_REQUEST."
} else {
    Write-Host "FAIL: expected 400 BAD_REQUEST for invalid sort."
}
Write-Host "Status: $($badSort.status)"
Write-Host "Body: $($badSort.body)"
Write-Host ""

Write-Host "7) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/market/players"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
Write-Host ""

Write-Host "8) Invalid league -> expect 404 LEAGUE_NOT_FOUND"
$invalid = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$InvalidLeagueId/market/players" -Headers @("Authorization: Bearer $token")
if ($invalid.status -eq 404 -and $invalid.body -match '"LEAGUE_NOT_FOUND"') {
    Write-Host "PASS: invalid league returned 404 LEAGUE_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 LEAGUE_NOT_FOUND."
}
Write-Host "Status: $($invalid.status)"
Write-Host "Body: $($invalid.body)"
