#!/usr/bin/env bash
set -euo pipefail

message="${1:-chore(demo): add timestamped demo entry}"
timestamp="$(date '+%Y-%m-%d %H:%M:%S %z')"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Initialize Git first, for example: git init -b main"
  exit 1
fi

printf '[%s] %s\n' "$timestamp" "$message" >> commit-log.txt
git add commit-log.txt
GIT_AUTHOR_DATE="$timestamp" GIT_COMMITTER_DATE="$timestamp" git commit -m "$message"
