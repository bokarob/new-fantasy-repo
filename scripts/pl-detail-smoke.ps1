param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "phase_d_auth_test@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456",
    [int]$InvalidPrivateleagueId = 999999
)

function Invoke-CurlRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string[]]$Headers = @(),
        [object]$JsonBody = $null
    )

    $headerFile = Join-Path $env:TEMP ("pl-detail-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-detail-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-detail-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Private league detail endpoint smoke checks for TASK-025"
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
    $runEmail = ("pl_detail_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Pick league_id and ensure at least one private league exists"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = $null
if ($homeResp.status -eq 200) {
    try {
        $homeObj = $homeResp.body | ConvertFrom-Json
        $ls = @($homeObj.data.league_selector.leagues)
        if ($ls.Count -gt 0) { $leagueId = [int]$ls[0].league_id }
    } catch {}
}
if (-not $leagueId) {
    Write-Host "FAIL: could not discover league_id from /home."
    Write-Host "Status: $($homeResp.status)"
    Write-Host "Body: $($homeResp.body)"
    exit 1
}

$listResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Authorization: Bearer $token")
$privateleagueId = $null
if ($listResp.status -eq 200) {
    try {
        $listObj = $listResp.body | ConvertFrom-Json
        $existing = @($listObj.data.leagues)
        if ($existing.Count -gt 0) {
            $privateleagueId = [int]$existing[0].privateleague_id
        }
    } catch {}
}

if (-not $privateleagueId) {
    $name = "PL Detail Smoke " + [int][double]::Parse((Get-Date -UFormat %s))
    $createResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @(
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ) -JsonBody @{
        leaguename = $name
    }
    if ($createResp.status -eq 200) {
        try { $privateleagueId = [int](($createResp.body | ConvertFrom-Json).data.privateleague_id) } catch {}
    }
}

if (-not $privateleagueId) {
    Write-Host "FAIL: could not ensure a private league for detail checks."
    Write-Host "List status: $($listResp.status)"
    Write-Host "List body: $($listResp.body)"
    exit 1
}
Write-Host "PASS: using league_id=$leagueId privateleague_id=$privateleagueId"
Write-Host ""

Write-Host "3) GET detail -> expect 200 + ETag + required blocks"
$detail1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId" -Headers @("Authorization: Bearer $token")
$cc1 = Header-Value -Headers $detail1.headers -Name "Cache-Control"
$etag1 = Header-Value -Headers $detail1.headers -Name "ETag"
$okShape = $false
$okMetaEtag = $false
if ($detail1.status -eq 200) {
    try {
        $obj = $detail1.body | ConvertFrom-Json
        $okShape = ($null -ne $obj.data.privateleague) -and ($null -ne $obj.data.membership) -and ($null -ne $obj.data.gameweek) -and ($null -ne $obj.data.standings) -and ($obj.data.pending_members -is [array]) -and ($null -ne $obj.data.permissions)
        $okMetaEtag = [string]$obj.meta.etag -eq [string]$etag1
    } catch {}
}
if ($detail1.status -eq 200 -and $cc1 -eq "private, must-revalidate" -and $etag1 -and $okShape -and $okMetaEtag) {
    Write-Host "PASS: detail returned expected envelope and cache headers."
} elseif ($detail1.status -eq 409 -and $detail1.body -match '"RANKING_NOT_AVAILABLE"') {
    Write-Host "PASS: detail returned optional 409 RANKING_NOT_AVAILABLE (no standings rows yet)."
} else {
    Write-Host "FAIL: detail did not meet expected response/header requirements."
}
Write-Host "Status: $($detail1.status)"
Write-Host "Cache-Control: $cc1"
Write-Host "ETag: $etag1"
Write-Host "Body: $($detail1.body)"
Write-Host ""

if ($etag1) {
    Write-Host "4) Repeat with If-None-Match -> expect 304"
    $detail304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId" -Headers @(
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
} else {
    Write-Host "4) Skip If-None-Match check (no ETag due non-200 response)."
    Write-Host ""
}

Write-Host "5) Invalid privateleague_id -> expect 404 PRIVATE_LEAGUE_NOT_FOUND"
$invalid = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$InvalidPrivateleagueId" -Headers @("Authorization: Bearer $token")
if ($invalid.status -eq 404 -and $invalid.body -match '"PRIVATE_LEAGUE_NOT_FOUND"') {
    Write-Host "PASS: invalid id returned 404 PRIVATE_LEAGUE_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 PRIVATE_LEAGUE_NOT_FOUND."
}
Write-Host "Status: $($invalid.status)"
Write-Host "Body: $($invalid.body)"
Write-Host ""

Write-Host "6) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
