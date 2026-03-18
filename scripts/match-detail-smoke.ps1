param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "phase_d_auth_test@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456",
    [int]$InvalidMatchId = 999999999
)

function Invoke-CurlRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string[]]$Headers = @(),
        [object]$JsonBody = $null
    )

    $headerFile = Join-Path $env:TEMP ("match-detail-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("match-detail-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("match-detail-j-" + [guid]::NewGuid().ToString() + ".json")
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
    param(
        [string]$Headers,
        [string]$Name
    )
    $m = [regex]::Match($Headers, "(?im)^" + [regex]::Escape($Name) + ":\s*(.+)$")
    if ($m.Success) {
        return $m.Groups[1].Value.Trim()
    }
    return $null
}

Write-Host "Match detail smoke checks for TASK-020"
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
    $runEmail = ("match_detail_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Find a league with at least one match in the selected/current GW"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = $null
$matchId = $null
if ($homeResp.status -eq 200) {
    try {
        $homeObj = $homeResp.body | ConvertFrom-Json
        $leagues = @($homeObj.data.league_selector.leagues)
        foreach ($league in $leagues) {
            $candidateLeagueId = [int]$league.league_id
            $listResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$candidateLeagueId/matches" -Headers @("Authorization: Bearer $token")
            if ($listResp.status -ne 200) {
                continue
            }
            $listObj = $listResp.body | ConvertFrom-Json
            $items = @($listObj.data.items)
            if ($items.Count -gt 0) {
                $leagueId = $candidateLeagueId
                $matchId = [int]$items[0].match_id
                break
            }
        }
    } catch {}
}
if (-not $leagueId) {
    Write-Host "FAIL: could not discover league_id from /home."
    exit 1
}
if (-not $matchId) {
    Write-Host "SKIP: no matches available for selected league/gw; detail checks skipped."
    exit 0
}
Write-Host "PASS: using league_id=$leagueId match_id=$matchId"
Write-Host ""

Write-Host "3) GET match detail -> expect 200 + ETag, then If-None-Match -> 304"
$d1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/matches/$matchId" -Headers @("Authorization: Bearer $token")
$cc1 = Header-Value -Headers $d1.headers -Name "Cache-Control"
$etag1 = Header-Value -Headers $d1.headers -Name "ETag"
$okShape = $false
$okMetaEtag = $false
if ($d1.status -eq 200) {
    try {
        $obj = $d1.body | ConvertFrom-Json
        $okShape = ($null -ne $obj.data.match.match_id) -and ($obj.data.rows -is [System.Array])
        $okMetaEtag = [string]$obj.meta.etag -eq [string]$etag1
    } catch {}
}
if ($d1.status -eq 200 -and $cc1 -eq "private, must-revalidate" -and $etag1 -and $okShape -and $okMetaEtag) {
    Write-Host "PASS: match detail returned expected envelope and Category A headers."
} else {
    Write-Host "FAIL: match detail response/header checks failed."
}
Write-Host "Status: $($d1.status)"
Write-Host "Cache-Control: $cc1"
Write-Host "ETag: $etag1"
Write-Host "Body: $($d1.body)"
Write-Host ""

if ($etag1) {
    $d304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/matches/$matchId" -Headers @(
        "Authorization: Bearer $token",
        "If-None-Match: $etag1"
    )
    if ($d304.status -eq 304) {
        Write-Host "PASS: conditional request returned 304."
    } else {
        Write-Host "FAIL: expected 304 Not Modified."
    }
    Write-Host "Status: $($d304.status)"
    Write-Host "Body length: $($d304.body.Length)"
    Write-Host ""
}

Write-Host "4) Invalid match -> expect 404 MATCH_NOT_FOUND"
$invalid = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/matches/$InvalidMatchId" -Headers @("Authorization: Bearer $token")
if ($invalid.status -eq 404 -and $invalid.body -match '"MATCH_NOT_FOUND"') {
    Write-Host "PASS: invalid match returned 404 MATCH_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 MATCH_NOT_FOUND."
}
Write-Host "Status: $($invalid.status)"
Write-Host "Body: $($invalid.body)"
Write-Host ""

Write-Host "5) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/matches/$matchId"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
