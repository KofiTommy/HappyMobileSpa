param(
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)
$ErrorActionPreference = 'Stop'
$projectPath = Split-Path -Parent $PSScriptRoot
$workerPath = Join-Path $PSScriptRoot 'sms-worker.php'
if (!(Test-Path -LiteralPath $PhpPath)) { throw 'PHP executable not found.' }
if (!(Test-Path -LiteralPath $workerPath)) { throw 'SMS worker not found.' }
$phpWindowlessPath = Join-Path (Split-Path -Parent $PhpPath) 'php-win.exe'
if (Test-Path -LiteralPath $phpWindowlessPath) {
    $action = New-ScheduledTaskAction -Execute $phpWindowlessPath -Argument ('"' + $workerPath + '"') -WorkingDirectory $projectPath
} else {
    $runnerPath = Join-Path $PSScriptRoot 'run-sms-worker.ps1'
    $arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $runnerPath + '" -PhpPath "' + $PhpPath + '"'
    $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $arguments -WorkingDirectory $projectPath
}
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType S4U -RunLevel Limited
try {
    Register-ScheduledTask -TaskName 'HappyMobileSpa-SmsNotifications' -Description 'Send spa booking confirmations and 24-hour appointment reminders every minute.' -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null
    Write-Output 'SMS worker scheduled every minute, including while logged off. Keep the computer and database running; configure SMS in Admin > SMS notifications.'
} catch {
    $principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
    Register-ScheduledTask -TaskName 'HappyMobileSpa-SmsNotifications' -Description 'Send spa booking confirmations and reminders while the current user is logged on.' -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null
    Write-Output 'SMS worker scheduled every minute while you are logged on. For logged-off operation, run this installer from an Administrator PowerShell. Keep the computer and database running.'
}
