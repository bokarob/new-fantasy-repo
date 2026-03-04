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

    $headerFile = Join-Path $env:TEMP ("pl-invite-accept-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-invite-accept-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }

        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-invite-accept-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Private league invite accept smoke checks for TASK-030"
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
    $runEmail = ("pl_invite_accept_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

$privateleagueId = 0
if ([string]::IsNullOrWhiteSpace($InviteId)) {
    Write-Host "3) Find a pending invite via GET /leagues/$LeagueId/private-leagues/invites"
    $invitesResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/private-leagues/invites" -Headers @("Authorization: Bearer $token")
    if ($invitesResp.status -eq 200) {
        try {
            $obj = $invitesResp.body | ConvertFrom-Json
            $pending = @($obj.data.items | Where-Object { [string]$_.status -eq "pending" })
            if ($pending.Count -gt 0) {
                $InviteId = [string]$pending[0].invite_id
                $privateleagueId = [int]$pending[0].privateleague_id
            }
        } catch {}
    }
    if ([string]::IsNullOrWhiteSpace($InviteId)) {
        Write-Host "FAIL: no pending invite found. Create one first (TASK-028 flow), then rerun."
        Write-Host "Status: $($invitesResp.status)"
        Write-Host "Body: $($invitesResp.body)"
        exit 1
    }
} else {
    $m = [regex]::Match($InviteId, '^pl([1-9][0-9]*)-c([1-9][0-9]*)$')
    if ($m.Success) {
        $privateleagueId = [int]$m.Groups[1].Value
    }
}
Write-Host "PASS: using invite_id=$InviteId"
Write-Host ""

Write-Host "4) POST /leagues/$LeagueId/private-leagues/invites/$InviteId/accept -> expect 200 ok:true + no-store"
$acceptResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/private-leagues/invites/$InviteId/accept" -Headers @(
    "Authorization: Bearer $token"
)
$acceptCc = Header-Value -Headers $acceptResp.headers -Name "Cache-Control"
$acceptOk = $false
$acceptMetaEtagNull = $false
if ($acceptResp.status -eq 200) {
    try {
        $obj = $acceptResp.body | ConvertFrom-Json
        $acceptOk = [bool]$obj.data.ok
        $acceptMetaEtagNull = $null -eq $obj.meta.etag
    } catch {}
}
if ($acceptResp.status -eq 200 -and $acceptOk -and $acceptMetaEtagNull -and $acceptCc -eq "no-store") {
    Write-Host "PASS: accept returned expected success envelope."
} else {
    Write-Host "FAIL: accept did not meet expected response."
    Write-Host "Status: $($acceptResp.status)"
    Write-Host "Cache-Control: $acceptCc"
    Write-Host "Body: $($acceptResp.body)"
    exit 1
}
Write-Host ""

Write-Host "5) GET /leagues/$LeagueId/private-leagues -> accepted league appears in leagues[]"
$listResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/private-leagues" -Headers @(
    "Authorization: Bearer $token"
)
$foundLeague = $false
if ($listResp.status -eq 200) {
    try {
        $obj = $listResp.body | ConvertFrom-Json
        $leagueRows = @($obj.data.leagues)
        if ($privateleagueId -gt 0) {
            $foundLeague = @($leagueRows | Where-Object { [int]$_.privateleague_id -eq $privateleagueId }).Count -gt 0
        } else {
            $m = [regex]::Match($InviteId, '^pl([1-9][0-9]*)-c([1-9][0-9]*)$')
            if ($m.Success) {
                $pid = [int]$m.Groups[1].Value
                $foundLeague = @($leagueRows | Where-Object { [int]$_.privateleague_id -eq $pid }).Count -gt 0
            }
        }
    } catch {}
}
if ($listResp.status -eq 200 -and $foundLeague) {
    Write-Host "PASS: accepted private league is present in leagues list."
} else {
    Write-Host "FAIL: accepted private league missing from leagues list."
    Write-Host "Status: $($listResp.status)"
    Write-Host "Body: $($listResp.body)"
    exit 1
}
Write-Host ""

Write-Host "6) Re-accept same invite -> expect 409 INVITE_NOT_PENDING"
$acceptAgainResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$LeagueId/private-leagues/invites/$InviteId/accept" -Headers @(
    "Authorization: Bearer $token"
)
$againCode = ""
if ($acceptAgainResp.body) {
    try { $againCode = [string](($acceptAgainResp.body | ConvertFrom-Json).error.code) } catch {}
}
if ($acceptAgainResp.status -eq 409 -and $againCode -eq "INVITE_NOT_PENDING") {
    Write-Host "PASS: second accept rejected with INVITE_NOT_PENDING."
    exit 0
}

Write-Host "FAIL: expected 409 INVITE_NOT_PENDING on second accept."
Write-Host "Status: $($acceptAgainResp.status)"
Write-Host "Body: $($acceptAgainResp.body)"
exit 1
