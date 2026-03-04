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

    $headerFile = Join-Path $env:TEMP ("pl-create-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-create-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-create-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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
    if ($m.Success) { return $m.Groups[1].Value.Trim() }
    return $null
}

Write-Host "Private league create endpoint smoke checks for TASK-024"
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
    $runEmail = ("pl_create_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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

Write-Host "2) Pick league_id from /home"
$homeResp = Invoke-CurlRequest -Method GET -Url "$BaseUrl/home" -Headers @("Authorization: Bearer $token")
$leagueId = $null
if ($homeResp.status -eq 200) {
    try {
        $homeObj = $homeResp.body | ConvertFrom-Json
        $ls = @($homeObj.data.league_selector.leagues)
        if ($ls.Count -gt 0) { $leagueId = [int]$ls[0].league_id }
    } catch {}
}
if (-not $leagueId) {
    Write-Host "FAIL: could not discover league_id from /home."
    Write-Host "Status: $($homeResp.status)"
    Write-Host "Body: $($homeResp.body)"
    exit 1
}
Write-Host "PASS: using league_id=$leagueId"
Write-Host ""

Write-Host "3) Capture list ETag before create"
$listBefore = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Authorization: Bearer $token")
$etagBefore = Header-Value -Headers $listBefore.headers -Name "ETag"
if ($listBefore.status -eq 200 -and $etagBefore) {
    Write-Host "PASS: captured pre-create ETag: $etagBefore"
} else {
    Write-Host "WARN: could not capture pre-create ETag cleanly."
}
Write-Host ""

$name = "PL Smoke " + [int][double]::Parse((Get-Date -UFormat %s))
Write-Host "4) POST create unique name -> expect 200 + no-store + meta.etag=null"
$create = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    leaguename = $name
}
$createCc = Header-Value -Headers $create.headers -Name "Cache-Control"
$createdId = 0
$createOk = $false
if ($create.status -eq 200) {
    try {
        $obj = $create.body | ConvertFrom-Json
        $createdId = [int]$obj.data.privateleague_id
        $createOk = ($obj.data.ok -eq $true) -and ($null -eq $obj.meta.etag) -and ($createdId -gt 0) -and ($createCc -eq "no-store")
    } catch {}
}
if ($createOk) {
    Write-Host "PASS: create returned expected Category C envelope."
} else {
    Write-Host "FAIL: create response/header mismatch."
}
Write-Host "Status: $($create.status)"
Write-Host "Cache-Control: $createCc"
Write-Host "Body: $($create.body)"
Write-Host ""

Write-Host "5) GET list -> contains created league and ETag changes"
$listAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Authorization: Bearer $token")
$etagAfter = Header-Value -Headers $listAfter.headers -Name "ETag"
$containsCreated = $false
if ($listAfter.status -eq 200) {
    try {
        $obj = $listAfter.body | ConvertFrom-Json
        $items = @($obj.data.leagues)
        foreach ($it in $items) {
            if ([int]$it.privateleague_id -eq $createdId -or [string]$it.leaguename -eq $name) {
                $containsCreated = $true
                break
            }
        }
    } catch {}
}
$etagChanged = $false
if ($etagBefore -and $etagAfter) {
    $etagChanged = ($etagBefore -ne $etagAfter)
}
if ($listAfter.status -eq 200 -and $containsCreated -and ($etagChanged -or -not $etagBefore)) {
    Write-Host "PASS: list includes new league and ETag moved."
} else {
    Write-Host "FAIL: list verification after create failed."
}
Write-Host "Status: $($listAfter.status)"
Write-Host "ETag before: $etagBefore"
Write-Host "ETag after:  $etagAfter"
Write-Host "Body: $($listAfter.body)"
Write-Host ""

Write-Host "6) POST create same name again (optional behavior)"
$dup = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    leaguename = $name
}
if ($dup.status -eq 409 -and $dup.body -match '"NAME_ALREADY_USED"') {
    Write-Host "PASS: duplicate name rejected with 409 NAME_ALREADY_USED."
} elseif ($dup.status -eq 200) {
    Write-Host "PASS: duplicate name accepted (allowed optional behavior)."
} else {
    Write-Host "FAIL: unexpected duplicate-name behavior."
}
Write-Host "Status: $($dup.status)"
Write-Host "Body: $($dup.body)"
Write-Host ""

Write-Host "7) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Content-Type: application/json") -JsonBody @{
    leaguename = "NoTokenLeague"
}
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
