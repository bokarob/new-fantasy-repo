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

    $headerFile = Join-Path $env:TEMP ("rules-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("rules-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }

        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("rules-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Rules endpoint smoke checks for TASK-015"
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
    $runEmail = ("rules_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Pick a valid league_id from /home"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = $null
if ($homeResp.status -eq 200) {
    try {
        $homeObj = $homeResp.body | ConvertFrom-Json
        $leagues = @($homeObj.data.league_selector.leagues)
        if ($leagues.Count -gt 0) {
            $leagueId = [int]$leagues[0].league_id
        }
    } catch {}
}
if (-not $leagueId) {
    Write-Host "FAIL: could not discover a league_id from /home."
    Write-Host "Status: $($homeResp.status)"
    Write-Host "Body: $($homeResp.body)"
    exit 1
}
Write-Host "PASS: using league_id=$leagueId"
Write-Host ""

Write-Host "3) GET /leagues/$leagueId/rules -> expect 200 + Category A headers + rules constants"
$rules1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/rules" -Headers @("Authorization: Bearer $token")
$cc1 = Header-Value -Headers $rules1.headers -Name "Cache-Control"
$etag1 = Header-Value -Headers $rules1.headers -Name "ETag"
$okRoster = $false
$okBudget = $false
$okMetaEtag = $false
$hasIsFreeGw = $false
if ($rules1.status -eq 200) {
    try {
        $obj = $rules1.body | ConvertFrom-Json
        $okRoster = [int]$obj.data.rules.roster_size -eq 8
        $okBudget = [double]$obj.data.rules.initial_budget -eq 80.0
        $okMetaEtag = [string]$obj.meta.etag -eq [string]$etag1
        $hasIsFreeGw = $null -ne $obj.data.rules.is_free_gw
    } catch {}
}
if ($rules1.status -eq 200 -and $cc1 -eq "private, must-revalidate" -and $etag1 -and $okRoster -and $okBudget -and $okMetaEtag -and $hasIsFreeGw) {
    Write-Host "PASS: /rules returned expected envelope, headers, and core values."
} else {
    Write-Host "FAIL: /rules did not meet expected response/header requirements."
}
Write-Host "Status: $($rules1.status)"
Write-Host "Cache-Control: $cc1"
Write-Host "ETag: $etag1"
Write-Host "Body: $($rules1.body)"
Write-Host ""

if ($etag1) {
    Write-Host "4) Repeat with If-None-Match -> expect 304"
    $rules304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/rules" -Headers @(
        "Authorization: Bearer $token",
        "If-None-Match: $etag1"
    )
    if ($rules304.status -eq 304) {
        Write-Host "PASS: conditional request returned 304 Not Modified."
    } else {
        Write-Host "FAIL: expected 304 Not Modified."
    }
    Write-Host "Status: $($rules304.status)"
    Write-Host "Body length: $($rules304.body.Length)"
    Write-Host ""
}

Write-Host "5) Invalid league -> expect 404 LEAGUE_NOT_FOUND"
$invalid = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$InvalidLeagueId/rules" -Headers @("Authorization: Bearer $token")
if ($invalid.status -eq 404 -and $invalid.body -match '"LEAGUE_NOT_FOUND"') {
    Write-Host "PASS: invalid league returned 404 LEAGUE_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 LEAGUE_NOT_FOUND."
}
Write-Host "Status: $($invalid.status)"
Write-Host "Body: $($invalid.body)"
Write-Host ""

Write-Host "6) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/rules"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
