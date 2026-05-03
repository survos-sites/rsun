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
# - MEILI_SERVER (your Meilisearch server URL)
# - MEILI_API_KEY (your Meilisearch API key)

# Database setup
bin/console doctrine:migrations:migrate

# Clear and warmup cache
bin/console cache:clear
bin/console cache:warmup

# Setup Meilisearch index (REQUIRED before importing)
bin/console meili:settings:update --force --keys bill --wait
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

## Development

```bash
# Lint container
bin/console lint:container

# Run tests
./bin/phpunit
```

## Data Source

Data is imported from https://www.richmondsunlight.com/downloads/
