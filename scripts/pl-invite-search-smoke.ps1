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

    $headerFile = Join-Path $env:TEMP ("pl-invite-search-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-invite-search-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-invite-search-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Private league invite search smoke checks for TASK-027"
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
    $runEmail = ("pl_invite_search_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Pick league_id, create a private league as admin, and derive q"
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

$plId = $null
$name = "PLIS " + [int][double]::Parse((Get-Date -UFormat %s))
$create = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    leaguename = $name
}
if ($create.status -eq 200) {
    try { $plId = [int](($create.body | ConvertFrom-Json).data.privateleague_id) } catch {}
}
if (-not $plId) {
    Write-Host "FAIL: could not create privateleague_id for search."
    Write-Host "Create status: $($create.status)"
    Write-Host "Create body: $($create.body)"
    exit 1
}

$q = "a"
$competitor = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
if ($competitor.status -eq 200) {
    try {
        $teamObj = $competitor.body | ConvertFrom-Json
        $teamname = [string]$teamObj.data.competitor.teamname
        if ($teamname.Length -ge 2) {
            $q = $teamname.Substring(0, 2)
        }
    } catch {}
}
if ($q.Length -lt 2) {
    $q = "ab"
}
Write-Host "PASS: using league_id=$leagueId privateleague_id=$plId q=$q"
Write-Host ""

Write-Host "3) GET invite search -> expect 200 + Cache-Control contains max-age=30"
$search = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$plId/invite/search?q=$q" -Headers @("Authorization: Bearer $token")
$cc = Header-Value -Headers $search.headers -Name "Cache-Control"
$etag = Header-Value -Headers $search.headers -Name "ETag"
$shapeOk = $false
if ($search.status -eq 200) {
    try {
        $obj = $search.body | ConvertFrom-Json
        $shapeOk = ($obj.data.items -is [array]) -and ([string]$obj.data.q -eq $q) -and ([int]$obj.data.league_id -eq $leagueId)
    } catch {}
}
if ($search.status -eq 200 -and $shapeOk -and $cc -match "max-age=30") {
    Write-Host "PASS: search returned expected response with short TTL cache header."
} else {
    Write-Host "FAIL: invite search did not meet expected response/header requirements."
}
Write-Host "Status: $($search.status)"
Write-Host "Cache-Control: $cc"
Write-Host "ETag: $etag"
Write-Host "Body: $($search.body)"
Write-Host ""

Write-Host "4) Invalid q (len<2) -> expect 422 QUERY_TOO_SHORT or 200 with items=[]"
$short = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$plId/invite/search?q=a" -Headers @("Authorization: Bearer $token")
$shortOk = $false
if ($short.status -eq 422 -and $short.body -match '"QUERY_TOO_SHORT"') {
    $shortOk = $true
}
if (-not $shortOk -and $short.status -eq 200) {
    try {
        $shortObj = $short.body | ConvertFrom-Json
        $shortOk = ($shortObj.data.items -is [array]) -and (@($shortObj.data.items).Count -eq 0)
    } catch {}
}
if ($shortOk) {
    Write-Host "PASS: short query behavior is accepted by contract."
} else {
    Write-Host "FAIL: short query behavior mismatch."
}
Write-Host "Status: $($short.status)"
Write-Host "Body: $($short.body)"
Write-Host ""

Write-Host "5) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$plId/invite/search?q=$q"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
