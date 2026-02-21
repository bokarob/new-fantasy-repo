param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "phase_d_auth_test@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456"
)

function Invoke-ApiRequest {
    param(
        [string]$Method,
        [string]$Url,
        [object]$JsonBody = $null
    )

    $params = @{
        Method = $Method
        Uri = $Url
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
            body = [string]$resp.Content
        }
    } catch {
        $errResp = $_.Exception.Response
        $status = 0
        $body = ""

        if ($errResp) {
            $status = [int]$errResp.StatusCode
            $stream = $errResp.GetResponseStream()
            if ($stream) {
                $reader = New-Object System.IO.StreamReader($stream)
                $body = $reader.ReadToEnd()
                $reader.Close()
            }
        }

        return @{
            status = $status
            body = [string]$body
        }
    }
}

Write-Host "Auth smoke checks for TASK-001"
Write-Host "Base URL: $BaseUrl"
Write-Host ""
Write-Host "Prereq: set AUTH_FIXED_OTP=$Otp for the web server process before running automated checks."
Write-Host ""

Write-Host "1) Register -> otp_sent"
$register = Invoke-ApiRequest -Method POST -Url "$BaseUrl/auth/register" -JsonBody @{
    email = $Email
    password = $Password
    alias = "phase_d"
    lang = "en"
}
Write-Host "Status: $($register.status)"
Write-Host $register.body
Write-Host ""

Write-Host "2) OTP verify (register) -> verified + tokens"
$verify = Invoke-ApiRequest -Method POST -Url "$BaseUrl/auth/otp/verify" -JsonBody @{
    email = $Email
    otp = $Otp
    purpose = "register"
}
Write-Host "Status: $($verify.status)"
Write-Host $verify.body
Write-Host ""

Write-Host "3) Login -> tokens"
$login = Invoke-ApiRequest -Method POST -Url "$BaseUrl/auth/login" -JsonBody @{
    email = $Email
    password = $Password
}
Write-Host "Status: $($login.status)"
Write-Host $login.body
Write-Host ""

Write-Host "4) Refresh rotation -> new refresh; old refresh invalid"
Write-Host "Manual step: extract refresh_token from login response and run:"
Write-Host "Invoke-WebRequest -Method POST -Uri `"$BaseUrl/auth/token/refresh`" -ContentType `"application/json`" -Body '{`"refresh_token`":`"<REFRESH>`"}'"
Write-Host "Then retry same request with old token and expect 401 AUTH_INVALID_TOKEN."
Write-Host ""

Write-Host "5) Logout -> logged_out; refresh afterwards fails"
Write-Host "Manual step:"
Write-Host "Invoke-WebRequest -Method POST -Uri `"$BaseUrl/auth/logout`" -ContentType `"application/json`" -Body '{`"refresh_token`":`"<LATEST_REFRESH>`"}'"
Write-Host "Then call /auth/token/refresh with the same token and expect 401 AUTH_INVALID_TOKEN."
Write-Host ""

Write-Host "6) OTP invalid increments attempts; attempt #6 returns OTP_RETRY_LIMIT"
Write-Host "Manual step: call /auth/otp/verify with wrong OTP 6 times for same email+purpose."
Write-Host "Expected: attempts 1-5 -> 422 OTP_INVALID, attempt 6 -> 429 OTP_RETRY_LIMIT."
Write-Host ""

Write-Host "7) OTP resend cooldown 60/120/300"
Write-Host "Manual step: call /auth/otp/send rapidly with same purpose."
Write-Host "Expected: first resend blocked by 60s, second by 120s, third+ by 300s."
