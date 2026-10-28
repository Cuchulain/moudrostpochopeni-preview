#!/usr/bin/env bash
# Imports an UpdraftPlus backup from local/backup/ into the local Docker WordPress.
# Accepts both the original .zip/.gz files and folders that macOS already unpacked.
#
# Usage: wordpress/import-backup.sh [--force]
#   --force  replace an existing local/wp-content

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP="$ROOT/local/backup"
CONTENT="$ROOT/local/wp-content"
LIVE_DOMAIN="moudrostpochopeni.cz"
LOCAL_URL="http://localhost:8080"

cd "$ROOT"

die() { echo "Chyba: $*" >&2; exit 1; }
step() { echo; echo "==> $*"; }

[[ -d "$BACKUP" ]] || die "chybí složka local/backup/ se zálohou"

if [[ -d "$CONTENT" && -n "$(ls -A "$CONTENT" 2>/dev/null)" ]]; then
	[[ "${1:-}" == "--force" ]] || die "local/wp-content už existuje, pro přepsání spusť s --force"
	rm -rf "$CONTENT"
fi
mkdir -p "$CONTENT"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# --- Database ---------------------------------------------------------------

step "Hledám databázi"
DB_SRC="$(find "$BACKUP" -maxdepth 2 -type f \( -name '*-db.gz' -o -name '*-db' -o -name '*-db.sql' -o -name '*.sql' -o -name '*.sql.gz' \) -print0 | xargs -0 ls -1S | head -n 1)"
[[ -n "$DB_SRC" ]] || die "v local/backup/ není soubor databáze (*-db.gz)"
echo "    $DB_SRC"

DUMP="$WORK/db.sql"
if gzip -t "$DB_SRC" 2>/dev/null; then
	gunzip -c "$DB_SRC" > "$DUMP"
else
	cp "$DB_SRC" "$DUMP"
fi

grep -q 'CREATE TABLE' "$DUMP" \
	|| die "dump databáze neobsahuje žádné tabulky (nejspíš poškozené stažení, stáhni ho znovu bez automatického rozbalení)"

PREFIX="$(grep -m 1 -oE 'CREATE TABLE `[A-Za-z0-9_]+options`' "$DUMP" | sed -E 's/CREATE TABLE `(.*)options`/\1/')"
[[ -n "$PREFIX" ]] || die "nepodařilo se zjistit prefix tabulek z dumpu"
echo "    prefix tabulek: $PREFIX"
echo "WORDPRESS_TABLE_PREFIX=$PREFIX" > "$ROOT/local/wordpress.env"

# --- Files ------------------------------------------------------------------

# Copies one wp-content part (plugins, themes, uploads) from a zip or an unpacked folder.
import_part() {
	local part="$1" found=0 src

	while IFS= read -r src; do
		echo "    $part: $(basename "$src")"
		unzip -q -o "$src" -d "$CONTENT"
		found=1
	done < <(find "$BACKUP" -maxdepth 1 -type f -name "*-${part}*.zip" ! -name "*mu-${part}*" | sort)
	((found)) && return

	# macOS Archive Utility either unpacks to the bare folder name ("plugins", "plugins 2")
	# or to a folder named after the archive ("backup_..._-plugins").
	while IFS= read -r src; do
		[[ -d "$src/$part" ]] && src="$src/$part"
		echo "    $part: $(basename "$src")/"
		mkdir -p "$CONTENT/$part"
		rsync -a "$src/" "$CONTENT/$part/"
		found=1
	done < <(find "$BACKUP" -mindepth 1 -maxdepth 1 -type d \( -name "$part" -o -name "$part [0-9]*" -o -name "*-${part}*" \) ! -name "*mu-${part}*" | sort)

	((found)) || echo "    VAROVÁNÍ: část '$part' nenalezena"
}

step "Kopíruji soubory do local/wp-content"
for part in plugins themes uploads; do
	import_part "$part"
done

others_found=0
while IFS= read -r src; do
	echo "    others: $(basename "$src")"
	if [[ -f "$src" ]]; then
		unzip -q -o "$src" -d "$CONTENT" -x 'upgrade-temp-backup/*'
	else
		rsync -a --exclude upgrade-temp-backup "$src/" "$CONTENT/"
	fi
	others_found=1
done < <(find "$BACKUP" -mindepth 1 -maxdepth 1 -name '*-others*' | sort)
((others_found)) || echo "    (část 'others' nenalezena – obvykle jen jazykové soubory, nevadí)"

# mu-plugins come from wordpress/mu-plugins (see compose.yaml); the live site's only one
# is the WEDOS cache drop-in, which would serve stale pages locally anyway.

[[ -d "$CONTENT/themes/oceanwp" ]] || echo "    VAROVÁNÍ: chybí šablona OceanWP, zkontroluj zálohu šablon"

# --- Containers and database import -----------------------------------------

step "Spouštím Docker"
docker compose up -d --wait db
docker compose up -d --force-recreate wordpress
until docker compose exec -T wordpress test -f /var/www/html/wp-config.php; do sleep 1; done

step "Importuji databázi"
docker compose exec -T db mariadb -uroot -proot -e "
	DROP DATABASE IF EXISTS wordpress;
	CREATE DATABASE wordpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
	GRANT ALL ON wordpress.* TO 'wordpress'@'%';"
# MySQL 8 collations are not available in MariaDB.
sed -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_520_ci/g' "$DUMP" \
	| docker compose exec -T db mariadb -uroot -proot wordpress

wp() { docker compose run --rm --no-deps -T cli wp "$@"; }

step "Přepisuji adresy na $LOCAL_URL"
for from in "https://www.$LIVE_DOMAIN" "http://www.$LIVE_DOMAIN" "https://$LIVE_DOMAIN" "http://$LIVE_DOMAIN"; do
	wp search-replace "$from" "$LOCAL_URL" --all-tables-with-prefix --skip-columns=guid --report-changed-only
	# Elementor stores its data as JSON with escaped slashes.
	wp search-replace "${from//\//\\/}" "${LOCAL_URL//\//\\/}" --all-tables-with-prefix --skip-columns=guid --report-changed-only
done

step "Vypínám pluginy, které lokálně překážejí"
for plugin in updraftplus litespeed-cache wp-rocket w3-total-cache wp-super-cache wp-fastest-cache \
	really-simple-ssl wordfence sucuri-scanner jetpack wgpwpp two-factor; do
	if wp plugin is-active "$plugin" 2>/dev/null; then
		wp plugin deactivate "$plugin"
	fi
done

step "Úklid"
wp cache flush || true
wp elementor flush-css || true
wp rewrite flush || true

echo
echo "Hotovo: $LOCAL_URL (přihlášení stejné jako na ostrém webu, $LOCAL_URL/wp-admin)"
