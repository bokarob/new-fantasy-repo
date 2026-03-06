<#
run-all.ps1 — Reset DB then run full regression

Usage:
  powershell -ExecutionPolicy Bypass -File scripts\run-all.ps1
  powershell -ExecutionPolicy Bypass -File scripts\run-all.ps1 -ContinueOnFail
#>

[CmdletBinding()]
param(
  [switch]$ContinueOnFail
)

$ErrorActionPreference = "Stop"
$scriptRoot = $PSScriptRoot

function Invoke-Step {
  param(
    [string]$Label,
    [string]$ScriptPath,
    [string[]]$Arguments = @()
  )

  Write-Host "=== $Label ==="
  & powershell -NoProfile -ExecutionPolicy Bypass -File $ScriptPath @Arguments
  $exitCode = $LASTEXITCODE
  if ($exitCode -ne 0) {
    throw "$Label failed with exit code $exitCode."
  }
}

Invoke-Step -Label "RESET DB" -ScriptPath (Join-Path $scriptRoot "reset-db.ps1")

Write-Host ""
if ($ContinueOnFail) {
  Invoke-Step -Label "RUN REGRESSION" -ScriptPath (Join-Path $scriptRoot "run-regression.ps1") -Arguments @("-ContinueOnFail")
} else {
  Invoke-Step -Label "RUN REGRESSION" -ScriptPath (Join-Path $scriptRoot "run-regression.ps1")
}
