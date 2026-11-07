param([string]$PhpPath = 'C:\xampp\php\php.exe')
$workerPath = Join-Path $PSScriptRoot 'sms-worker.php'
$process = Start-Process -FilePath $PhpPath -ArgumentList ('"' + $workerPath + '"') -WorkingDirectory (Split-Path -Parent $PSScriptRoot) -WindowStyle Hidden -Wait -PassThru
exit $process.ExitCode
