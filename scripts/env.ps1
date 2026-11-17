$clinicTools = Join-Path $env:LOCALAPPDATA 'ClinicTools'
$clinicPhp = Join-Path $clinicTools 'php'
$clinicGit = Get-ChildItem "$env:LOCALAPPDATA\GitHubDesktop\app-*\resources\app\git\cmd\git.exe" -ErrorAction SilentlyContinue | Select-Object -Last 1
$env:PATH = "$clinicTools;$clinicPhp;$env:PATH"
if ($clinicGit) { $env:PATH = "$($clinicGit.DirectoryName);$env:PATH" }
