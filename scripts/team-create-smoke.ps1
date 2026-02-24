param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
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

    $headerFile = Join-Path $env:TEMP ("team-create-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("team-create-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }

        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("team-create-j-" + [guid]::NewGuid().ToString() + ".json")
            ($JsonBody | ConvertTo-Json -Compress -Depth 6) | Set-Content -Path $jsonFile -NoNewline
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

function New-TestUserToken {
    param([string]$Tag)

    $email = ("team_create_" + $Tag + "_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")

    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/register" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $email
        password = $Password
        alias = "phase_d"
        lang = "en"
    })
    [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/otp/verify" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $email
        otp = $Otp
        purpose = "register"
    })

    $login = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $email
        password = $Password
    }
    if ($login.status -ne 200) {
        return @{ ok = $false; email = $email; token = $null; raw = $login }
    }
    $token = $null
    try { $token = (($login.body | ConvertFrom-Json).data.tokens.access_token) } catch {}
    return @{ ok = (-not [string]::IsNullOrWhiteSpace($token)); email = $email; token = $token; raw = $login }
}

Write-Host "Team create smoke checks for TASK-006B"
Write-Host "Base URL: $BaseUrl"
Write-Host "League: $LeagueId"
Write-Host ""

Write-Host "1) Register/login fresh user (create-flow user)"
$u1 = New-TestUserToken -Tag "a"
if (-not $u1.ok) {
    Write-Host "FAIL: could not acquire token for user1."
    Write-Host "Email: $($u1.email)"
    Write-Host "Status: $($u1.raw.status)"
    Write-Host "Body: $($u1.raw.body)"
    exit 1
}
Write-Host "PASS: user1 token acquired ($($u1.email))."
Write-Host ""

Write-Host "2) GET /leagues/$LeagueId/team/builder"
$teamBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @("Authorization: Bearer $($u1.token)")
$teamBeforeCc = Header-Value -Headers $teamBefore.headers -Name "Cache-Control"
$teamBeforeEtag = Header-Value -Headers $teamBefore.headers -Name "ETag"
Write-Host "Pre-create GET /team -> status=$($teamBefore.status) cache-control=$teamBeforeCc etag=$teamBeforeEtag"

$builder1 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team/builder" -Headers @("Authorization: Bearer $($u1.token)")
if ($builder1.status -ne 200) {
    Write-Host "FAIL: expected 200 from builder for user1."
    Write-Host "Status: $($builder1.status)"
    Write-Host "Body: $($builder1.body)"
    exit 1
}

$builderObj1 = $null
try { $builderObj1 = $builder1.body | ConvertFrom-Json } catch {}
if ($null -eq $builderObj1) {
    Write-Host "FAIL: builder response JSON parse failed."
    exit 1
}

$players = @($builderObj1.data.players)
if ($players.Count -lt 8) {
    Write-Host "FAIL: builder has fewer than 8 players."
    Write-Host "Count: $($players.Count)"
    exit 1
}

$selected = @($players | Select-Object -First 8)
$playerIds = @($selected | ForEach-Object { [int]$_.player_id })
$captainId = [int]$playerIds[0]
$favoriteTeamId = [int]$selected[0].team.team_id
$teamname = "TeamCreate_" + [int][double]::Parse((Get-Date -UFormat %s))
Write-Host "PASS: selected 8 players; captain=$captainId favorite_team_id=$favoriteTeamId"
Write-Host ""

Write-Host "3) POST /leagues/$LeagueId/team (create)"
$createResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @(
    "Authorization: Bearer $($u1.token)",
    "Content-Type: application/json"
) -JsonBody @{
    teamname = $teamname
    player_ids = $playerIds
    captain_player_id = $captainId
    favorite_team_id = $favoriteTeamId
}

$createCacheControl = Header-Value -Headers $createResp.headers -Name "Cache-Control"
$createObj = $null
$createOk = $false
$competitorId = $null
try {
    $createObj = $createResp.body | ConvertFrom-Json
    $competitorId = [int]$createObj.data.competitor_id
    $createOk = ($createResp.status -eq 200 -and $createCacheControl -eq "no-store" -and $createObj.meta.etag -eq $null -and $competitorId -gt 0)
} catch {}

if ($createOk) {
    Write-Host "PASS: team created with no-store + meta.etag=null."
} else {
    Write-Host "FAIL: create response did not match expected contract."
}
Write-Host "Status: $($createResp.status)"
Write-Host "Cache-Control: $createCacheControl"
Write-Host "Body: $($createResp.body)"
Write-Host ""

if (-not $createOk) { exit 1 }

Write-Host "4) GET /leagues/$LeagueId/team (ensure existing GET still works)"
$teamResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @("Authorization: Bearer $($u1.token)")
$teamAfterCc = Header-Value -Headers $teamResp.headers -Name "Cache-Control"
$teamAfterEtag = Header-Value -Headers $teamResp.headers -Name "ETag"
$teamOk = $false
try {
    $teamObj = $teamResp.body | ConvertFrom-Json
    $teamOk = ($teamResp.status -eq 200 -and [int]$teamObj.data.competitor.competitor_id -eq $competitorId)
} catch {}
if ($teamOk) {
    Write-Host "PASS: GET /team returns created competitor."
} else {
    Write-Host "FAIL: GET /team did not return expected competitor."
}
Write-Host "Status: $($teamResp.status)"
Write-Host "Cache-Control: $teamAfterCc"
Write-Host "ETag: $teamAfterEtag"
if (-not [string]::IsNullOrWhiteSpace($teamBeforeEtag) -and -not [string]::IsNullOrWhiteSpace($teamAfterEtag)) {
    Write-Host "ETag changed pre->post create: $($teamBeforeEtag -ne $teamAfterEtag)"
} else {
    Write-Host "ETag compare pre->post create: not applicable (pre-create had no ETag, expected when NO_COMPETITOR)."
}
Write-Host ""

Write-Host "5) GET builder again -> expect 409 TEAM_ALREADY_EXISTS"
$builderAgain = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team/builder" -Headers @("Authorization: Bearer $($u1.token)")
if ($builderAgain.status -eq 409 -and $builderAgain.body -match '"TEAM_ALREADY_EXISTS"') {
    Write-Host "PASS: builder blocked after team creation."
} else {
    Write-Host "FAIL: expected 409 TEAM_ALREADY_EXISTS from builder."
}
Write-Host "Status: $($builderAgain.status)"
Write-Host "Body: $($builderAgain.body)"
Write-Host ""

Write-Host "6) POST create again -> expect 409 TEAM_ALREADY_EXISTS"
$createAgain = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @(
    "Authorization: Bearer $($u1.token)",
    "Content-Type: application/json"
) -JsonBody @{
    teamname = "DupTeam"
    player_ids = $playerIds
    captain_player_id = $captainId
    favorite_team_id = $favoriteTeamId
}
$createAgainCc = Header-Value -Headers $createAgain.headers -Name "Cache-Control"
if ($createAgain.status -eq 409 -and $createAgain.body -match '"TEAM_ALREADY_EXISTS"') {
    Write-Host "PASS: duplicate create rejected."
} else {
    Write-Host "FAIL: expected 409 TEAM_ALREADY_EXISTS on duplicate create."
}
Write-Host "Status: $($createAgain.status)"
Write-Host "Cache-Control: $createAgainCc"
Write-Host "Body: $($createAgain.body)"
Write-Host ""

Write-Host "7) Negative budget check with second fresh user"
$u2 = New-TestUserToken -Tag "b"
if (-not $u2.ok) {
    Write-Host "FAIL: could not acquire token for user2."
    exit 1
}

$builder2 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/team/builder" -Headers @("Authorization: Bearer $($u2.token)")
if ($builder2.status -ne 200) {
    Write-Host "FAIL: expected 200 from builder for user2."
    Write-Host "Status: $($builder2.status)"
    Write-Host "Body: $($builder2.body)"
    exit 1
}

$builderObj2 = $builder2.body | ConvertFrom-Json
$sorted = @($builderObj2.data.players | Sort-Object -Property @{Expression = { [double]$_.price }; Descending = $true})
if ($sorted.Count -lt 8) {
    Write-Host "FAIL: not enough players for budget test."
    exit 1
}
$expensive = @()
$teamCounts = @{}
foreach ($p in $sorted) {
    $tid = [int]$p.team.team_id
    if (-not $teamCounts.ContainsKey($tid)) {
        $teamCounts[$tid] = 0
    }
    if ($teamCounts[$tid] -ge 2) {
        continue
    }
    $expensive += $p
    $teamCounts[$tid] = [int]$teamCounts[$tid] + 1
    if ($expensive.Count -eq 8) {
        break
    }
}
if ($expensive.Count -lt 8) {
    Write-Host "FAIL: could not build 8-player set respecting max-2-per-team."
    exit 1
}
$expensiveIds = @($expensive | ForEach-Object { [int]$_.player_id })
$expensiveCaptain = [int]$expensiveIds[0]
$expensiveFav = [int]$expensive[0].team.team_id
$sum = 0.0
foreach ($p in $expensive) { $sum += [double]$p.price }
Write-Host ("Top-8 constrained total price: {0:N1}" -f $sum)

$budgetResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/team" -Headers @(
    "Authorization: Bearer $($u2.token)",
    "Content-Type: application/json"
) -JsonBody @{
    teamname = "OverBudget"
    player_ids = $expensiveIds
    captain_player_id = $expensiveCaptain
    favorite_team_id = $expensiveFav
}

if ($sum -le 80.0) {
    Write-Host "INFO: top-8 sum <= 80.0; strict INITIAL_BUDGET_EXCEEDED assertion skipped for this dataset."
    Write-Host "Status: $($budgetResp.status)"
    Write-Host "Body: $($budgetResp.body)"
} elseif ($budgetResp.status -eq 422 -and $budgetResp.body -match '"INITIAL_BUDGET_EXCEEDED"') {
    Write-Host "PASS: budget exceeded rejected with INITIAL_BUDGET_EXCEEDED."
} else {
    Write-Host "FAIL: expected 422 INITIAL_BUDGET_EXCEEDED."
    Write-Host "Status: $($budgetResp.status)"
    Write-Host "Body: $($budgetResp.body)"
    exit 1
}
