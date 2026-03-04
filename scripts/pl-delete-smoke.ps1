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

    $headerFile = Join-Path $env:TEMP ("pl-delete-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-delete-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-delete-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

function Acquire-Token {
    param(
        [string]$BaseUrl,
        [string]$Email,
        [string]$Password,
        [string]$Otp,
        [string]$Prefix
    )

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
        $runEmail = ($Prefix + "_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

    return @{
        token = $token
        email = $runEmail
        status = $login.status
        body = $login.body
    }
}

Write-Host "Private league delete endpoint smoke checks for TASK-034"
Write-Host "Base URL: $BaseUrl"
Write-Host ""

Write-Host "1) Login as admin user (register+verify fallback)"
$auth = Acquire-Token -BaseUrl $BaseUrl -Email $Email -Password $Password -Otp $Otp -Prefix "pl_delete_admin_smoke"
$adminToken = $auth.token
if (-not $adminToken) {
    Write-Host "FAIL: could not acquire admin access token."
    Write-Host "Status: $($auth.status)"
    Write-Host "Body: $($auth.body)"
    exit 1
}
Write-Host "PASS: admin token acquired."
Write-Host ""

Write-Host "2) Pick league_id from /home and create private league"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $adminToken")
$leagueId = $null
if ($homeResp.status -eq 200) {
    try {
        $homeObj = $homeResp.body | ConvertFrom-Json
        $ls = @($homeObj.data.league_selector.leagues)
        if ($ls.Count -gt 0) {
            $leagueId = [int]$ls[0].league_id
        }
    } catch {}
}
if (-not $leagueId) {
    Write-Host "FAIL: could not discover league_id from /home."
    Write-Host "Status: $($homeResp.status)"
    Write-Host "Body: $($homeResp.body)"
    exit 1
}

$plName = "PL Delete " + [int][double]::Parse((Get-Date -UFormat %s))
$create = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @(
    "Authorization: Bearer $adminToken",
    "Content-Type: application/json"
) -JsonBody @{
    leaguename = $plName
}
$privateleagueId = $null
if ($create.status -eq 200) {
    try { $privateleagueId = [int](($create.body | ConvertFrom-Json).data.privateleague_id) } catch {}
}
if (-not $privateleagueId) {
    Write-Host "FAIL: could not create private league for delete test."
    Write-Host "Status: $($create.status)"
    Write-Host "Body: $($create.body)"
    exit 1
}
Write-Host "PASS: using league_id=$leagueId privateleague_id=$privateleagueId"
Write-Host ""

Write-Host "3) POST delete (admin) -> expect 200 ok:true + no-store + meta.etag null"
$deleteResp = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/delete" -Headers @(
    "Authorization: Bearer $adminToken"
)
$deleteCc = Header-Value -Headers $deleteResp.headers -Name "Cache-Control"
$deleteOk = $false
if ($deleteResp.status -eq 200) {
    try {
        $obj = $deleteResp.body | ConvertFrom-Json
        $deleteOk = ($obj.data.ok -eq $true) -and ($null -eq $obj.meta.etag) -and ($deleteCc -eq "no-store")
    } catch {}
}
if ($deleteOk) {
    Write-Host "PASS: delete returned expected Category C response."
} else {
    Write-Host "FAIL: delete response/header mismatch."
}
Write-Host "Status: $($deleteResp.status)"
Write-Host "Cache-Control: $deleteCc"
Write-Host "Body: $($deleteResp.body)"
Write-Host ""

Write-Host "4) GET list -> deleted private league should be absent"
$listAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Authorization: Bearer $adminToken")
$existsInList = $false
if ($listAfter.status -eq 200) {
    try {
        $obj = $listAfter.body | ConvertFrom-Json
        $items = @($obj.data.leagues)
        foreach ($it in $items) {
            if ([int]$it.privateleague_id -eq $privateleagueId) {
                $existsInList = $true
                break
            }
        }
    } catch {}
}
if ($listAfter.status -eq 200 -and -not $existsInList) {
    Write-Host "PASS: private league is removed from list."
} else {
    Write-Host "FAIL: deleted private league still visible in list."
}
Write-Host "Status: $($listAfter.status)"
Write-Host "Body: $($listAfter.body)"
Write-Host ""

Write-Host "5) Recreate private league and verify NOT_ADMIN from second user"
$create2 = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @(
    "Authorization: Bearer $adminToken",
    "Content-Type: application/json"
) -JsonBody @{
    leaguename = ("PL Delete NotAdmin " + [int][double]::Parse((Get-Date -UFormat %s)))
}
$privateleagueId2 = $null
if ($create2.status -eq 200) {
    try { $privateleagueId2 = [int](($create2.body | ConvertFrom-Json).data.privateleague_id) } catch {}
}
if (-not $privateleagueId2) {
    Write-Host "FAIL: could not create second private league."
    Write-Host "Status: $($create2.status)"
    Write-Host "Body: $($create2.body)"
    exit 1
}

$otherAuth = Acquire-Token -BaseUrl $BaseUrl -Email ("pl_delete_other_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com") -Password $Password -Otp $Otp -Prefix "pl_delete_other_smoke"
$otherToken = $otherAuth.token
if (-not $otherToken) {
    Write-Host "FAIL: could not acquire second user token."
    Write-Host "Status: $($otherAuth.status)"
    Write-Host "Body: $($otherAuth.body)"
    exit 1
}

$notAdmin = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId2/delete" -Headers @(
    "Authorization: Bearer $otherToken"
)
if ($notAdmin.status -eq 403 -and $notAdmin.body -match '"NOT_ADMIN"') {
    Write-Host "PASS: non-admin delete rejected with 403 NOT_ADMIN."
} else {
    Write-Host "FAIL: expected 403 NOT_ADMIN for non-admin delete."
}
Write-Host "Status: $($notAdmin.status)"
Write-Host "Body: $($notAdmin.body)"
Write-Host ""

Write-Host "6) Missing token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId2/delete"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
