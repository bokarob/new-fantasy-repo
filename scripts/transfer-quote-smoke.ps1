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

    $headerFile = Join-Path $env:TEMP ("quote-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("quote-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("quote-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
            ($JsonBody | ConvertTo-Json -Compress) | Set-Content -Path $jsonFile -NoNewline
            $args += @('--data-binary', "@$jsonFile")
        }

        & curl.exe @args | Out-Null
        $headersRaw = if (Test-Path $headerFile) { Get-Content -Raw $headerFile } else { "" }
        $bodyRaw = if (Test-Path $bodyFile) { Get-Content -Raw $bodyFile } else { "" }
        $status = 0
        $matches = [regex]::Matches($headersRaw, "HTTP/\d\.\d\s+(\d+)")
        if ($matches.Count -gt 0) { $status = [int]$matches[$matches.Count - 1].Groups[1].Value }

        return @{ status = $status; headers = $headersRaw; body = $bodyRaw.Trim() }
    } finally {
        Remove-Item -Path $headerFile -Force -ErrorAction SilentlyContinue
        Remove-Item -Path $bodyFile -Force -ErrorAction SilentlyContinue
        if ($null -ne $jsonFile) { Remove-Item -Path $jsonFile -Force -ErrorAction SilentlyContinue }
    }
}

function Header-Value {
    param([string]$Headers, [string]$Name)
    $m = [regex]::Match($Headers, "(?im)^" + [regex]::Escape($Name) + ":\s*(.+)$")
    if ($m.Success) { return $m.Groups[1].Value.Trim() }
    return $null
}

Write-Host "Transfer quote smoke checks for TASK-004A"
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
    $runEmail = ("quote_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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
    Write-Host "FAIL: could not acquire token."
    Write-Host "Status: $($login.status)"
    Write-Host "Body: $($login.body)"
    exit 1
}
Write-Host "PASS: token acquired."
Write-Host ""

Write-Host "2) Choose league_id from /home (competitor preferred)"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = 1
$hasCompetitorLeague = $false
if ($homeResp.status -eq 200) {
    try {
        $obj = $homeResp.body | ConvertFrom-Json
        $leagues = @($obj.data.league_selector.leagues)
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

Write-Host "3) Fetch /leagues/{league}/team for roster ids"
$teamResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/team" -Headers @("Authorization: Bearer $token")
if ($teamResp.status -ne 200) {
    Write-Host "SKIP: /team did not return 200 (status $($teamResp.status))."
    Write-Host "Body: $($teamResp.body)"
    Write-Host "Cannot continue quote selection from roster."
    exit 0
}

$rosterIds = @()
try {
    $teamObj = $teamResp.body | ConvertFrom-Json
    foreach ($p in @($teamObj.data.roster.positions)) {
        $rosterIds += [int]$p.player.player_id
    }
} catch {}
if ($rosterIds.Count -eq 0) {
    Write-Host "SKIP: could not parse roster ids."
    exit 0
}
$outgoing = $rosterIds[0]
$incoming = $null
for ($i = 1; $i -le 300; $i++) {
    if ($rosterIds -notcontains $i) { $incoming = $i; break }
}
if ($null -eq $incoming) { $incoming = 99999 }

Write-Host "4) Quote normal candidate"
$quoteResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/transfers/quote" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    outgoing_player_ids = @($outgoing)
    incoming_player_ids = @($incoming)
}

$cc = Header-Value -Headers $quoteResp.headers -Name "Cache-Control"
$quoteOk = $false
try {
    $q = $quoteResp.body | ConvertFrom-Json
    $quoteOk = ($quoteResp.status -eq 200) -and ($cc -eq "no-store") -and ($null -eq $q.meta.etag) -and ($null -ne $q.data.is_valid)
} catch {}
if ($quoteOk) {
    Write-Host "PASS: quote returned 200 + no-store + meta.etag null."
} else {
    Write-Host "FAIL: quote response shape/headers unexpected."
}
Write-Host "Status: $($quoteResp.status)"
Write-Host "Cache-Control: $cc"
Write-Host "Body: $($quoteResp.body)"
Write-Host ""

Write-Host "5) Force invalid overlap (incoming=outgoing) -> TRANSFER_SAME_PLAYER"
$badResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/transfers/quote" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    outgoing_player_ids = @($outgoing)
    incoming_player_ids = @($outgoing)
}
$badOk = $false
try {
    $b = $badResp.body | ConvertFrom-Json
    $codes = @($b.data.violations | ForEach-Object { $_.code })
    $badOk = ($badResp.status -eq 200) -and ($b.data.is_valid -eq $false) -and ($codes -contains "TRANSFER_SAME_PLAYER")
} catch {}
if ($badOk) {
    Write-Host "PASS: overlap violation returned as soft validation result."
} else {
    Write-Host "FAIL: expected TRANSFER_SAME_PLAYER violation."
}
Write-Host "Status: $($badResp.status)"
Write-Host "Body: $($badResp.body)"
Write-Host ""

Write-Host "6) Missing token -> 401"
$noAuth = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/transfers/quote" -Headers @("Content-Type: application/json") -JsonBody @{
    outgoing_player_ids = @($outgoing)
    incoming_player_ids = @($incoming)
}
if ($noAuth.status -eq 401) { Write-Host "PASS: missing token returned 401." } else { Write-Host "FAIL: expected 401." }
Write-Host "Status: $($noAuth.status)"
Write-Host "Body: $($noAuth.body)"
