<#
reset-db.ps1 — Reset user/competitor scoped data (keep reference data)

- Keeps: leagues, gameweeks, teams, players, playertrade, playerresult, matches, news, etc.
- Resets: profiles (except profile_id=1), competitor/roster/transfers/teamresult/teamranking,
          privateleague/privateleaguemembers, notification, auth_refresh_tokens (if exists),
          and seeds a deterministic regression dataset.

Default DB: fantasy_app, user: root, no password (XAMPP typical).

Usage:
  powershell -ExecutionPolicy Bypass -File scripts\reset-db.ps1
  powershell -ExecutionPolicy Bypass -File scripts\reset-db.ps1 -DbName fantasy_app -User root
  powershell -ExecutionPolicy Bypass -File scripts\reset-db.ps1 -Password "secret"
#>

[CmdletBinding()]
param(
  [string]$DbName = "fantasy_app",
  [string]$User = "root",
  [string]$Password = "",
  [string]$DbHost = "127.0.0.1",
  [int]$Port = 3306,
  [string]$SeedSqlPath = "database/seed/regression-user-reset.sql"
)

$ErrorActionPreference = "Stop"

function Find-MySqlExe {
  $cmd = Get-Command mysql.exe -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Source }

  $xampp = "C:\xampp\mysql\bin\mysql.exe"
  if (Test-Path $xampp) { return $xampp }

  throw "mysql.exe not found in PATH and not at $xampp. Install XAMPP MySQL or add mysql.exe to PATH."
}

$repoRoot = Split-Path -Parent $PSScriptRoot
$resolvedSeedSqlPath = $SeedSqlPath
if (-not [System.IO.Path]::IsPathRooted($resolvedSeedSqlPath)) {
  $resolvedSeedSqlPath = Join-Path $repoRoot $resolvedSeedSqlPath
}

if (-not (Test-Path $resolvedSeedSqlPath)) {
  throw "Seed SQL not found: $resolvedSeedSqlPath"
}

$mysqlExe = Find-MySqlExe

$args = @("--protocol=tcp", "-h", $DbHost, "-P", "$Port", "-u", $User, $DbName)
if ($Password -ne "") {
  $args = @("--protocol=tcp", "-h", $DbHost, "-P", "$Port", "-u", $User, "-p$Password", $DbName)
}

Write-Host "Resetting DB '$DbName' on ${DbHost}:$Port using $mysqlExe"
Write-Host "Applying seed: $resolvedSeedSqlPath"
Write-Host ""

# Feed SQL via stdin to avoid shell redirection issues.
$sql = Get-Content -Path $resolvedSeedSqlPath -Raw -Encoding UTF8
$stdout = $null
$stderr = $null

$psi = New-Object System.Diagnostics.ProcessStartInfo
$psi.FileName = $mysqlExe
$psi.Arguments = ($args -join " ")
$psi.RedirectStandardInput = $true
$psi.RedirectStandardOutput = $true
$psi.RedirectStandardError = $true
$psi.UseShellExecute = $false

$proc = New-Object System.Diagnostics.Process
$proc.StartInfo = $psi
[void]$proc.Start()

$proc.StandardInput.WriteLine($sql)
$proc.StandardInput.Close()

$stdout = $proc.StandardOutput.ReadToEnd()
$stderr = $proc.StandardError.ReadToEnd()
$proc.WaitForExit()

if ($stdout) { Write-Host $stdout.TrimEnd() }
if ($stderr) { Write-Host $stderr.TrimEnd() }

if ($proc.ExitCode -ne 0) {
  throw "mysql exited with code $($proc.ExitCode). See output above."
}

Write-Host ""
Write-Host "OK: user-scoped data reset + regression seed applied."
Write-Host "Seeded users:"
Write-Host "  seed.user2@example.com / TestPass123!"
Write-Host "  seed.user3@example.com / TestPass123!"
Write-Host "  seed.user4@example.com / TestPass123! (no competitor)"
Write-Host "  seed.user5@example.com / TestPass123! (confirmed competitor, no rankings)"
