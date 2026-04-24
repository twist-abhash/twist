param(
    [string]$Message = "chore(demo): add timestamped demo entry"
)

$ErrorActionPreference = "Stop"
$timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss zzz")

git rev-parse --is-inside-work-tree *> $null
if ($LASTEXITCODE -ne 0) {
    throw "Initialize Git first, for example: git init -b main"
}

Add-Content -Path "commit-log.txt" -Value "[$timestamp] $Message"
git add commit-log.txt

$env:GIT_AUTHOR_DATE = $timestamp
$env:GIT_COMMITTER_DATE = $timestamp

try {
    git commit -m $Message
}
finally {
    Remove-Item Env:GIT_AUTHOR_DATE -ErrorAction SilentlyContinue
    Remove-Item Env:GIT_COMMITTER_DATE -ErrorAction SilentlyContinue
}
