#!/usr/bin/env bash
# Exports the local WordPress, commits it to the wp-static branch and deploys it to GitHub Pages.
#
# Usage: wordpress/publish-preview.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BRANCH="wp-static"
WORKTREE="$ROOT/local/wp-static"

cd "$ROOT"

wordpress/export-static.sh "$ROOT/local/static"

echo "==> Připravuji větev $BRANCH"
if [[ ! -e "$WORKTREE/.git" ]]; then
	git fetch -q origin "$BRANCH" 2>/dev/null || true
	if git show-ref -q "refs/remotes/origin/$BRANCH"; then
		git worktree add -q -B "$BRANCH" "$WORKTREE" "origin/$BRANCH"
	else
		git worktree add -q --orphan -b "$BRANCH" "$WORKTREE"
	fi
fi

rsync -a --delete --exclude .git "$ROOT/local/static/" "$WORKTREE/"
git -C "$WORKTREE" add -A
if git -C "$WORKTREE" diff --cached --quiet; then
	echo "    beze změn"
else
	git -C "$WORKTREE" commit -q -m "chore(preview): update static export of the WordPress site"
	git -C "$WORKTREE" push -q origin "$BRANCH"
fi

echo "==> Spouštím nasazení"
gh workflow run deploy.yml --ref main
echo "Hotovo, nasazení běží: gh run watch"
