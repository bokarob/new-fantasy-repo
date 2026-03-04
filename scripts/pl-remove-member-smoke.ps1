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

    $headerFile = Join-Path $env:TEMP ("pl-remove-member-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-remove-member-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-remove-member-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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
    if ($m.Success) {
        return $m.Groups[1].Value.Trim()
    }
    return $null
}

Write-Host "Private league remove member smoke checks for TASK-032"
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
    $runEmail = ("pl_remove_member_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Select league + admin competitor, create private league"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = $null
$adminCompetitorId = $null
if ($homeResp.status -eq 200) {
    try {
        $obj = $homeResp.body | ConvertFrom-Json
        $leagues = @($obj.data.league_selector.leagues)
        if ($leagues.Count -gt 0) {
            $leagueId = [int]$leagues[0].league_id
            $adminCompetitorId = [int]$leagues[0].competitor.competitor_id
        }
    } catch {}
}
if (-not $leagueId -or -not $adminCompetitorId) {
    Write-Host "FAIL: could not discover league_id/admin competitor from /home."
    Write-Host "Status: $($homeResp.status)"
    Write-Host "Body: $($homeResp.body)"
    exit 1
}

$plName = "PLRM " + [int][double]::Parse((Get-Date -UFormat %s))
$create = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{ leaguename = $plName }
$privateleagueId = $null
if ($create.status -eq 200) {
    try { $privateleagueId = [int](($create.body | ConvertFrom-Json).data.privateleague_id) } catch {}
}
if (-not $privateleagueId) {
    Write-Host "FAIL: could not create private league."
    Write-Host "Status: $($create.status)"
    Write-Host "Body: $($create.body)"
    exit 1
}
Write-Host "PASS: using league_id=$leagueId privateleague_id=$privateleagueId admin_competitor_id=$adminCompetitorId"
Write-Host ""

Write-Host "3) Invite a second competitor (pending member) to remove"
$queries = @("an","er","ar","st","re","in","ka","ma","jo","mi","sz")
$targetCompetitorId = $null
$selectedQuery = $null
foreach ($q in $queries) {
    $search = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/invite/search?q=$q&limit=25" -Headers @("Authorization: Bearer $token")
    if ($search.status -eq 200) {
        try {
            $obj = $search.body | ConvertFrom-Json
            $items = @($obj.data.items)
            foreach ($it in $items) {
                if (-not $it.already_member -and -not $it.already_invited -and ([int]$it.competitor_id -ne $adminCompetitorId)) {
                    $targetCompetitorId = [int]$it.competitor_id
                    $selectedQuery = $q
                    break
                }
            }
        } catch {}
    }
    if ($targetCompetitorId) { break }
}
if (-not $targetCompetitorId) {
    Write-Host "FAIL: could not find inviteable competitor. Seed a second competitor and rerun."
    exit 1
}

$invite = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/invite" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{ competitor_id = $targetCompetitorId }
if ($invite.status -ne 200) {
    Write-Host "FAIL: invite setup step failed."
    Write-Host "Status: $($invite.status)"
    Write-Host "Body: $($invite.body)"
    exit 1
}
Write-Host "PASS: invited competitor_id=$targetCompetitorId via q=$selectedQuery"
Write-Host ""

Write-Host "4) POST remove -> expect 200 ok:true + no-store + meta.etag null"
$remove = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/members/$targetCompetitorId/remove" -Headers @(
    "Authorization: Bearer $token"
)
$removeCc = Header-Value -Headers $remove.headers -Name "Cache-Control"
$removeOk = $false
if ($remove.status -eq 200) {
    try {
        $obj = $remove.body | ConvertFrom-Json
        $removeOk = ($obj.data.ok -eq $true) -and ($null -eq $obj.meta.etag) -and ($removeCc -eq "no-store")
    } catch {}
}
if ($removeOk) {
    Write-Host "PASS: remove returned expected Category C response."
} else {
    Write-Host "FAIL: remove response/header mismatch."
}
Write-Host "Status: $($remove.status)"
Write-Host "Cache-Control: $removeCc"
Write-Host "Body: $($remove.body)"
Write-Host ""

Write-Host "5) GET detail should not contain removed competitor in pending_members"
$detail = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId" -Headers @(
    "Authorization: Bearer $token"
)
$stillPending = $false
if ($detail.status -eq 200) {
    try {
        $obj = $detail.body | ConvertFrom-Json
        $pending = @($obj.data.pending_members)
        foreach ($p in $pending) {
            if ([int]$p.competitor_id -eq $targetCompetitorId) {
                $stillPending = $true
                break
            }
        }
    } catch {}
}
if ($detail.status -eq 200 -and -not $stillPending) {
    Write-Host "PASS: detail reflects removal."
} elseif ($detail.status -eq 409 -and $detail.body -match '"RANKING_NOT_AVAILABLE"') {
    Write-Host "WARN: detail returned optional 409 RANKING_NOT_AVAILABLE; using direct re-remove check next."
} else {
    Write-Host "FAIL: detail verification failed."
}
Write-Host "Status: $($detail.status)"
Write-Host "Body: $($detail.body)"
Write-Host ""

Write-Host "6) Re-remove same competitor -> expect 404 MEMBER_NOT_FOUND"
$removeAgain = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/members/$targetCompetitorId/remove" -Headers @(
    "Authorization: Bearer $token"
)
if ($removeAgain.status -eq 404 -and $removeAgain.body -match '"MEMBER_NOT_FOUND"') {
    Write-Host "PASS: second remove rejected with MEMBER_NOT_FOUND."
} else {
    Write-Host "FAIL: expected 404 MEMBER_NOT_FOUND on second remove."
}
Write-Host "Status: $($removeAgain.status)"
Write-Host "Body: $($removeAgain.body)"
Write-Host ""

Write-Host "7) Self-remove blocked -> expect 409 CANNOT_REMOVE_SELF"
$selfRemove = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/members/$adminCompetitorId/remove" -Headers @(
    "Authorization: Bearer $token"
)
if ($selfRemove.status -eq 409 -and $selfRemove.body -match '"CANNOT_REMOVE_SELF"') {
    Write-Host "PASS: self-remove blocked by CANNOT_REMOVE_SELF."
} else {
    Write-Host "FAIL: expected 409 CANNOT_REMOVE_SELF."
}
Write-Host "Status: $($selfRemove.status)"
Write-Host "Body: $($selfRemove.body)"
Write-Host ""

Write-Host "8) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/members/$targetCompetitorId/remove"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
