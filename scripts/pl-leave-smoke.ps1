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

    $headerFile = Join-Path $env:TEMP ("pl-leave-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-leave-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-leave-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Private league leave endpoint smoke checks for TASK-026"
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
    $runEmail = ("pl_leave_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Pick league_id and create private league"
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

$plName = "PLL " + [int][double]::Parse((Get-Date -UFormat %s))
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

Write-Host "3) Capture list ETag before leave"
$listBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Authorization: Bearer $token")
$etagBefore = Header-Value -Headers $listBefore.headers -Name "ETag"
if ($listBefore.status -eq 200 -and $etagBefore) {
    Write-Host "PASS: captured pre-leave ETag: $etagBefore"
} else {
    Write-Host "WARN: could not capture pre-leave ETag cleanly."
}
Write-Host ""

Write-Host "4) POST leave -> expect 200 ok:true + no-store + meta.etag null"
$leave = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/leave" -Headers @(
    "Authorization: Bearer $token"
)
$leaveCc = Header-Value -Headers $leave.headers -Name "Cache-Control"
$leaveOk = $false
if ($leave.status -eq 200) {
    try {
        $obj = $leave.body | ConvertFrom-Json
        $leaveOk = ($obj.data.ok -eq $true) -and ($null -eq $obj.meta.etag) -and ($leaveCc -eq "no-store")
    } catch {}
}
if ($leaveOk) {
    Write-Host "PASS: leave returned expected Category C response."
} else {
    Write-Host "FAIL: leave response/header mismatch."
}
Write-Host "Status: $($leave.status)"
Write-Host "Cache-Control: $leaveCc"
Write-Host "Body: $($leave.body)"
Write-Host ""

Write-Host "5) GET list -> does not include left league + ETag changes"
$listAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Authorization: Bearer $token")
$etagAfter = Header-Value -Headers $listAfter.headers -Name "ETag"
$containsLeft = $false
if ($listAfter.status -eq 200) {
    try {
        $obj = $listAfter.body | ConvertFrom-Json
        $items = @($obj.data.leagues)
        foreach ($it in $items) {
            if ([int]$it.privateleague_id -eq $privateleagueId) {
                $containsLeft = $true
                break
            }
        }
    } catch {}
}
$etagChanged = $false
if ($etagBefore -and $etagAfter) {
    $etagChanged = ($etagBefore -ne $etagAfter)
}
if ($listAfter.status -eq 200 -and -not $containsLeft -and ($etagChanged -or -not $etagBefore)) {
    Write-Host "PASS: left league removed from list and ETag moved."
} else {
    Write-Host "FAIL: list verification after leave failed."
}
Write-Host "Status: $($listAfter.status)"
Write-Host "ETag before: $etagBefore"
Write-Host "ETag after:  $etagAfter"
Write-Host "Body: $($listAfter.body)"
Write-Host ""

Write-Host "6) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/leave"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
