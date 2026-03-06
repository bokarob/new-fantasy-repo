<#
Master regression runner for Fantasy API smoke tests.

Runs smoke scripts in a stable order, captures exit codes, prints a summary,
and exits non-zero if any test failed (unless -ContinueOnFail is used).

Usage:
  powershell -ExecutionPolicy Bypass -File scripts\run-regression.ps1
  powershell -ExecutionPolicy Bypass -File scripts\run-regression.ps1 -ContinueOnFail
  powershell -ExecutionPolicy Bypass -File scripts\run-regression.ps1 -VerboseOutput
#>

[CmdletBinding()]
param(
  [string]$ScriptsDir = "scripts",
  [switch]$ContinueOnFail,
  [switch]$VerboseOutput
)

$ErrorActionPreference = "Stop"

$PreferredOrder = @(
  "auth-smoke.ps1"
  "home-smoke.ps1"
  "rules-smoke.ps1"
  "me-smoke.ps1"
  "me-patch-smoke.ps1"
  "me-teams-smoke.ps1"
  "contact-smoke.ps1"
  "team-builder-smoke.ps1"
  "team-create-smoke.ps1"
  "team-smoke.ps1"
  "captain-smoke.ps1"
  "substitute-smoke.ps1"
  "market-players-smoke.ps1"
  "transfer-quote-smoke.ps1"
  "transfer-confirm-smoke.ps1"
  "transfers-list-smoke.ps1"
  "fantasy-smoke.ps1"
  "notifications-smoke.ps1"
  "notification-read-smoke.ps1"
  "notification-readall-smoke.ps1"
  "matches-list-smoke.ps1"
  "match-detail-smoke.ps1"
  "table-smoke.ps1"
  "player-stats-smoke.ps1"
  "pl-list-smoke.ps1"
  "pl-create-smoke.ps1"
  "pl-detail-smoke.ps1"
  "pl-invite-search-smoke.ps1"
  "pl-invite-smoke.ps1"
  "pl-invites-get-smoke.ps1"
  "pl-invite-accept-smoke.ps1"
  "pl-invite-decline-smoke.ps1"
  "pl-remove-member-smoke.ps1"
  "pl-rename-smoke.ps1"
  "pl-delete-smoke.ps1"
  "pl-leave-smoke.ps1"
)

$ScriptArguments = @{
  "market-players-smoke.ps1"    = @("-Email", "seed.user2@example.com")
  "captain-smoke.ps1"           = @("-Email", "seed.user2@example.com")
  "substitute-smoke.ps1"        = @("-Email", "seed.user2@example.com")
  "transfer-quote-smoke.ps1"    = @("-Email", "seed.user2@example.com")
  "transfer-confirm-smoke.ps1"  = @("-Email", "seed.user2@example.com")
  "transfers-list-smoke.ps1"    = @("-Email", "seed.user2@example.com")
  "fantasy-smoke.ps1"           = @("-Email", "seed.user2@example.com")
  "notifications-smoke.ps1"     = @("-Email", "seed.user2@example.com")
  "notification-read-smoke.ps1" = @("-Email", "seed.user2@example.com")
  "notification-readall-smoke.ps1" = @("-Email", "seed.user2@example.com")
  "pl-list-smoke.ps1"           = @("-Email", "seed.user2@example.com")
  "pl-create-smoke.ps1"         = @("-Email", "seed.user2@example.com")
  "pl-detail-smoke.ps1"         = @("-Email", "seed.user2@example.com")
  "pl-invite-search-smoke.ps1"  = @("-Email", "seed.user2@example.com")
  "pl-invite-smoke.ps1"         = @("-Email", "seed.user2@example.com")
  "pl-invites-get-smoke.ps1"    = @("-Email", "seed.user3@example.com")
  "pl-invite-accept-smoke.ps1"  = @("-Email", "seed.user3@example.com")
  "pl-invite-decline-smoke.ps1" = @("-Email", "seed.user3@example.com")
  "pl-remove-member-smoke.ps1"  = @("-Email", "seed.user2@example.com")
  "pl-rename-smoke.ps1"         = @("-Email", "seed.user2@example.com")
  "pl-delete-smoke.ps1"         = @("-Email", "seed.user2@example.com")
  "pl-leave-smoke.ps1"          = @("-Email", "seed.user2@example.com")
}

function Get-ScriptPath([string]$dir, [string]$name) {
  $p = Join-Path $dir $name
  if (Test-Path $p) { return (Resolve-Path $p).Path }
  return $null
}

Write-Host "Running smoke tests from '$ScriptsDir'..."
Write-Host ""

$results = @()
$failed = 0

foreach ($name in $PreferredOrder) {
  $scriptPath = Get-ScriptPath -dir $ScriptsDir -name $name
  if (-not $scriptPath) {
    Write-Host "=== $name ==="
    Write-Host "SKIP: file not found"
    Write-Host ""
    $results += [pscustomobject]@{ Script=$name; Status="SKIP"; ExitCode=0; Seconds=0 }
    continue
  }

  Write-Host "=== $name ==="
  $sw = [System.Diagnostics.Stopwatch]::StartNew()

  try {
    $extraArgs = @()
    if ($ScriptArguments.ContainsKey($name)) {
      $extraArgs = @($ScriptArguments[$name])
    }
    $argString = (($extraArgs | ForEach-Object { "`"$_`"" }) -join " ")

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = (Get-Command powershell).Source
    $psi.Arguments = "-NoProfile -ExecutionPolicy Bypass -File `"$scriptPath`" $argString"
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError  = $true
    $psi.UseShellExecute = $false

    $proc = New-Object System.Diagnostics.Process
    $proc.StartInfo = $psi
    [void]$proc.Start()
    $stdout = $proc.StandardOutput.ReadToEnd()
    $stderr = $proc.StandardError.ReadToEnd()
    $proc.WaitForExit()
    $code = $proc.ExitCode

    if ($VerboseOutput) {
      if ($stdout) { Write-Host $stdout.TrimEnd() }
      if ($stderr) { Write-Host $stderr.TrimEnd() }
    } else {
      $outLines = ($stdout -split "`r?`n") | Where-Object { $_ -ne "" }
      if ($outLines.Count -gt 0) {
        $tail = $outLines | Select-Object -Last 8
        Write-Host ($tail -join "`n")
      }
      if ($stderr) {
        Write-Host "STDERR:"
        $errLines = ($stderr -split "`r?`n") | Where-Object { $_ -ne "" } | Select-Object -Last 8
        Write-Host ($errLines -join "`n")
      }
    }

    $sw.Stop()
    $status = if ($code -eq 0) { "PASS" } else { "FAIL" }
    if ($code -ne 0) { $failed++ }

    $results += [pscustomobject]@{
      Script   = $name
      Status   = $status
      ExitCode = $code
      Seconds  = [Math]::Round($sw.Elapsed.TotalSeconds, 2)
    }

    Write-Host ("Result: {0} (exit {1}) in {2}s" -f $status, $code, [Math]::Round($sw.Elapsed.TotalSeconds,2))
    Write-Host ""

    if (($code -ne 0) -and (-not $ContinueOnFail)) {
      Write-Host "Stopping on first failure. Re-run with -ContinueOnFail to see all failures."
      break
    }
  }
  catch {
    $sw.Stop()
    $failed++
    $results += [pscustomobject]@{ Script=$name; Status="ERROR"; ExitCode=1; Seconds=[Math]::Round($sw.Elapsed.TotalSeconds,2) }
    Write-Host "ERROR running ${name}: $($_.Exception.Message)"
    Write-Host ""
    if (-not $ContinueOnFail) { break }
  }
}

Write-Host "=== SUMMARY ==="
$results | Format-Table -AutoSize

if ($failed -gt 0) {
  Write-Host ""
  Write-Host "FAIL: $failed script(s) failed."
  exit 1
}

Write-Host ""
Write-Host "PASS: All smoke scripts succeeded."
exit 0
