#!/usr/bin/env sh
set -eu

cd "${APP_DIR:-/var/www/html}"

mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is not set. Provide it through .env or compose environment before starting the app." >&2
    exit 1
fi

if [ "${DB_CONNECTION:-sqlite}" != "sqlite" ] && [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for database ${DB_CONNECTION} at ${DB_HOST}:${DB_PORT:-}..."

    attempts=0
    until php -r '
        $driver = getenv("DB_CONNECTION") ?: "pgsql";
        $host = getenv("DB_HOST") ?: "";
        if ($host === "") {
            exit(0);
        }

        $port = getenv("DB_PORT") ?: ($driver === "pgsql" ? "5432" : "3306");
        $database = getenv("DB_DATABASE") ?: "";
        $username = getenv("DB_USERNAME") ?: "";
        $password = getenv("DB_PASSWORD") ?: "";

        $dsn = match ($driver) {
            "pgsql" => sprintf("pgsql:host=%s;port=%s;dbname=%s", $host, $port, $database),
            "mysql", "mariadb" => sprintf("mysql:host=%s;port=%s;dbname=%s", $host, $port, $database),
            default => "",
        };

        if ($dsn === "") {
            exit(0);
        }

        new PDO($dsn, $username, $password, [PDO::ATTR_TIMEOUT => 3]);
    '; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 30 ]; then
            echo "Database was not ready after ${attempts} attempts." >&2
            exit 1
        fi

        sleep 2
    done
fi

exec "$@"
