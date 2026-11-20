# Bill chat and retrieval

Work stays in RSun until a second consumer justifies extracting a library.
The public site is `https://vabills.org`. The previous rsun.survos.com hostname
remains available for existing MCP connections.
The active stack is PostgreSQL, Symfony AI,
and the shared `survos/ai-chat-bundle`; DuckDB is retained only as an experiment.

## Data and evidence

The downloaded archive is imported into `Bill`. `BillVersion` records every supplied
version reference and associates the archive's single full-text body with its
reported current version. An absent body remains NULL: a reference is not evidence
that we possess that historical text. `BillSection` stores ordered HTML, text,
proposed wording and inline insertions/deletions. The unmodified source and SHA-256
are preserved. Inline edits are not modeled as separately filed amendments.

2026 currently has 3,948 distinct bills, 9,655 version references, 3,690 available
bodies and 12,453 derived sections. These counts describe the imported snapshot,
not a claim about all Virginia legislation or the current Code.

## Build locally

```sh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:postgres:build
php bin/console app:bills:structure --year=2026
php bin/console app:bills:vectorize --year=2026 --prepare-only --no-debug
php bin/console app:bills:vectorize --year=2026 --no-debug
php bin/console app:bills:project-es --year=2026 --no-debug
```

Use `--no-debug` for bulk jobs: the debug profiler retains HTTP requests and can
exhaust memory. Embeddings are content-addressed, persisted and resumable. A failed
or pending request requires inspection before retry; it might already have been
billed. The initial interrupted 24-input batch is recorded in each affected row's
`error` field. Never clear completed vectors to restart a job.

`app:bills:structure --reparse` refreshes derived sections, preserving unchanged
identifiers. Run vector preparation again afterward to register any changed inputs.

## Models and chunking

Symfony AI uses the same Gemini provider as FF. Keys belong in ignored `.env.local`.
Chat uses `gemini-flash-lite-latest`; embeddings use `gemini-embedding-2`, 1,024
dimensions. No additional dense summary is generated. Existing bill summaries
support discovery; answers must consult full text.

Short sections remain whole. Long sections split at paragraph, then sentence
boundaries using PHP IntlBreakIterator, with a UTF-8-safe fallback for oversized
sentences. A byte limit controls initial packing, not token accounting: Gemini's
countTokens checks every new embedding input against a 7,000-token budget. Exact
source text, including deletions, remains available when the embedded proposed
wording omits deleted passages.

## Retrieval and clients

- `/bills/chat`: session chat; `?year=2026&bill=83553` scopes a conversation.
- `/bills/sections/{id}`: version-specific source citation.
- `/mcp`: read-only `find_bills`, `find_bill_summaries`, `read_bill_section`, `read_bill`.
- `app:bills:search 'question' --backend=postgres|es`: inspect retrieval results.

PostgreSQL retrieval fuses full-text/BM25 and cosine vector rankings by section.
The ES projection copies the same chunks and vectors without re-embedding, checks
bulk errors/counts and switches a session alias only when every chunk is ready.
Old projection indices are retained for inspection; they are not automatically deleted.

The shared chat widget owns presentation and the optional Clear conversation button.
RSun owns scope, CSRF, session history, retrieval and reset. Clearing removes the
selected history, which is the sole conversation memory passed to the agent.
Bill-scoped retrieval is enforced server-side. Direct bill lookup also works for
older imported bills, even when their sections have not been vectorized.

Top-k retrieval cannot prove corpus-wide absence. Answers must distinguish proposal,
version, outcome and effective dates; tax calculations need units and applicable
conditions. Missing historical bodies limit amendment comparisons.

## Local verification (2026-11-20)

The current snapshot has 18,219 chunks backed by 18,217 distinct completed document
embeddings. All vectors have 1,024 dimensions; the largest input is 2,699 counted
tokens. Recorded document input totals 9,735,935 tokens (about $1.95 at $0.20 per
million, excluding chat and any provider billing differences). The interrupted
24-input batch could add 16,313 duplicate input tokens, less than one cent.

The local ES alias `rsun_bill_sections_2026` contains all 18,219 chunks. A 20-document
sample matches PostgreSQL text, section IDs and vector values within 1e-6. Both
backends pass the named minimum-wage, tobacco-licensing, nicotine-tax and bill-scope
checks. Run them after imports or retrieval changes:

```sh
php bin/console app:bills:check-retrieval --backend=postgres --no-debug
php bin/console app:bills:check-retrieval --backend=es --no-debug
```

These are smoke checks, not a comprehensive relevance evaluation. Warm query-vector
cache measurements were 49–127 ms for PostgreSQL and 27–106 ms for ES. New queries
also incur an embedding request. Both lexical paths search section chunk text;
repeating the bill summary on every ES chunk distorted ranking and is avoided.
Chat and MCP currently use PostgreSQL retrieval; ES is a verified alternate
projection exposed through the search command, not the default chat backend.

HTTP chat checks covered 2023 SB992 and 2026 HB1, including their metadata and source
answers. Session-wide loose-tobacco questions do not have sufficient retrieved
rate evidence for a supported calculation. Historical version bodies absent from
the archive remain unavailable; 258 imported 2026 bills lack full-text bodies.

## Public connection

Connect at `https://vabills.org/mcp` without authentication. The setup page at
`/bills/chat/connect` links to the current Claude and ChatGPT connection screens.
`APP_PUBLIC_URL` controls the origin shown there. Allowed hosts and browser origins
are explicit in `config/packages/mcp.yaml`; ChatGPT and Claude are allowed, while
unrelated browser origins remain rejected.

## MCP feedback improvements (local, 2026-11-20)

Both search tools accept `limit` (1–50), `offset` (0–200), `outcome`
(passed/failed/vetoed/incorporated), exact `status`, `chamber` (house/senate),
and a case-insensitive literal `patron` substring. The existing year/bill scope
still applies before ranking. Invalid bounds and enum filters are rejected.

Summary discovery remains weighted PostgreSQL keyword search, prioritizing titles.
Exact topic queries vaping/vape/e-cigarettes expand to statutory aliases; tobacco
adds nicotine/cigarette/cigar. Arbitrary OR queries retain their syntax. An ATF
agency name in a summary alone no longer makes that bill a tobacco-topic match.
This is a focused alias pass, not summary embeddings or a general synonym engine.

Section retrieval still fuses BM25 and embeddings, first selecting the best chunk
per section for each method (500 candidates each), then grouping Code headings
within the year. Pagination is after grouping. Results retain a representative
section and at most eight separate bill/version references, with candidate counts
and a truncation flag. These are not exhaustive session counts. Different versions
are never asserted to have identical text. Excerpts are at most 480 characters
plus ellipses; use read_bill_section for the complete text.

MCP outcomes normalize vetoes/incorporations while preserving archive_outcome.
incorporated_into is derived from explicit archive summary/history evidence.
SB826 points to SB542. The raw archive itself calls SB630's patron “Del.” and
assigns house as her chamber; this is not a parser-added prefix. Tool responses
flag the mismatch without guessing or overwriting source data.

Budget line items are not separately indexed. Both tool descriptions and the
site-agent prompt now state that limitation. No additional budget corpus or
summary embeddings are claimed by this change.

Reproduction against the local 2026 archive: vaping and e-cigarettes each discover
HB1449, SB789 and SB712; the broad tobacco/nicotine/vapor/cigarette/hemp query
excludes HB907 and SB704. Tested summary and grouped-section pagination, outcome
plus chamber filters, patron matching, incorporation extraction and existing
retrieval/scope smoke checks. Changes require deployment before public clients
see the revised tool schema; reconnect/refresh tools afterward.

## Historical backfill on production

The 2023–2025 catalogs are already imported (3,029 / 3,595 / 3,512 bills).
For each session, run `app:bills:structure --year=YEAR --no-debug`, then
`app:bills:vectorize --year=YEAR --no-debug`, followed by
`app:bills:project-es --year=YEAR --no-debug`. Run on the production host so the
database and Elasticsearch traffic stays there. Preserve completed vectors and
inspect pending/failed attempts before retrying. The ES alias stays on its
previous generation until projection validation succeeds.

Verify `coverage.catalog_bills`, `coverage.bills_with_embeddings`, distinct-bill
counts and HTTP/MCP parity for each year. Current imported full-text coverage is
3,013 / 3,533 / 3,364 bills for 2023 / 2024 / 2025. Version references without
bodies remain metadata-only; this does not fetch separate historical amendments.
Definitions remain part of the source bill, with their scope and inline changes.

## Demo follow-up

The approved November 2026 demo is on YouTube. A QR code and a simplified mobile
chat experience, using the RappNews local experience as a reference, are deferred
to v2. Review new cuts before uploading: YouTube revisions need new video URLs.
