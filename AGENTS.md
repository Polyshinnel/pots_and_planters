# Repository instructions

## Laravel test environment

Laravel feature tests use a dedicated MariaDB database named `potsandplanters_test`. Run them only through the Docker `backend` service, where the host, username, and password come from the local Compose environment. `backend/phpunit.xml` forces the test connection to this database so a test run cannot target the normal `potsandplanters` database.

For a fresh local setup, start the development stack and create the isolated database once:

```sh
docker compose -f compose.yaml -f compose.dev.yaml up -d --wait
docker compose exec -T db sh -lc 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS potsandplanters_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON potsandplanters_test.* TO \"$MARIADB_USER\"@\"%\"; FLUSH PRIVILEGES;"'
```

Then run backend tests from the repository root:

```sh
docker compose -f compose.yaml -f compose.dev.yaml exec -T backend sh -lc 'DB_DATABASE=potsandplanters_test php artisan test --compact'
```

`RefreshDatabase` may reset tables in `potsandplanters_test`; it must never be pointed at a development or production database. These seed commands are safe to repeat and only insert missing rows:

```sh
docker compose -f compose.yaml -f compose.dev.yaml exec -T backend php artisan db:seed --class=PageSeoSeeder
docker compose -f compose.yaml -f compose.dev.yaml exec -T backend php artisan db:seed --class=CategorySeeder
```

## Run all checks through Docker Compose

Do not run application tests, TypeScript checks, or builds directly on the host. Use the Compose services so checks use the same runtime and dependencies as the application:

```sh
docker compose -f compose.yaml -f compose.dev.yaml exec -T backend sh -lc 'DB_DATABASE=potsandplanters_test php artisan test --compact'
docker compose -f compose.yaml -f compose.dev.yaml exec -T frontend npm run typecheck
docker compose -f compose.yaml -f compose.dev.yaml exec -T frontend npm run build
docker compose -f compose.yaml -f compose.dev.yaml exec -T frontend npm run test:catalog
docker compose -f compose.yaml -f compose.dev.yaml exec -T frontend npm run test:product
docker compose -f compose.yaml -f compose.dev.yaml exec -T frontend npm run test:enquiry
docker compose -f compose.yaml -f compose.dev.yaml exec -T frontend npm run test:forms
```

Run frontend commands from the repository root; the container executes them in `/app`.
