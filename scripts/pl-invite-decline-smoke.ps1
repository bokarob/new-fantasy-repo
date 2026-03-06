param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "phase_d_auth_test@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456",
    [int]$LeagueId = 0,
    [string]$InviteId = ""
)

function Invoke-CurlRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string[]]$Headers = @(),
        [object]$JsonBody = $null
    )

    $headerFile = Join-Path $env:TEMP ("pl-invite-decline-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-invite-decline-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }

        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-invite-decline-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

function Acquire-AccessToken {
    param(
        [string]$BaseUrl,
        [string]$Email,
        [string]$Password
    )

    $loginResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
        email = $Email
        password = $Password
    }
    if ($loginResp.status -ne 200) {
        return $null
    }

    try {
        return (($loginResp.body | ConvertFrom-Json).data.tokens.access_token)
    } catch {
        return $null
    }
}

Write-Host "Private league invite decline smoke checks for TASK-031"
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
    $runEmail = ("pl_invite_decline_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

if ($LeagueId -le 0) {
    Write-Host "2) Pick a valid league_id from /home"
    $homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
    if ($homeResp.status -eq 200) {
        try {
            $homeObj = $homeResp.body | ConvertFrom-Json
            $leagues = @($homeObj.data.league_selector.leagues)
            if ($leagues.Count -gt 0) {
                $LeagueId = [int]$leagues[0].league_id
            }
        } catch {}
    }
    if ($LeagueId -le 0) {
        Write-Host "FAIL: could not discover a league_id from /home."
        Write-Host "Status: $($homeResp.status)"
        Write-Host "Body: $($homeResp.body)"
        exit 1
    }
}
Write-Host "PASS: using league_id=$LeagueId"
Write-Host ""

Write-Host "3) Ensure pending invite exists"
$invitesResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/private-leagues/invites" -Headers @("Authorization: Bearer $token")
if ([string]::IsNullOrWhiteSpace($InviteId) -and $invitesResp.status -eq 200) {
    try {
        $obj = $invitesResp.body | ConvertFrom-Json
        $pending = @($obj.data.items | Where-Object { [string]$_.status -eq "pending" })
        if ($pending.Count -gt 0) {
            $InviteId = [string]$pending[0].invite_id
        }
    } catch {}
}

if ([string]::IsNullOrWhiteSpace($InviteId)) {
    Write-Host "INFO: no pending invite found; creating a fresh one as seeded admin."
    $competitorId = 0
    $homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
    if ($homeResp.status -eq 200) {
        try {
            $homeObj = $homeResp.body | ConvertFrom-Json
            foreach ($leagueRow in @($homeObj.data.league_selector.leagues)) {
                if ([int]$leagueRow.league_id -eq $LeagueId -and $null -ne $leagueRow.competitor -and $null -ne $leagueRow.competitor.competitor_id) {
                    $competitorId = [int]$leagueRow.competitor.competitor_id
                    break
                }
            }
        } catch {}
    }

    $adminToken = Acquire-AccessToken -BaseUrl $BaseUrl -Email "seed.user2@example.com" -Password $Password
    if ($competitorId -gt 0 -and $adminToken) {
        $tmpLeagueName = "PL Decline Smoke " + [int][double]::Parse((Get-Date -UFormat %s))
        $createResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/private-leagues" -Headers @(
            "Authorization: Bearer $adminToken",
            "Content-Type: application/json"
        ) -JsonBody @{
            leaguename = $tmpLeagueName
        }

        $tmpPrivateleagueId = 0
        if ($createResp.status -eq 200) {
            try { $tmpPrivateleagueId = [int](($createResp.body | ConvertFrom-Json).data.privateleague_id) } catch {}
        }

        if ($tmpPrivateleagueId -gt 0) {
            [void](Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/private-leagues/$tmpPrivateleagueId/invite" -Headers @(
                "Authorization: Bearer $adminToken",
                "Content-Type: application/json"
            ) -JsonBody @{
                competitor_id = $competitorId
            })

            $invitesResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/private-leagues/invites" -Headers @("Authorization: Bearer $token")
            if ($invitesResp.status -eq 200) {
                try {
                    $obj = $invitesResp.body | ConvertFrom-Json
                    $pending = @($obj.data.items | Where-Object { [string]$_.status -eq "pending" })
                    if ($pending.Count -gt 0) {
                        $InviteId = [string]$pending[0].invite_id
                    }
                } catch {}
            }
        }
    }
}

if ([string]::IsNullOrWhiteSpace($InviteId)) {
    Write-Host "FAIL: no pending invite found. Create one first (TASK-028 flow), then rerun."
    Write-Host "Status: $($invitesResp.status)"
    Write-Host "Body: $($invitesResp.body)"
    exit 1
}
Write-Host "PASS: using invite_id=$InviteId"
Write-Host ""

Write-Host "4) POST decline -> expect 200 ok:true + no-store"
$declineResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/private-leagues/invites/$InviteId/decline" -Headers @(
    "Authorization: Bearer $token"
)
$declineCc = Header-Value -Headers $declineResp.headers -Name "Cache-Control"
$declineOk = $false
$declineMetaEtagNull = $false
if ($declineResp.status -eq 200) {
    try {
        $obj = $declineResp.body | ConvertFrom-Json
        $declineOk = [bool]$obj.data.ok
        $declineMetaEtagNull = $null -eq $obj.meta.etag
    } catch {}
}
if ($declineResp.status -eq 200 -and $declineOk -and $declineMetaEtagNull -and $declineCc -eq "no-store") {
    Write-Host "PASS: decline returned expected success envelope."
} else {
    Write-Host "FAIL: decline did not meet expected response."
    Write-Host "Status: $($declineResp.status)"
    Write-Host "Cache-Control: $declineCc"
    Write-Host "Body: $($declineResp.body)"
    exit 1
}
Write-Host ""

Write-Host "5) GET invites -> declined invite should not be listed"
$invitesAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/private-leagues/invites" -Headers @(
    "Authorization: Bearer $token"
)
$stillPresent = $false
if ($invitesAfter.status -eq 200) {
    try {
        $obj = $invitesAfter.body | ConvertFrom-Json
        $rows = @($obj.data.items)
        $stillPresent = @($rows | Where-Object { [string]$_.invite_id -eq $InviteId }).Count -gt 0
    } catch {}
}
if ($invitesAfter.status -eq 200 -and -not $stillPresent) {
    Write-Host "PASS: declined invite is no longer in inbox list."
    exit 0
}

Write-Host "FAIL: declined invite still appears in inbox list."
Write-Host "Status: $($invitesAfter.status)"
Write-Host "Body: $($invitesAfter.body)"
exit 1
