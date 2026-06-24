# stockflow

Simulating multi-warehouse stock reservation and automated order allocation.

## Requirements

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose
- [Make](https://www.gnu.org/software/make/)

## Quick start

1. Copy environment variables (done automatically on first run):

   ```bash
   cp .env.dist .env
   ```

2. Initialize the project and start containers:

   ```bash
   make init start
   ```

3. Create database schema and load sample data:

   ```bash
   make db-migrate
   make db-seed
   ```

4. Open the application:

   - API base URL: [http://localhost:8080/api](http://localhost:8080/api)
   - MariaDB: `localhost:3306` (credentials in `.env`)

## Stack

| Service  | Image            | Purpose              |
|----------|------------------|----------------------|
| PHP      | `php:8.4-fpm`    | Symfony 8.1 runtime  |
| Nginx    | `nginx:1.27`     | Web server           |
| MariaDB  | `mariadb:latest` | Database             |

## Authentication

All `/api/*` endpoints require an API key sent in a request header:

```bash
-H "API_KEY: dev"
```

The default development key is `dev` (configured via `API_KEY` in `.env`).

## API endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/warehouses` | List warehouses and stock levels |
| POST | `/api/orders` | Create order and reserve stock |
| GET | `/api/orders/{id}` | Get order details |
| POST | `/api/orders/{id}/ship` | Ship order and deduct stock |
| POST | `/api/orders/{id}/cancel` | Cancel order and recalculate reservations |

### Create order

```bash
curl -s -X POST http://localhost:8080/api/orders \
  -H "API_KEY: dev" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {"sku": "WIDGET-A", "quantity": 5},
      {"sku": "WIDGET-B", "quantity": 2}
    ]
  }'
```

Stock is allocated from the **fewest possible warehouses**. If the order cannot be fully fulfilled, the response includes `missing_items` and status `partially_reserved`.

### Ship order

```bash
curl -s -X POST http://localhost:8080/api/orders/1/ship \
  -H "API_KEY: dev"
```

Decreases warehouse quantities and marks the order as `shipped`.

### Cancel order

```bash
curl -s -X POST http://localhost:8080/api/orders/1/cancel \
  -H "API_KEY: dev"
```

Releases reservations and recalculates active orders for optimal allocation.

## Database

### Initialize schema

```bash
make db-migrate
```

Runs Doctrine migrations against MariaDB.

### Import sample data

```bash
make db-seed
```

Loads `data/seed.sql` with sample products, warehouses, and stock:

| SKU | Product |
|-----|---------|
| WIDGET-A | Widget A |
| WIDGET-B | Widget B |
| GADGET-C | Gadget C |

| Code | Warehouse |
|------|-----------|
| WH-EU | Europe Central |
| WH-US | United States East |
| WH-ASIA | Asia Pacific |

### Reset database

```bash
make db-reset
```

Drops all tables, re-runs migrations, and re-imports seed data.

## Testing

Run unit tests (core allocation logic):

```bash
make test
```

## Useful commands

```bash
make start          # Start all containers
make stop           # Stop containers
make down           # Stop and remove containers
make restart        # Restart containers
make logs           # Follow container logs
make shell          # Open shell in PHP container
make composer install   # Run composer in PHP container
make symfony cache:clear # Run Symfony console commands
make db-migrate     # Apply database migrations
make db-seed        # Import sample data
make db-reset       # Drop, migrate, and seed database
make test           # Run PHPUnit tests
```

## Database connection

Default credentials (override in `.env`):

| Variable | Default |
|----------|---------|
| Database | `stockflow` |
| User | `stockflow` |
| Password | `stockflow` |
| Root password | `root` |

From the host:

```bash
mysql -h 127.0.0.1 -P 3306 -u stockflow -pstockflow stockflow
```

From the PHP container, use the `DATABASE_URL` from `.env` (host `database`).

## Architecture

```
src/
  Allocation/          Stock allocation algorithm (unit tested)
  Controller/          REST API endpoints
  Entity/              Warehouse, Product, Order, reservations
  Service/             Order and reservation business logic
  Security/            API key authentication
data/
  seed.sql             Sample data for development
```

Design patterns used:

- **Repository** — Doctrine repositories for data access
- **Service layer** — `OrderService`, `StockReservationService`
- **Strategy** — `StockAllocator` encapsulates allocation algorithm

## Project structure

```
docker/
  nginx/default.conf   # Nginx virtual host
  php/Dockerfile       # PHP 8.4 with Symfony extensions
docker-compose.yml
Makefile
.env.dist
data/seed.sql
```

## Troubleshooting

**Port already in use**

Change `APP_PORT` or `MYSQL_PORT` in `.env`.

**Permission errors on `var/` or `vendor/`**

```bash
docker compose run --rm php sh -c 'mkdir -p var/cache var/log && chmod -R 777 var'
```

**401 Unauthorized on API calls**

Ensure the `API_KEY` header is sent. Nginx is configured to pass headers with underscores (`API_KEY`).

**`make db-seed` fails with duplicate entry (ERROR 1062)**

If seeding fails with an error like:

```text
ERROR 1062 (23000): Duplicate entry 'WIDGET-A' for key 'UNIQ_B3BA5A5AF9038C4'
```

The sample data was already imported. `data/seed.sql` uses plain `INSERT` statements and cannot be run twice on the same database.

Reset the database and import fresh data:

```bash
make db-reset
```

**Re-init Symfony from scratch**

Remove `composer.json`, `vendor/`, and Symfony directories, then run `make init` again.
