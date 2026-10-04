# RSun - Virginia Legislation Tracker

Virginia Legislation tracking using data from https://www.richmondsunlight.com/downloads/

## Installation

```bash
# Install dependencies
composer install

# Copy and configure environment
cp .env .env.local
# Edit .env.local to set:
# - APP_SECRET
# - DATABASE_URL
# - ELASTICSEARCH_DSN (local default: elasticsearch://127.0.0.1:9200)
# - SEARCH_INDEX_PREFIX (rsun_, unique to this app)

# Database setup
bin/console doctrine:migrations:migrate

# Clear and warmup cache
bin/console cache:clear
bin/console cache:warmup

# Elasticsearch is populated after importing bills (see below).
```

## Loading Data

Download the raw data files (if not already in data/):

```bash
bin/console app:richmondsunlight:download-raw
```

Import bills into the database:

```bash
bin/console import:entities App\\Entity\\Bill data/2023.jsonl
```

## Search

Search uses `survos/search-bundle` with Elasticsearch, following the `packages` app.
The browser queries `/instant-search`; only `app_bill` is public, and engine credentials
stay on the server. Open `/bills/search` for text search, facets, and sorting.

After importing bills, build the index:

```bash
bin/console elastic:index:rebuild app_bill
bin/console elastic:index:status app_bill
```

The `rsun_` prefix isolates this app from other indexes on the shared cluster.
For subsequent Doctrine writes, run the dedicated worker:

```bash
bin/console messenger:setup-transports elastic
bin/console messenger:consume elastic --time-limit=3600 --memory-limit=256M
```

`Procfile` declares the worker; production must provision `ELASTICSEARCH_DSN`,
scale the `elastic` process, and restart it after its time limit (Dokku:
`ps:set rsun restart-policy unless-stopped`). This repository change does not deploy it.
Index diagnostics are available at `/admin/elastic/`.

## Development

```bash
# Lint container
bin/console lint:container

# Run tests
./bin/phpunit
```

## Data Source

Data is imported from https://www.richmondsunlight.com/downloads/
