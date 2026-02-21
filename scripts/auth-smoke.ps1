param(
    [string]$BaseUrl = "http://localhost/new-fantasy-repo",
    [string]$Email = "phase_d_auth_test@example.com",
    [string]$Password = "TestPass123!",
    [string]$Otp = "123456"
)

Write-Host "Auth smoke checks for TASK-001"
Write-Host "Base URL: $BaseUrl"
Write-Host ""
Write-Host "Prereq: set AUTH_FIXED_OTP=$Otp for the web server process before running automated checks."
Write-Host ""

Write-Host "1) Register -> otp_sent"
curl.exe -s -X POST "$BaseUrl/auth/register" `
  -H "Content-Type: application/json" `
  -d "{`"email`":`"$Email`",`"password`":`"$Password`",`"alias`":`"phase_d`",`"lang`":`"en`"}"
Write-Host ""
Write-Host ""

Write-Host "2) OTP verify (register) -> verified + tokens"
$verifyResp = curl.exe -s -X POST "$BaseUrl/auth/otp/verify" `
  -H "Content-Type: application/json" `
  -d "{`"email`":`"$Email`",`"otp`":`"$Otp`",`"purpose`":`"register`"}"
Write-Host $verifyResp
Write-Host ""

Write-Host "3) Login -> tokens"
$loginResp = curl.exe -s -X POST "$BaseUrl/auth/login" `
  -H "Content-Type: application/json" `
  -d "{`"email`":`"$Email`",`"password`":`"$Password`"}"
Write-Host $loginResp
Write-Host ""

Write-Host "4) Refresh rotation -> new refresh; old refresh invalid"
Write-Host "Manual step: extract refresh_token from login response and run:"
Write-Host "curl -s -X POST `"$BaseUrl/auth/token/refresh`" -H `"Content-Type: application/json`" -d '{`"refresh_token`":`"<REFRESH>`"}'"
Write-Host "Then retry same request with old token and expect 401 AUTH_INVALID_TOKEN."
Write-Host ""

Write-Host "5) Logout -> logged_out; refresh afterwards fails"
Write-Host "Manual step:"
Write-Host "curl -s -X POST `"$BaseUrl/auth/logout`" -H `"Content-Type: application/json`" -d '{`"refresh_token`":`"<LATEST_REFRESH>`"}'"
Write-Host "Then call /auth/token/refresh with the same token and expect 401 AUTH_INVALID_TOKEN."
Write-Host ""

Write-Host "6) OTP invalid increments attempts; attempt #6 returns OTP_RETRY_LIMIT"
Write-Host "Manual step: call /auth/otp/verify with wrong OTP 6 times for same email+purpose."
Write-Host "Expected: attempts 1-5 -> 422 OTP_INVALID, attempt 6 -> 429 OTP_RETRY_LIMIT."
Write-Host ""

Write-Host "7) OTP resend cooldown 60/120/300"
Write-Host "Manual step: call /auth/otp/send rapidly with same purpose."
Write-Host "Expected: first resend blocked by 60s, second by 120s, third+ by 300s."
