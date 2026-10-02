#!/usr/bin/env bash
# Invoked over SSH by the production workflow. Works with the BSD utilities on Sakura rental servers.
set -euo pipefail

deploy_root=${1:?Deployment root required}
release_id=${2:?Release ID required}
php_bin=${3:-php}
[[ "$deploy_root" == /* && "$deploy_root" != / && "$deploy_root" != *..* ]]
[[ "$release_id" =~ ^[a-f0-9]{40}-[0-9]+-[0-9]+$ ]]

mkdir -p "$deploy_root/releases" "$deploy_root/shared"
# Other domains may serve the parent www directory. Expose only public there too.
if [[ ! -f "$deploy_root/.htaccess" ]]; then
  cat > "$deploy_root/.htaccess" <<'HTACCESS'
RewriteEngine On
RewriteRule ^(?!public(?:/|$)) - [F,L]
HTACCESS
fi
if [[ ! -e "$deploy_root/public" && ! -L "$deploy_root/public" ]]; then
  ln -s "$deploy_root/current/public" "$deploy_root/public"
fi
[[ -L "$deploy_root/public" && "$(readlink "$deploy_root/public")" == "$deploy_root/current/public" ]] || {
  echo 'The public path must point to current/public.' >&2
  exit 1
}
mkdir "$deploy_root/.deploy.lock" || { echo 'Another deployment is running.' >&2; exit 1; }
trap 'rmdir "$deploy_root/.deploy.lock"' EXIT

# PHP rename replaces the symlink atomically with BSD and GNU userlands alike.
replace_link() {
  "$php_bin" -r 'if (!rename($argv[1], $argv[2])) { exit(1); }' "$1" "$2"
}

test -f "$deploy_root/shared/.env"
command -v "$php_bin" >/dev/null
command -v curl >/dev/null
"$php_bin" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'
if [[ -e "$deploy_root/current" && ! -L "$deploy_root/current" ]]; then
  echo 'The current path must be a symlink, not an existing directory.' >&2
  exit 1
fi

release="$deploy_root/releases/$release_id"
archive="$deploy_root/incoming/$release_id.tar.gz"
test ! -e "$release"
mkdir "$release"
tar -xzf "$archive" -C "$release"
rm "$archive"
rm -rf "$release/storage"
mkdir -p "$deploy_root/shared/storage/app/public" "$deploy_root/shared/storage/app/private" \
  "$deploy_root/shared/storage/framework/cache/data" "$deploy_root/shared/storage/framework/sessions" \
  "$deploy_root/shared/storage/framework/views" "$deploy_root/shared/storage/logs"
ln -s "$deploy_root/shared/storage" "$release/storage"
ln -s "$deploy_root/shared/.env" "$release/.env"
mkdir -p "$release/bootstrap/cache"
cd "$release"

# Composer runs in CI; discover packages again using the server environment.
"$php_bin" artisan package:discover --no-interaction
"$php_bin" artisan config:cache --no-interaction
"$php_bin" artisan route:cache --no-interaction
"$php_bin" artisan view:cache --no-interaction
"$php_bin" artisan storage:link --no-interaction
"$php_bin" artisan migrate --force --no-interaction

previous=$(readlink "$deploy_root/current" || true)
activated=false
rollback() {
  status=$?
  if [[ "$activated" == true && -n "$previous" ]]; then
    ln -s "$previous" "$deploy_root/rollback-$release_id"
    replace_link "$deploy_root/rollback-$release_id" "$deploy_root/current"
    echo 'Restored the previous code release. Database migrations were not reverted.' >&2
  elif [[ "$activated" == true ]]; then
    rm "$deploy_root/current"
    echo 'Removed the failed first release from the public path.' >&2
  fi
  exit "$status"
}
trap rollback ERR
ln -s "$release" "$deploy_root/activate-$release_id"
replace_link "$deploy_root/activate-$release_id" "$deploy_root/current"
activated=true
"$php_bin" artisan queue:restart --no-interaction

# Check Laravel boot over public TLS. Database migrations were checked above.
curl --fail --silent --show-error --retry 5 --retry-all-errors --retry-delay 3 \
  --connect-timeout 10 --max-time 30 https://share-toku.shikode.com/up >/dev/null
trap - ERR
echo "Deployed $release_id to https://share-toku.shikode.com"
