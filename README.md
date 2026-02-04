# Gradin Backend Position Test - Courier Module (Laravel 12)

Backend-only API for Courier master data.

Features:
- CRUD: `index`, `show`, `store`, `update`, `destroy`
- Index supports pagination, default sort by name, optional sort by `registered_at`
- Search by name terms (e.g. `search=budi+agung`)
- Filter by level `2,3`
- Rate limit: 5 req/min per IP

Courier table columns:
- `id`
- `name`
- `phone` (unique, nullable)
- `email` (nullable)
- `level` (1-5)
- `status` (`active|inactive`, default `active`)
- `registered_at`
- `created_at`, `updated_at`

## Setup (copy-paste in order)
```bash
git clone https://github.com/alfonsusenrico/test-backend-gradin.git
cd test-backend-gradin
composer install
cp .env.example .env
php artisan key:generate
sudo mysql
```

```sql
CREATE DATABASE project_gradin;
CREATE USER 'user'@'localhost' IDENTIFIED BY 'test_programming';
GRANT ALL PRIVILEGES ON project_gradin.* TO 'user'@'localhost';
FLUSH PRIVILEGES;
exit;
```

Update `.env`:
```
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=project_gradin
DB_USERNAME=user
DB_PASSWORD=test_programming
```

```bash
php artisan migrate
php artisan serve
php artisan test --filter CourierApiTest
```

## Commands
```bash
php artisan serve
php artisan migrate
php artisan test
php artisan test --filter CourierApiTest
```

## Tests (Feature)
File: `tests/Feature/CourierApiTest.php`

Covered sections:
- Index: pagination, default sort by name, override sort `registered_at`, search, filter level `2,3`.
- Store/Update/Destroy: create, partial update, delete, and DB assertions.
- Rate limit: limiter set to **5 req/min per IP** (in `app/Providers/AppServiceProvider.php`).
  Test sends **6 requests** and expects **429 Too Many Requests**.
