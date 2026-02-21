param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "phase_d_auth_test@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456",
    [int]$InvalidLeagueId = 999999
)

function Invoke-ApiRequest {
    param(
        [string]$Method,
        [string]$Url,
        [hashtable]$Headers = @{},
        [object]$JsonBody = $null
    )

    $params = @{
        Method = $Method
        Uri = $Url
        Headers = $Headers
        UseBasicParsing = $true
    }
    if ($null -ne $JsonBody) {
        $params.ContentType = "application/json"
        $params.Body = ($JsonBody | ConvertTo-Json -Compress)
    }

    try {
        $resp = Invoke-WebRequest @params
        return @{
            status = [int]$resp.StatusCode
            headers = $resp.Headers
            body = [string]$resp.Content
        }
    } catch {
        $errResp = $_.Exception.Response
        $status = 0
        $respHeaders = @{}
        $body = ""

        if ($errResp) {
            $status = [int]$errResp.StatusCode
            $respHeaders = $errResp.Headers
            $stream = $errResp.GetResponseStream()
            if ($stream) {
                $reader = New-Object System.IO.StreamReader($stream)
                $body = $reader.ReadToEnd()
                $reader.Close()
            }
        }

        return @{
            status = $status
            headers = $respHeaders
            body = [string]$body
        }
    }
}

function Get-HeaderValue {
    param(
        [object]$Headers,
        [string]$Name
    )

    if ($null -eq $Headers) {
        return $null
    }

    $value = $Headers[$Name]
    if ($null -eq $value) {
        $value = $Headers[$Name.ToLower()]
    }
    if ($null -eq $value) {
        return $null
    }
    if ($value -is [array]) {
        return ($value -join ", ")
    }
    return [string]$value
}

Write-Host "Home smoke checks for TASK-002"
Write-Host "Base URL: $BaseUrl"
Write-Host ""
Write-Host "Prereq: web app + DB running, JWT_SECRET configured, and AUTH_FIXED_OTP=$Otp if using auto-register."
Write-Host ""

Write-Host "1) Login (with register/verify fallback)"
$login = Invoke-ApiRequest -Method POST -Url "$BaseUrl/auth/login" -JsonBody @{
    email = $Email
    password = $Password
}

$token = $null
if ($login.status -eq 200) {
    try {
        $loginObj = $login.body | ConvertFrom-Json
        $token = $loginObj.data.tokens.access_token
    } catch {}
}

if (-not $token) {
    Write-Host "Login did not return token. Attempting register + otp verify..."
    [void](Invoke-ApiRequest -Method POST -Url "$BaseUrl/auth/register" -JsonBody @{
        email = $Email
        password = $Password
        alias = "phase_d"
        lang = "en"
    })
    [void](Invoke-ApiRequest -Method POST -Url "$BaseUrl/auth/otp/verify" -JsonBody @{
        email = $Email
        otp = $Otp
        purpose = "register"
    })

    $login = Invoke-ApiRequest -Method POST -Url "$BaseUrl/auth/login" -JsonBody @{
        email = $Email
        password = $Password
    }

    if ($login.status -eq 200) {
        try {
            $loginObj = $login.body | ConvertFrom-Json
            $token = $loginObj.data.tokens.access_token
        } catch {}
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

$authHeaders = @{ Authorization = "Bearer $token" }

Write-Host "2) GET /home -> expect 200 + ETag + Cache-Control"
$home = Invoke-ApiRequest -Method GET -Url "$BaseUrl/home" -Headers $authHeaders
$etag = Get-HeaderValue -Headers $home.headers -Name "ETag"
$cacheControl = Get-HeaderValue -Headers $home.headers -Name "Cache-Control"
if ($home.status -eq 200 -and $etag -and $cacheControl -eq "private, must-revalidate") {
    Write-Host "PASS: /home 200 with required cache headers."
} else {
    Write-Host "FAIL: /home expected 200 + ETag + Cache-Control private,must-revalidate."
}
Write-Host "Status: $($home.status)"
Write-Host "ETag: $etag"
Write-Host "Cache-Control: $cacheControl"
Write-Host ""

Write-Host "3) GET /home with If-None-Match -> expect 304"
$headers304 = @{
    Authorization = "Bearer $token"
    "If-None-Match" = $etag
}
$resp304 = Invoke-ApiRequest -Method GET -Url "$BaseUrl/home" -Headers $headers304
if ($resp304.status -eq 304) {
    Write-Host "PASS: conditional request returned 304."
} else {
    Write-Host "FAIL: expected 304."
}
Write-Host "Status: $($resp304.status)"
Write-Host ""

$validLeagueId = $null
if ($home.status -eq 200) {
    try {
        $homeObj = $home.body | ConvertFrom-Json
        if ($homeObj.data.league_selector.leagues.Count -gt 0) {
            $validLeagueId = [int]$homeObj.data.league_selector.leagues[0].league_id
        }
    } catch {}
}

if (-not $validLeagueId) {
    Write-Host "SKIP: no valid league_id discovered from /home response."
    Write-Host ""
} else {
    Write-Host "4) GET /home?league_id=$validLeagueId -> expect 200 + league_context"
    $leagueResp = Invoke-ApiRequest -Method GET -Url "$BaseUrl/home?league_id=$validLeagueId" -Headers $authHeaders
    $hasLeagueContext = $false
    if ($leagueResp.status -eq 200) {
        try {
            $leagueObj = $leagueResp.body | ConvertFrom-Json
            $hasLeagueContext = $null -ne $leagueObj.data.league_context
        } catch {}
    }

    if ($leagueResp.status -eq 200 -and $hasLeagueContext) {
        Write-Host "PASS: league-scoped /home returned 200 with league_context."
    } else {
        Write-Host "FAIL: expected 200 with league_context."
    }
    Write-Host "Status: $($leagueResp.status)"
    Write-Host ""
}

Write-Host "5) GET /home?league_id=$InvalidLeagueId -> expect 403 or 404"
$invalidLeague = Invoke-ApiRequest -Method GET -Url "$BaseUrl/home?league_id=$InvalidLeagueId" -Headers $authHeaders
if ($invalidLeague.status -eq 403 -or $invalidLeague.status -eq 404) {
    Write-Host "PASS: invalid league returned $($invalidLeague.status)."
} else {
    Write-Host "FAIL: expected 403 or 404."
}
Write-Host "Status: $($invalidLeague.status)"
Write-Host ""

Write-Host "6) GET /home without token -> expect 401"
$noToken = Invoke-ApiRequest -Method GET -Url "$BaseUrl/home"
if ($noToken.status -eq 401) {
    Write-Host "PASS: missing token returned 401."
} else {
    Write-Host "FAIL: expected 401."
}
Write-Host "Status: $($noToken.status)"
