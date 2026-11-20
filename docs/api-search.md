# Canonical bill search

`GET /api/bills/search` and the API Platform MCP `find_bills` tool share
`BillSearchProvider`. Site chat, `find_bill_summaries` and `count_bills` also call
that provider in process. There is no loopback HTTP request and no separate MCP
SQL search/count implementation. The earlier PostgreSQL retrieval remains for
comparison commands; source-body reads still use PostgreSQL.

This follows FF's `ArticleSearchProvider` pattern: a scoped request-body extension
and hit denormalizer feed API Platform's native Elasticsearch CollectionProvider.
API Platform MCP requires `api_platform.mcp.enabled` and `symfony/object-mapper`.

## Contract

- `query`, `year` (2026), `billId`, `limit` (1–50), `offset` (0–9950).
- `mode`: `hybrid` (default), `fulltext`, `summaries`.
- `minimumSimilarity`: cosine threshold, default 0.65, used only in hybrid mode.
- Filters: `outcome`, exact `status`, `chamber`, `patron` substring, `party`.
- `bills`: one result per bill with title, summary, sponsor, party, outcome and a
  matching passage/citation. A section citation identifies the retrieved version.
- `total`, `facets`: exact distinct bill IDs for the same query and filters.
  `returnedCount` is this page's size. `hasMore` describes additional matches;
  offsets beyond the supported result window are rejected.
- `coverage`: published session generation, catalog size and bills with embeddings.

Hybrid means keyword matches OR embedded passages above the cosine threshold.
Scores combine lexical and semantic scores. This is deliberately **not** top-k
RRF: a bounded vector candidate list cannot provide an exhaustive topic total.
The default threshold is a starting point to evaluate, not a calibrated legal
relevance guarantee. Exact cosine evaluation scans eligible embedded passages;
benchmark before extending this strategy to much larger corpora.

A counted match is not necessarily a substantive change: a bill can mention a
subject incidentally or in definitions. No mode promises all legally related
bills. Budget line items and unavailable historical bodies remain outside coverage.
Fulltext covers archived text and section passages; summaries only title/summary
keywords with topic aliases. Empty query counts the filtered bill catalog, including
bills without any vectors. An unavailable session fails explicitly instead of
silently searching a different year.

## Exact counting

Never use ES `hits.total` after collapse as a bill count. It counts documents.
Never call a cardinality aggregation exact. Here a composite aggregation pages
through distinct `bill_id` buckets, with one metadata record per bill for facets.
Counting uses the identical query as retrieval, without page limits. A partial
or timed-out count fails instead of returning an apparently exact number.
The alias resolves once per request to an immutable generation, keeping pages,
counts and coverage on the same snapshot. Across separate requests, a publication
can change the generation; its identifier is returned in coverage.

## Publication

`php bin/console app:bills:project-es --year=2026` publishes one catalog document
per archive bill plus its existing vector chunks, validates bulk/document counts,
and atomically swaps the session alias. No corpus embedding requests are made.
The schema marker prevents old chunk-only projections from reporting incomplete
catalog totals. Publish this projection before exposing the new search in an
existing environment. Historical embedding generation remains a separate task.

## Reuse in search-bundle

The reusable unit is an entity-grouped search provider with configurable identity
field, metadata/facet projection, query builder and explicit count policy:
`exact distinct`, `approximate`, or `bounded candidates`. HTTP and MCP should share
its typed request/response contract and validation. Domain aliases, bill metadata,
source citations and version rules belong in the application.

FF already demonstrates the native API/MCP path; this application adds exact
entity counts and facets. Keep these concrete implementations as contract tests
for extracting the adapter into search-bundle. A skill can guide installation,
but cannot enforce result/count correctness; that belongs in code and tests.
