#!/usr/bin/env bash
set -euo pipefail
# No production credentials or storage are mounted. Both containers are temporary.
test_run="stellar-claim-${BUILD_BUILDID:-local}-$$"
test_db_password="$(openssl rand -hex 24)"
cleanup() {
  docker rm -f "${test_run}-php" "${test_run}-mysql" >/dev/null 2>&1 || true
  docker network rm "$test_run" >/dev/null 2>&1 || true
}
trap cleanup EXIT
docker network create --internal "$test_run" >/dev/null
docker run -d --name "${test_run}-mysql" --network "$test_run" --network-alias mysql \
  --tmpfs /var/lib/mysql:rw,nosuid --tmpfs /tmp:rw,nosuid \
  -e MYSQL_RANDOM_ROOT_PASSWORD=yes -e MYSQL_DATABASE=stellar_claim_test \
  -e MYSQL_USER=stellar_test -e "MYSQL_PASSWORD=$test_db_password" \
  mysql:8.4@sha256:0f51e63e537a1f176423fa03ef559aabe6fa4372a53160a44bc7e75da7d15364 >/dev/null
# Prepare PHP extensions before joining the isolated network; the app is not mounted yet.
docker run -d --name "${test_run}-php" \
  php:8.2-cli-bookworm@sha256:0d6ec82fde85307fd9c672527017fccf9bac55f254756f035e542f8c566b63c9 sleep 900 >/dev/null
docker exec "${test_run}-php" sh -ec 'apt-get update -qq; apt-get install -y -qq libonig-dev libxml2-dev libsqlite3-dev >/dev/null; docker-php-ext-install -j2 mbstring dom xml xmlwriter pdo_mysql pdo_sqlite pcntl >/dev/null'
docker network disconnect bridge "${test_run}-php"
docker network connect "$test_run" "${test_run}-php"
docker exec "${test_run}-php" mkdir /app
# Use the CI checkout including its locked vendor tree, excluding all local credentials.
tar --exclude=.env --exclude=.git --exclude=storage/logs -cf - . | docker exec -i "${test_run}-php" tar -xf - -C /app
docker exec -e "DB_PASSWORD=$test_db_password" "${test_run}-php" php -r '
for ($n=0; $n<60; $n++) { try { new PDO("mysql:host=mysql;dbname=stellar_claim_test", "stellar_test", getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { sleep(1); } } exit(1);'
docker exec -w /app -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  -e APPINSIGHTS_ENABLED=false "${test_run}-php" php vendor/bin/phpunit --filter 'AccountClaim|FactoryVpnHandover'
docker exec -w /app -e "DB_PASSWORD=$test_db_password" \
  -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  "${test_run}-php" php vendor/bin/phpunit -c phpunit-mysql.xml
