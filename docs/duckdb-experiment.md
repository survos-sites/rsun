# Local DuckDB experiment

The existing local `/bills/search` UI and `/instant-search` endpoint use SearchBundle's
native DuckDB adapter. `BillDuckDbSearch` maps the snapshot fields to the existing
hit template, with all six facets, sorting and pagination. `BillDuckDbConnection`
opens the published snapshot read-only. `/bills/{id}` reads the same snapshot locally;
the old `/bills/duckdb` routes redirect to the existing routes.

This explicit search is registered only in dev/test. Production retains the automatic
entity search and Elasticsearch configuration. No production changes or embeddings
are part of this integration. Doctrine remains available for the app's other workflows.

The local app uses the monorepo through `php ../mono/link "$PWD"`. Composer's lock
still describes the released bundle; these shared changes need a bundle release and
an app dependency update before this integration is reproducible from Composer alone.

## Reproduce

Requires the native `pdo_duckdb` extension in both CLI PHP and the PHP-FPM serving the app. The current PHP driver embeds DuckDB **1.5.6**, while the installed CLI is **2.0.0**. The experiment deliberately uses PHP for builds and queries. Do not assume CLI and PHP engines/extensions are interchangeable.

```sh
php --ri pdo_duckdb
php bin/console app:duckdb:build
php bin/duckdb-benchmark.php
php bin/phpunit --do-not-record-test-run-history
```

The builder reads `data/YYYY.jsonl`, installs the matching official FTS extension, checks conflicting duplicate IDs, and removes identical duplicates. It stores the full source JSON alongside typed searchable fields, and extracts nested text entries into `bill_versions`. These are version references, not separate amendment records or version-specific full texts.

## Measured snapshot

- 14,093 source rows → 14,084 distinct bills/resolutions; nine identical duplicate rows.
- 32,242 text-version references across 2023–2026.
- Import and projection: 4.22 seconds. Total with FTS and compaction: 19.74 seconds.
- Compacted database: 516,435,968 bytes (492.5 MiB), including raw source JSON and the FTS index.
- Initial projection queries exhausted 1 GB and 2 GB limits. Batching JSON extraction into 128 records bounded this intermediate memory use; successful build used one thread and a 2 GB DuckDB limit.
- Building and dropping staging tables left the first successful file at 1.08 GiB. Copying the completed database into a fresh file reclaimed unused blocks.

## Historical standalone-prototype comparison

These measurements predate SearchBundle integration. One warmup plus five measured runs per query; median below. All filter to 2026 and return 24 hits. DuckDB keeps one connection open for these measurements. Elasticsearch runs through the existing application gateway, including facets, highlighting and larger returned documents. These are current implementation measurements, **not equivalent-workload engine benchmarks**.

| Query | DuckDB hits | ES hits | DuckDB median ms | ES median ms | Top-ten overlap |
|---|---:|---:|---:|---:|---:|
| (browse) | 3948 | 3948 | 1.39 | 16.11 | 10 |
| minimum wage | 98 | 103 | 84.22 | 55.36 | 10 |
| school funding | 395 | 430 | 62.85 | 42.64 | 3 |
| abortion | 30 | 462 | 28.96 | 54.49 | 10 |
| solar energy | 101 | 101 | 65.89 | 65.61 | 8 |
| hb1 | 1 | 0 | 26.68 | 5.04 | 0 |

DuckDB uses Porter stemming and BM25 with all query terms required (`conjunctive := 1`), and keeps digits to support bill-number lookup. Elasticsearch uses its configured English analysis, AND matching plus fuzzy/prefix clauses. DuckDB searches number, title, summary, full text and patron; the current ES searchable fields omit number. HTML tags are removed from the DuckDB search projection. These differences explain why counts and ordering need not match; no relevance judgments have been performed.

## Historical standalone-prototype web-request latency

Three sequential local HTTPS requests per query, with Symfony debug enabled and a
fresh read-only DuckDB connection per request (browser behavior):

| Query | HTTP time range | Search + connection range | Connection-open range |
|---|---:|---:|---:|
| minimum wage, 2026 | 1,144–1,275 ms | 1,090–1,219 ms | 12–25 ms |
| solar energy, 2026 | 751–767 ms | 671–711 ms | 12–13 ms |

The connection call itself is only a small part of this gap; the first FTS queries
on a fresh connection reload/prepare state that the warm benchmark can reuse.
The snapshot is usable, but it is not a demonstrated performance improvement for
PHP-FPM. A long-lived reader, smaller search projection, or different query plan
would be separate experiments. No persistent worker or new service was installed.

Raw measurements are in `duckdb-benchmark.json` and `duckdb-http-benchmark.json`.

## Snapshot lifecycle and limitations

- Each build creates a new generation, closes/checkpoints it, compacts it and atomically publishes `var/duckdb/current.json`. A failed build leaves the previous published snapshot active. Older successful files remain available; remove them only after readers have finished.
- Web requests open the selected generation read-only. There is no writer touching the file being served.
- DuckDB 1.5 FTS indexes do not automatically track later changes; refresh by rebuilding the snapshot. See https://duckdb.org/docs/1.5/core_extensions/full_text_search.
- SearchBundle supplies year, chamber, status, outcome, patron party and committee facets, plus sorting and pagination. Facet counts exclude their own active filter. ES retains distinct highlighting and fuzzy/prefix behavior.
- Opening a database per PHP request can make real page latency substantially higher than the warm persistent-connection benchmark. The SearchBundle profiler records query timings.
- The source snapshots contain public legislative data. The generated database remains ignored under `var/`; no database files are committed.

The result supports a read-only snapshot architecture for this corpus. It does not yet establish that replacing the current PostgreSQL + Elasticsearch application is beneficial.

## Shared adapter validation

`bin/duckdb-benchmark.php` now measures the shared InstantSearch gateway with six
facets and a fresh connection per query. The older JSON measurements above remain
historical; they are not measurements of this integration.

The app's HTTP test builds a tiny independent snapshot, then exercises the existing
endpoint, field aliases, digit-preserving bill-number FTS, facets, pagination, sorting,
empty results and bill details. The shared bundle tests cover native DuckDB facets,
ranges, bound parameters, FTS AND/OR matching and its existing optional vector path.
RSun does not configure vector columns or an embedding provider.

API Platform can later use a separate state provider over this same snapshot;
`survos/duckdb-api-bundle` already provides that integration path in the monorepo.
It is not installed or enabled by this search change.

Latest shared-gateway measurements on the 14,084-bill snapshot (2026 filter, 24 hits,
six facets, one warmup then five runs): browse 12 ms; minimum wage 391 ms; school
funding 306 ms; abortion 179 ms; solar energy 304 ms; hb1 179 ms. These are in-process
gateway medians including fresh connection setup, not HTTP latency or an ES comparison.
