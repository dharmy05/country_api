Country Currency & Exchange API (plain PHP)
==========================================

What this is
------------
A small RESTful API that:
- fetches country data from restcountries.com
- fetches exchange rates from open.er-api.com
- caches country records in MySQL
- computes estimated_gdp using a random multiplier per refresh
- serves data via REST endpoints and generates a summary image

Requirements
------------
- PHP 8.0+ with extensions: pdo_mysql, gd, curl
- MySQL 5.7+ (or compatible)
- Composer is NOT required

Installation
------------
1. Clone the repo:
   git clone <your-repo-url> countries-api
   cd countries-api

2. Create a database and run the provided SQL:
   - Use the SQL in the repo called `schema.sql` (or the CREATE statements in the README).
   - Example:
     mysql -u root -p < schema.sql

3. Copy `.env.example` to `.env` and edit to match your DB credentials:
   cp .env.example .env

   Important vars:
     DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
     CACHE_DIR (defaults to ./cache)

4. Ensure cache dir is writable:
   mkdir -p cache
   chmod 755 cache

Run locally
-----------
You can use PHP built-in server for local testing:

php -S 0.0.0.0:8080 index.php

Or put the files on your webserver and point document root to project directory.

Endpoints
---------
POST /countries/refresh
- Fetch countries + exchange rates, upsert into DB, generate cache/summary.png.

GET /countries
- List countries. Query params:
  - region (e.g. ?region=Africa)
  - currency (e.g. ?currency=NGN)
  - sort=gdp_desc (e.g. ?sort=gdp_desc)

GET /countries/:name
- Get country by name (case-insensitive)

DELETE /countries/:name
- Delete a country record

GET /status
- { total_countries, last_refreshed_at }

GET /countries/image
- Returns cache/summary.png (or 404 JSON if not found)

Error behaviour
---------------
- 400 -> Validation failed (JSON with details)
- 404 -> { "error": "Country not found" } or image not found
- 503 -> External data source unavailable (when external API fails)
- 500 -> Internal server error (generic)

Notes
-----
- Refresh is atomic (wrapped in transaction). If external APIs fail the DB is not modified.
- For countries with empty currencies: currency_code null, exchange_rate null, estimated_gdp 0 (but record stored).
- For currency codes not found in exchange rates, exchange_rate=null and estimated_gdp=null.
- Random multiplier between 1000 and 2000 is freshly generated per country on every refresh.

Testing
-------
- Call POST /countries/refresh then GET /status and GET /countries to verify records.
- Example (using curl):
  curl -X POST http://localhost:8080/countries/refresh
  curl http://localhost:8080/countries?region=Africa&sort=gdp_desc

License
-------
MIT
