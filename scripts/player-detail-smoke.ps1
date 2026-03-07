param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "seed.user2@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456",
    [int]$InvalidLeagueId = 999999,
    [int]$InvalidPlayerId = 999999999
)

function Invoke-CurlRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string[]]$Headers = @(),
        [object]$JsonBody = $null
    )

    $headerFile = Join-Path $env:TEMP ("player-detail-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("player-detail-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("player-detail-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Player detail smoke checks"
Write-Host "Base URL: $BaseUrl"
Write-Host ""

Write-Host "1) Login"
$login = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
    email = $Email
    password = $Password
}
$token = $null
if ($login.status -eq 200) {
    try { $token = (($login.body | ConvertFrom-Json).data.tokens.access_token) } catch {}
}
if (-not $token) {
    $runEmail = ("player_detail_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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
    exit 1
}
Write-Host "PASS: access token acquired."
Write-Host ""

Write-Host "2) Discover owned player from /team"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = $null
if ($homeResp.status -eq 200) {
    try {
        $homeObj = $homeResp.body | ConvertFrom-Json
        foreach ($league in @($homeObj.data.league_selector.leagues)) {
            if ($null -ne $league.competitor -and $null -ne $league.competitor.competitor_id) {
                $leagueId = [int]$league.league_id
                break
            }
        }
    } catch {}
}
if (-not $leagueId) {
    Write-Host "FAIL: could not discover a league with competitor access."
    exit 1
}

$teamResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
$playerId = $null
if ($teamResp.status -eq 200) {
    try {
        $teamObj = $teamResp.body | ConvertFrom-Json
        $positions = @($teamObj.data.roster.positions)
        if ($positions.Count -gt 0) {
            $playerId = [int]$positions[0].player.player_id
        }
    } catch {}
}
if (-not $playerId) {
    Write-Host "FAIL: could not discover a player_id from /team."
    exit 1
}
Write-Host "PASS: using league_id=$leagueId player_id=$playerId"
Write-Host ""

Write-Host "3) GET player detail -> expect 200 + Category A headers + ownership payload"
$detail1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/players/$playerId" -Headers @("Authorization: Bearer $token")
$cc1 = Header-Value -Headers $detail1.headers -Name "Cache-Control"
$etag1 = Header-Value -Headers $detail1.headers -Name "ETag"
$okShape = $false
$okMetaEtag = $false
if ($detail1.status -eq 200) {
    try {
        $obj = $detail1.body | ConvertFrom-Json
        $okShape = ($obj.data.player.player_id -eq $playerId) -and ($obj.data.ownership.owned_by_you -eq $true) -and ($null -ne $obj.data.actions.can_replace)
        $okMetaEtag = [string]$obj.meta.etag -eq [string]$etag1
    } catch {}
}
if ($detail1.status -eq 200 -and $cc1 -eq "private, must-revalidate" -and $etag1 -and $okShape -and $okMetaEtag) {
    Write-Host "PASS: player detail returned expected envelope and Category A headers."
} else {
    Write-Host "FAIL: player detail response/header checks failed."
}
Write-Host "Status: $($detail1.status)"
Write-Host "Cache-Control: $cc1"
Write-Host "ETag: $etag1"
Write-Host "Body: $($detail1.body)"
Write-Host ""

Write-Host "4) Revalidate with If-None-Match -> expect 304"
$detail304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/players/$playerId" -Headers @(
    "Authorization: Bearer $token",
    "If-None-Match: $etag1"
)
if ($detail304.status -eq 304) {
    Write-Host "PASS: conditional request returned 304 Not Modified."
} else {
    Write-Host "FAIL: expected 304 Not Modified."
}
Write-Host "Status: $($detail304.status)"
Write-Host "Body length: $($detail304.body.Length)"
Write-Host ""

Write-Host "5) Invalid player -> expect 404 PLAYER_NOT_FOUND"
$invalidPlayer = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/players/$InvalidPlayerId" -Headers @("Authorization: Bearer $token")
if ($invalidPlayer.status -eq 404 -and $invalidPlayer.body -match '"PLAYER_NOT_FOUND"') {
    Write-Host "PASS: invalid player returned 404 PLAYER_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 PLAYER_NOT_FOUND."
}
Write-Host "Status: $($invalidPlayer.status)"
Write-Host "Body: $($invalidPlayer.body)"
Write-Host ""

Write-Host "6) Missing token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/players/$playerId"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
Write-Host ""

Write-Host "7) Invalid league -> expect 404 LEAGUE_NOT_FOUND"
$invalidLeague = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$InvalidLeagueId/players/$playerId" -Headers @("Authorization: Bearer $token")
if ($invalidLeague.status -eq 404 -and $invalidLeague.body -match '"LEAGUE_NOT_FOUND"') {
    Write-Host "PASS: invalid league returned 404 LEAGUE_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 LEAGUE_NOT_FOUND."
}
Write-Host "Status: $($invalidLeague.status)"
Write-Host "Body: $($invalidLeague.body)"
