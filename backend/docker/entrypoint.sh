#!/bin/sh
set -e

i=0
until php -r '
    try {
        new PDO(
            "pgsql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: "5432").";dbname=".getenv("DB_DATABASE"),
            getenv("DB_USERNAME") ?: "",
            getenv("DB_PASSWORD") ?: ""
        );
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage()."\n");
        exit(1);
    }
'; do
    i=$((i + 1))
    if [ "$i" -gt 30 ]; then
        echo "postgres is not ready" >&2
        exit 1
    fi
    sleep 1
done

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

if [ "$1" = "serve" ]; then
    php artisan migrate --force
    php artisan db:seed --force
fi

if [ "$1" = "queue" ] || [ "$1" = "schedule" ]; then
    i=0
    until php -r '
        $pdo = new PDO(
            "pgsql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: "5432").";dbname=".getenv("DB_DATABASE"),
            getenv("DB_USERNAME") ?: "",
            getenv("DB_PASSWORD") ?: ""
        );
        $pdo->query("select 1 from deals limit 1");
    '; do
        i=$((i + 1))
        if [ "$i" -gt 60 ]; then
            echo "schema is not ready" >&2
            exit 1
        fi
        sleep 1
    done
fi

case "$1" in
    serve)
        exec php artisan serve --host=0.0.0.0 --port=8000
        ;;
    queue)
        exec php artisan queue:work redis --sleep=1 --tries=1 --timeout=90
        ;;
    schedule)
        exec php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
