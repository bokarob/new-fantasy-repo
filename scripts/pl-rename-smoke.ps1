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

    $headerFile = Join-Path $env:TEMP ("pl-rename-smoke-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-rename-smoke-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) {
            $args += @('-H', $h)
        }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-rename-smoke-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Private league rename endpoint smoke checks for TASK-033"
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
    $runEmail = ("pl_rename_smoke_" + [int][double]::Parse((Get-Date -UFormat %s)) + "@example.com")
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
Write-Host "PASS: using league_id=$leagueId"
Write-Host ""

Write-Host "3) Setup: create private league for rename"
$oldName = "PL Rename Old " + [int][double]::Parse((Get-Date -UFormat %s))
$create = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    leaguename = $oldName
}
$privateleagueId = $null
if ($create.status -eq 200) {
    try { $privateleagueId = [int](($create.body | ConvertFrom-Json).data.privateleague_id) } catch {}
}
if (-not $privateleagueId) {
    Write-Host "FAIL: setup create failed."
    Write-Host "Status: $($create.status)"
    Write-Host "Body: $($create.body)"
    exit 1
}
Write-Host "PASS: created privateleague_id=$privateleagueId"
Write-Host ""

$newName = "PL Rename New " + [int][double]::Parse((Get-Date -UFormat %s))
Write-Host "4) POST rename -> expect 200 + ok:true + no-store + meta.etag null"
$rename = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/rename" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    leaguename = $newName
}
$renameCc = Header-Value -Headers $rename.headers -Name "Cache-Control"
$renameOk = $false
if ($rename.status -eq 200) {
    try {
        $obj = $rename.body | ConvertFrom-Json
        $renameOk = ($obj.data.ok -eq $true) -and ($null -eq $obj.meta.etag) -and ($renameCc -eq "no-store")
    } catch {}
}
if ($renameOk) {
    Write-Host "PASS: rename returned expected Category C response."
} else {
    Write-Host "FAIL: rename response/header mismatch."
}
Write-Host "Status: $($rename.status)"
Write-Host "Cache-Control: $renameCc"
Write-Host "Body: $($rename.body)"
Write-Host ""

Write-Host "5) GET list -> renamed league is visible"
$listAfter = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$leagueId/private-leagues" -Headers @("Authorization: Bearer $token")
$renamedInList = $false
if ($listAfter.status -eq 200) {
    try {
        $obj = $listAfter.body | ConvertFrom-Json
        $items = @($obj.data.leagues)
        foreach ($it in $items) {
            if ([int]$it.privateleague_id -eq $privateleagueId -and [string]$it.leaguename -eq $newName) {
                $renamedInList = $true
                break
            }
        }
    } catch {}
}
if ($listAfter.status -eq 200 -and $renamedInList) {
    Write-Host "PASS: list reflects renamed league."
} else {
    Write-Host "FAIL: list does not reflect renamed league."
}
Write-Host "Status: $($listAfter.status)"
Write-Host "Body: $($listAfter.body)"
Write-Host ""

Write-Host "6) Invalid name -> expect 422 VALIDATION_ERROR"
$badName = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/rename" -Headers @(
    "Authorization: Bearer $token",
    "Content-Type: application/json"
) -JsonBody @{
    leaguename = "a"
}
if ($badName.status -eq 422 -and $badName.body -match '"VALIDATION_ERROR"') {
    Write-Host "PASS: invalid name rejected with 422 VALIDATION_ERROR."
} else {
    Write-Host "FAIL: expected 422 VALIDATION_ERROR for invalid name."
}
Write-Host "Status: $($badName.status)"
Write-Host "Body: $($badName.body)"
Write-Host ""

Write-Host "7) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method POST -Url "$BaseUrl/leagues/$leagueId/private-leagues/$privateleagueId/rename" -Headers @("Content-Type: application/json") -JsonBody @{
    leaguename = "NoTokenRename"
}
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned 401 AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
