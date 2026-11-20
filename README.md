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

# Database setup
bin/console doctrine:migrations:migrate

# Clear and warmup cache
bin/console cache:clear
bin/console cache:warmup

# Build local PostgreSQL full-text search after importing bills:
bin/console app:postgres:build
```

## Loading Data

Download the raw data files (if not already in data/):

```bash
bin/console app:download --from=2026 --to=2026 --force
```

Import bills into the database:

```bash
bin/console import:entities App\\Entity\\Bill data/2026.jsonl --batch=1
```

The downloader defaults to 2023 through the current year. Select sessions with
`--from` and `--to`; use `--force` to refresh existing snapshots. Imports update
bills by ID and retain other sessions. Do not use `--reset` for routine refreshes.
`castor bills:load --fetch --from=2026 --to=2026` validates the schema, downloads
the selected sessions, and imports them. Rebuild the search index afterward.

The upstream 2026 export contains repeated bill IDs. Use `--batch=1` with the
generic importer so repeated records update the same bill without an ORM identity
collision. The Castor task supplies this option automatically.

## Search

Local bill details and default search use PostgreSQL. The SearchBundle provides
PostgreSQL full-text/BM25 and Elasticsearch comparison routes. DuckDB is no longer
in the active application path. See [bill chat and retrieval](docs/bill-chat.md)
for 2026 structure, embeddings, scoped chat, MCP and the Elasticsearch projection.

### Existing production Elasticsearch setup

The following commands apply to the existing production setup, not local DuckDB.
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

## Local DuckDB experiment

The DuckDB build command and tests remain available for experiments, but the
application defaults to PostgreSQL and omits DuckDB from navigation and public
local API routes. See [historical experiment notes](docs/duckdb-experiment.md).

## Backend comparison and API

PostgreSQL and Elasticsearch share the browser hit template and facets:

| Page | POST endpoint | Named search |
|---|---|---|
| `/bills/search/postgres` | `/bills/search/postgres/query` | `app_bill_pg` |
| `/bills/search/es` | `/bills/search/es/query` | `app_bill_es` |

`/bills/search` defaults to PostgreSQL. Each endpoint rejects another backend's
search name. Switching tabs carries query/filter state. Details always load from
Doctrine. `app:postgres:build` builds `rsun_bill_search` directly from the Bill table,
with English GIN full-text and pg_textsearch BM25 indexes; DuckDB is not required.

API Platform exposes `/api/search/bills/postgres` and `/api/search/bills/es`, with
`q`, `page`, `limit`, `filter`, `min`, `max` and `sort` parameters. `/api/docs` documents
the envelopes. The optional SearchBundle API Platform bridge supplies the provider
and DTO. DuckDB routes survive only in the test environment.

The bill-list ES route uses the original `rsun_bill` index. Versioned section/vector
retrieval has its own `rsun_bill_sections_YEAR` projection and is documented in
[the chat guide](docs/bill-chat.md). These are separate indexes with different units:
one record per bill versus one record per section chunk.

Typing is debounced by 300 ms. Pending searches replace old hits with a centered
spinner. Highlighting escapes source HTML and uses safe literal-term matches.
