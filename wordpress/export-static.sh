#!/usr/bin/env bash
# Exports the local WordPress as a static site for the GitHub Pages preview.
# Forms, search and anything else that needs PHP will not work in the export.
#
# Usage: wordpress/export-static.sh [output-dir] [base-url]

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$(mkdir -p "${1:-$ROOT/local/static}" && cd "${1:-$ROOT/local/static}" && pwd)"
BASE="${2:-https://cuchulain.github.io/moudrostpochopeni-preview}"
LOCAL="http://localhost:8080"

cd "$ROOT"

echo "==> Export do $OUT"
find "$OUT" -mindepth 1 -delete

# Every published page and post, so nothing depends on being linked from the menu.
docker compose run --rm --no-deps -T cli wp eval \
	'foreach ( get_posts( array( "post_type" => array( "page", "post" ), "numberposts" => -1 ) ) as $p ) echo get_permalink( $p ), "\n";' \
	2>/dev/null > "$OUT/.urls"
echo "    stránek: $(wc -l < "$OUT/.urls" | tr -d ' ')"

# Crawl from inside the WordPress container's network: Apache listens on :80 there,
# while every URL in the site points to :8080, so socat bridges the two.
docker run --rm --network container:moudrostpochopeni-wordpress-1 -v "$OUT:/out" -w /out alpine:3.22 sh -c '
	apk add --no-cache -q wget socat >/dev/null
	socat TCP-LISTEN:8080,fork,reuseaddr TCP:127.0.0.1:80 &
	sleep 1
	wget --mirror --page-requisites --convert-links --adjust-extension \
		--no-host-directories --restrict-file-names=windows -e robots=off \
		--domains localhost --no-verbose --input-file=.urls \
		--reject-regex "(wp-admin|wp-json|wp-login|xmlrpc|/feed|/comments/|\?s=|\?p=|replytocom|/page/[0-9]|/author/|oembed)" \
		http://localhost:8080/ 2>&1 | grep -vE " URL:.* -> " || true
'
rm -f "$OUT/.urls"

# Elementor and OceanWP load some scripts and styles at runtime, which wget cannot see.
echo "==> Kopíruji dynamicky načítané assety"
for dir in plugins/elementor/assets themes/oceanwp/assets plugins/complianz-gdpr/cookiebanner/js uploads/elementor/css uploads/complianz/css; do
	mkdir -p "$OUT/wp-content/$dir"
	# Only minified scripts and styles are ever loaded.
	rsync -a --include '*/' --include '*.min.js' --include '*.min.css' --exclude '*.js' --exclude '*.css' \
		--exclude '*.map' --exclude '*.scss' --exclude '*.php' "local/wp-content/$dir/" "$OUT/wp-content/$dir/"
done
rsync -a wordpress/oceanwp-child/assets/ "$OUT/wp-content/themes/oceanwp-child/assets/"

echo "==> Přepisuji adresy na $BASE"
# Passed through the environment so perl does not reinterpret the backslashes of the JSON-escaped form.
find "$OUT" -type f \( -name '*.html' -o -name '*.css' -o -name '*.js' \) -print0 \
	| FROM="$LOCAL" TO="$BASE" FROM_JSON="${LOCAL//\//\\/}" TO_JSON="${BASE//\//\\/}" \
		xargs -0 perl -pi -e 's#\Q$ENV{FROM}\E#$ENV{TO}#g; s#\Q$ENV{FROM_JSON}\E#$ENV{TO_JSON}#g'

echo "==> Převádím obrázky na WebP"
find "$OUT/wp-content/uploads" -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \) -print0 \
	| xargs -0 -P 8 -I{} sh -c 'cwebp -quiet -mt -q 82 -metadata none "$1" -o "${1%.*}.webp"' _ {}
# Point uploads references (plain and JSON-escaped) to the WebP versions; originals stay as a fallback.
find "$OUT" -type f \( -name '*.html' -o -name '*.css' -o -name '*.js' \) -print0 \
	| xargs -0 perl -pi -e 's#(wp-content(?:/|\\/)uploads(?:/|\\/)[^"\x27\s)<>]+?)\.(?:jpe?g|png)(?=[\s"\x27)?<>,]|$)#$1.webp#gi'

touch "$OUT/.nojekyll"
echo "==> Hotovo: $(du -sh "$OUT" | cut -f1), $(find "$OUT" -name '*.html' | wc -l | tr -d ' ') HTML stránek"
