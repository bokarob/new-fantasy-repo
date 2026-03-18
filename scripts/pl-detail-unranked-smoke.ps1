param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "seed.user2@example.com",
    [string]$Password = "TestPass123!",
    [int]$LeagueId = 10,
    [int]$PrivateleagueId = 2002
)

function Invoke-CurlRequest {
    param(
        [string]$Method,
        [string]$Url,
        [string[]]$Headers = @(),
        [object]$JsonBody = $null
    )

    $headerFile = Join-Path $env:TEMP ("pl-detail-unranked-h-" + [guid]::NewGuid().ToString() + ".txt")
    $bodyFile = Join-Path $env:TEMP ("pl-detail-unranked-b-" + [guid]::NewGuid().ToString() + ".txt")
    $jsonFile = $null

    try {
        $args = @('--connect-timeout', '10', '--max-time', '30', '-s', '-o', $bodyFile, '-D', $headerFile, '-X', $Method, $Url)
        foreach ($h in $Headers) { $args += @('-H', $h) }
        if ($null -ne $JsonBody) {
            $jsonFile = Join-Path $env:TEMP ("pl-detail-unranked-j-" + [guid]::NewGuid().ToString() + ".json")
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

Write-Host "Private league detail fallback smoke checks for post-M4 follow-up"
Write-Host "Base URL: $BaseUrl"
Write-Host "League: $LeagueId"
Write-Host "Private league: $PrivateleagueId"
Write-Host ""

Write-Host "1) Login as deterministic seeded admin"
$login = Invoke-CurlRequest -Method POST -Url "$BaseUrl/auth/login" -Headers @("Content-Type: application/json") -JsonBody @{
    email = $Email
    password = $Password
}
$token = $null
if ($login.status -eq 200) {
    try { $token = (($login.body | ConvertFrom-Json).data.tokens.access_token) } catch {}
}
if (-not $token) {
    Write-Host "FAIL: could not acquire access token."
    Write-Host "Status: $($login.status)"
    Write-Host "Body: $($login.body)"
    exit 1
}
Write-Host "PASS: access token acquired."
Write-Host ""

Write-Host "2) GET detail for private league with confirmed member lacking rankings"
$detail = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/private-leagues/$PrivateleagueId" -Headers @("Authorization: Bearer $token")
$cc = Header-Value -Headers $detail.headers -Name "Cache-Control"
$etag = Header-Value -Headers $detail.headers -Name "ETag"
$detailOk = $false
if ($detail.status -eq 200) {
    try {
        $obj = $detail.body | ConvertFrom-Json
        $detailOk = (
            $cc -eq "private, must-revalidate" -and
            $obj.meta.etag -eq $etag -and
            $obj.data.membership.your_role -eq "admin" -and
            @($obj.data.standings.items).Count -eq 0 -and
            $null -eq $obj.data.standings.you
        )
    } catch {}
}
if ($detailOk) {
    Write-Host "PASS: detail returned 200 with an empty standings block instead of 409."
} else {
    Write-Host "FAIL: detail did not return the expected fallback payload."
}
Write-Host "Status: $($detail.status)"
Write-Host "ETag: $etag"
Write-Host "Body: $($detail.body)"
Write-Host ""
if (-not $detailOk) { exit 1 }

Write-Host "3) Revalidate unchanged detail -> expect 304"
$detail304 = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/private-leagues/$PrivateleagueId" -Headers @(
    "Authorization: Bearer $token",
    "If-None-Match: $etag"
)
if ($detail304.status -eq 304) {
    Write-Host "PASS: conditional request returned 304."
} else {
    Write-Host "FAIL: expected 304 Not Modified."
}
Write-Host "Status: $($detail304.status)"
Write-Host ""

Write-Host "4) No token -> expect 401 AUTH_REQUIRED"
$noToken = Invoke-CurlRequest -Method GET -Url "$BaseUrl/leagues/$LeagueId/private-leagues/$PrivateleagueId"
if ($noToken.status -eq 401 -and $noToken.body -match '"AUTH_REQUIRED"') {
    Write-Host "PASS: missing token returned AUTH_REQUIRED."
} else {
    Write-Host "FAIL: expected 401 AUTH_REQUIRED."
}
Write-Host "Status: $($noToken.status)"
Write-Host "Body: $($noToken.body)"
