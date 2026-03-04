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

    $headerFile = Join-Path $env:TEMP ("pl-invite-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-invite-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-invite-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Private league invite endpoint smoke checks for TASK-028"
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
    $runEmail = ("pl_invite_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Select league and create private league as admin"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = $null
if ($homeResp.status -eq 200) {
    try {
        $obj = $homeResp.body | ConvertFrom-Json
        $leagues = @($obj.data.league_selector.leagues)
        if ($leagues.Count -gt 0) {
            $leagueId = [int]$leagues[0].league_id
        }
    } catch {}
}
if (-not $leagueId) {
    Write-Host "FAIL: could not discover league_id from /home."
    Write-Host "Status: $($homeResp.status)"
    Write-Host "Body: $($homeResp.body)"
    exit 1
}

$plName = "PLI " + [int][double]::Parse((Get-Date -UFormat %s))
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
Write-Host "PASS: using league_id=$leagueId privateleague_id=$privateleagueId"
Write-Host ""

Write-Host "3) Search a competitor to invite"
$queries = @("an","er","ar","st","re","in","ka","ma","jo","mi","sz")
$inviteCompetitorId = $null
$selectedQuery = $null
foreach ($q in $queries) {
    $search = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/invite/search?q=$q&limit=25" -Headers @("Authorization: Bearer $token")
    if ($search.status -eq 200) {
        try {
            $obj = $search.body | ConvertFrom-Json
            $items = @($obj.data.items)
            foreach ($it in $items) {
                if (-not $it.already_member -and -not $it.already_invited) {
                    $inviteCompetitorId = [int]$it.competitor_id
                    $selectedQuery = $q
                    break
                }
            }
        } catch {}
    }
    if ($inviteCompetitorId) { break }
}
if (-not $inviteCompetitorId) {
    Write-Host "FAIL: could not find an inviteable competitor via invite/search."
    exit 1
}
Write-Host "PASS: selected competitor_id=$inviteCompetitorId via q=$selectedQuery"
Write-Host ""

Write-Host "4) Capture pre-invite ETags for list + detail"
$listBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Authorization: Bearer $token")
$detailBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId" -Headers @("Authorization: Bearer $token")
$etagListBefore = Header-Value -Headers $listBefore.headers -Name "ETag"
$etagDetailBefore = Header-Value -Headers $detailBefore.headers -Name "ETag"
Write-Host "List ETag before: $etagListBefore"
Write-Host "Detail ETag before: $etagDetailBefore"
Write-Host ""

Write-Host "5) POST invite -> expect 200 ok:true + no-store + meta.etag null"
$invite = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/invite" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{ competitor_id = $inviteCompetitorId }
$inviteCc = Header-Value -Headers $invite.headers -Name "Cache-Control"
$inviteOk = $false
if ($invite.status -eq 200) {
    try {
        $obj = $invite.body | ConvertFrom-Json
        $inviteOk = ($obj.data.ok -eq $true) -and ($null -eq $obj.meta.etag) -and ($inviteCc -eq "no-store")
    } catch {}
}
if ($inviteOk) {
    Write-Host "PASS: invite returned expected Category C response."
} else {
    Write-Host "FAIL: invite response/header mismatch."
}
Write-Host "Status: $($invite.status)"
Write-Host "Cache-Control: $inviteCc"
Write-Host "Body: $($invite.body)"
Write-Host ""

Write-Host "6) Verify detail has pending member and ETag moved"
$detailAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId" -Headers @("Authorization: Bearer $token")
$etagDetailAfter = Header-Value -Headers $detailAfter.headers -Name "ETag"
$hasPending = $false
if ($detailAfter.status -eq 200) {
    try {
        $obj = $detailAfter.body | ConvertFrom-Json
        $pending = @($obj.data.pending_members)
        foreach ($p in $pending) {
            if ([int]$p.competitor_id -eq $inviteCompetitorId) {
                $hasPending = $true
                break
            }
        }
    } catch {}
}
$detailEtagChanged = $etagDetailBefore -and $etagDetailAfter -and ($etagDetailBefore -ne $etagDetailAfter)
if ($detailAfter.status -eq 200 -and $hasPending -and $detailEtagChanged) {
    Write-Host "PASS: detail updated and ETag changed."
} elseif ($detailAfter.status -eq 409 -and $detailAfter.body -match '"RANKING_NOT_AVAILABLE"') {
    $verifyQ = if ($selectedQuery) { $selectedQuery } else { "an" }
    $searchVerify = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/invite/search?q=$verifyQ&limit=25" -Headers @("Authorization: Bearer $token")
    $foundInvitedFlag = $false
    if ($searchVerify.status -eq 200) {
        try {
            $sobj = $searchVerify.body | ConvertFrom-Json
            $items = @($sobj.data.items)
            foreach ($it in $items) {
                if ([int]$it.competitor_id -eq $inviteCompetitorId -and $it.already_invited) {
                    $foundInvitedFlag = $true
                    break
                }
            }
        } catch {}
    }
    if ($foundInvitedFlag) {
        Write-Host "PASS: detail unavailable with optional 409; invite reflected via invite/search already_invited flag."
    } else {
        Write-Host "FAIL: detail unavailable and invite/search did not reflect invited status."
    }
} else {
    Write-Host "FAIL: detail did not reflect invite as expected."
}
Write-Host "Detail ETag after: $etagDetailAfter"
Write-Host "Body: $($detailAfter.body)"
Write-Host ""

Write-Host "7) Verify list ETag changed after invite"
$listAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Authorization: Bearer $token")
$etagListAfter = Header-Value -Headers $listAfter.headers -Name "ETag"
$listEtagChanged = $etagListBefore -and $etagListAfter -and ($etagListBefore -ne $etagListAfter)
if ($listAfter.status -eq 200 -and $listEtagChanged) {
    Write-Host "PASS: list ETag changed after invite."
} else {
    Write-Host "FAIL: list ETag did not change after invite."
}
Write-Host "List ETag after: $etagListAfter"
Write-Host ""

Write-Host "8) Duplicate invite -> expect 409 ALREADY_INVITED or ALREADY_MEMBER"
$dup = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/invite" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{ competitor_id = $inviteCompetitorId }
if ($dup.status -eq 409 -and ($dup.body -match '"ALREADY_INVITED"' -or $dup.body -match '"ALREADY_MEMBER"')) {
    Write-Host "PASS: duplicate invite rejected with expected 409 code."
} else {
    Write-Host "FAIL: duplicate invite behavior mismatch."
}
Write-Host "Status: $($dup.status)"
Write-Host "Body: $($dup.body)"
Write-Host ""

Write-Host "9) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/invite" -Headers @(
    "Content-Type: application/json"
) -JsonBody @{ competitor_id = $inviteCompetitorId }
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
