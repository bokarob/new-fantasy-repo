param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "phase_d_auth_test@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456",
    [int]$LeagueId = 1
)

function Invoke-CurlRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string[]]$Headers = @(),
        [object]$JsonBody = $null
    )

    $headerFile = Join-Path $env:TEMP ("builder-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("builder-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }

        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("builder-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Team builder smoke checks for TASK-006A"
Write-Host "Base URL: $BaseUrl"
Write-Host "League: $LeagueId"
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
    $runEmail = ("builder_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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
Write-Host "PASS: access token acquired."
Write-Host "Using email: $runEmail"
Write-Host ""

Write-Host "2) GET /leagues/$LeagueId/team/builder"
$builder = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team/builder" -Headers @("Authorization: Bearer $token")
$cacheControl = Header-Value -Headers $builder.headers -Name "Cache-Control"
$etag = Header-Value -Headers $builder.headers -Name "ETag"

if ($builder.status -eq 409 -and $builder.body -match '"TEAM_ALREADY_EXISTS"') {
    Write-Host "INFO: account already has team in this league; builder correctly blocked."
    Write-Host "Status: $($builder.status)"
    Write-Host "Body: $($builder.body)"
    Write-Host ""
    Write-Host "3) Existing-team case: expect 409 TEAM_ALREADY_EXISTS"
    Write-Host "PASS: received TEAM_ALREADY_EXISTS."
    exit 0
}

if ($builder.status -ne 200) {
    Write-Host "FAIL: expected 200 from builder."
    Write-Host "Status: $($builder.status)"
    Write-Host "Body: $($builder.body)"
    exit 1
}

$obj = $null
$rosterSizeOk = $false
$playersLen = 0
try {
    $obj = $builder.body | ConvertFrom-Json
    $rosterSizeOk = ([int]$obj.data.rules.roster_size -eq 8)
    $playersLen = @($obj.data.players).Count
} catch {}

$cacheOk = ($cacheControl -eq "private, must-revalidate")
$etagOk = -not [string]::IsNullOrWhiteSpace($etag)
$playersOk = ($playersLen -gt 0)

if ($cacheOk -and $etagOk -and $rosterSizeOk -and $playersOk) {
    Write-Host "PASS: builder returned 200 with expected headers and payload."
} else {
    Write-Host "FAIL: builder response did not match expected contract."
}
Write-Host "Status: $($builder.status)"
Write-Host "Cache-Control: $cacheControl"
Write-Host "ETag: $etag"
Write-Host "rules.roster_size: $(if ($null -ne $obj) { $obj.data.rules.roster_size } else { 'n/a' })"
Write-Host "players.length: $playersLen"
Write-Host ""

Write-Host "3) Repeat with If-None-Match -> expect 304"
$recheck = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team/builder" -Headers @(
    "Authorization: Bearer $token",
    "If-None-Match: $etag"
)
$etagRecheck = Header-Value -Headers $recheck.headers -Name "ETag"
if ($recheck.status -eq 304) {
    Write-Host "PASS: 304 Not Modified received."
} else {
    Write-Host "FAIL: expected 304 on conditional request."
}
Write-Host "Status: $($recheck.status)"
Write-Host "If-None-Match sent: $etag"
Write-Host "ETag returned: $etagRecheck"
Write-Host "Body length: $($recheck.body.Length)"
Write-Host ""

Write-Host "4) Existing-team case check (informational)"
Write-Host "Run this script with an account that already has a team in league $LeagueId to validate 409 TEAM_ALREADY_EXISTS."
