<?php

declare(strict_types=1);
namespace App\Service;

use App\Search\BillSearchInput;
use Elastic\Elasticsearch\Client;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Elasticsearch query/count policy owned by the canonical API Platform search. */
final readonly class BillSearchEngine
{
    public function __construct(private BillElasticProjection $projection, private BillEmbeddings $embeddings) {}
    public function client(): Client { return $this->projection->client(); }
    public function index(int $year): string
    {
        $alias = $this->projection->alias($year);
        if (!$this->client()->indices()->existsAlias(['name'=>$alias])->asBool()) {
            throw new ServiceUnavailableHttpException(null, 'This session has not been published to the search index.');
        }
        $indices = $this->client()->indices()->getAlias(['name'=>$alias])->asArray();
        if (count($indices) !== 1) throw new \RuntimeException('Expected one immutable search generation.');
        $index = array_key_first($indices);
        $mapping = $this->client()->indices()->getMapping(['index'=>$index])->asArray();
        if (($mapping[$index]['mappings']['_meta']['bill_search_schema'] ?? 0) !== 2) {
            throw new ServiceUnavailableHttpException(null, 'Republish this session with app:bills:project-es before using bill search.');
        }
        return $index;
    }
    public function body(BillSearchInput $input): array
    {
        $filters = [['term'=>['year'=>$input->year]]];
        foreach (['billId'=>'bill_id','outcome'=>'outcome','status'=>'status','chamber'=>'chamber','party'=>'patron_party'] as $property=>$field) {
            if ($input->$property !== null && $input->$property !== 0) $filters[] = ['term'=>[$field=>$input->$property]];
        }
        if ($input->patron !== null) {
            $literal = str_replace(['\\','*','?'], ['\\\\','\\*','\\?'], $input->patron);
            $filters[] = ['wildcard'=>['patron'=>['value'=>'*'.$literal.'*','case_insensitive'=>true]]];
        }
        if ($input->query === '') {
            $filters[] = ['term'=>['record_type'=>'bill']];
            $query = ['bool'=>['filter'=>$filters]];
        } else {
            $fields = $input->mode === 'summaries' ? ['number^5','title^4','search_summary^2'] : ['number^5','title^4','search_summary^2','text'];
            // Interpret only OR alternatives and phrases; no user-supplied Elasticsearch field syntax.
            $expanded = BillSearchResult::query($input->query);
            $alternatives = preg_split('/\s+OR\s+/i', $expanded);
            $clauses = [];
            foreach ($alternatives as $alternative) {
                $clauses[] = ['multi_match'=>['query'=>trim($alternative, '" '),'fields'=>$fields,'type'=>str_contains($alternative, '"') ? 'phrase' : 'best_fields','operator'=>'and']];
            }
            $lexical = ['bool'=>['should'=>$clauses,'minimum_should_match'=>1]];
            if ($input->mode === 'summaries') $filters[] = ['term'=>['record_type'=>'bill']];
            $matches = [$lexical];
            if ($input->mode === 'hybrid') {
                $vector = json_decode($this->embeddings->query('task: search result | query: '.$input->query), true, flags: JSON_THROW_ON_ERROR);
                $matches[] = ['script_score'=>[
                    'query'=>['bool'=>['filter'=>[...$filters, ['exists'=>['field'=>'embedding']]]]],
                    'script'=>['source'=>"cosineSimilarity(params.vector, 'embedding') + 1.0", 'params'=>['vector'=>$vector]],
                    'min_score'=>1 + $input->minimumSimilarity,
                ]];
            }
            $query = ['bool'=>['filter'=>$filters,'should'=>$matches,'minimum_should_match'=>1]];
        }
        return ['query'=>$query, 'size'=>$input->limit, 'from'=>$input->offset,
            'collapse'=>['field'=>'bill_id'], 'sort'=>[['_score'=>'desc'],['bill_id'=>'asc'],['record_type'=>'asc'],['position'=>'asc']],
            '_source'=>['excludes'=>['embedding','search_summary']], 'track_total_hits'=>false];
    }
    /** Resolve incorporation against the same published generation as the search hits. */
    public function resolveIncorporations(string $index, array $bills): array
    {
        $numbers = array_values(array_unique(array_filter(array_map(static fn ($bill) => $bill['incorporated_into']['number'] ?? null, $bills))));
        if (!$numbers) return $bills;
        $response = $this->client()->search(['index'=>$index, 'body'=>[
            'size'=>count($numbers), 'query'=>['bool'=>['filter'=>[
                ['term'=>['record_type'=>'bill']], ['terms'=>['number'=>$numbers]],
            ]]], '_source'=>['bill_id','number','year','title','summary','status','outcome','patron','patron_party','version','citation'],
        ]])->asArray();
        $this->assertComplete($response);
        $related = [];
        foreach ($response['hits']['hits'] as $hit) $related[$hit['_source']['number']] = $hit['_source'];
        foreach ($bills as &$bill) {
            $target = $bill['incorporated_into']['number'] ?? null;
            if ($target && isset($related[$target])) {
                $bill['incorporated_into'] = $related[$target] + ['note'=>'Separate bill: read its final text. Do not assume every provision of the original survived.'];
            }
        }
        return $bills;
    }
    /** Enumerate composite buckets, not approximate cardinality or pre-collapse hit totals. */
    public function counts(string $index, array $query): array
    {
        $total = 0; $facets = ['outcome'=>[], 'chamber'=>[], 'patron_party'=>[]]; $after = null;
        do {
            $composite = ['size'=>1000,'sources'=>[['bill'=>['terms'=>['field'=>'bill_id']]]]];
            if ($after !== null) $composite['after'] = $after;
            $response = $this->client()->search(['index'=>$index,'body'=>['size'=>0,'query'=>$query,'aggs'=>[
                'bills'=>['composite'=>$composite,'aggs'=>['metadata'=>['top_hits'=>['size'=>1,'_source'=>array_keys($facets)]]]],
            ]]])->asArray();
            $this->assertComplete($response);
            $aggregation = $response['aggregations']['bills'];
            foreach ($aggregation['buckets'] as $bucket) {
                ++$total;
                $metadata = $bucket['metadata']['hits']['hits'][0]['_source'];
                foreach ($facets as $field=>&$values) {
                    $key = $metadata[$field] ?? 'unknown';
                    $values[$key] = ($values[$key] ?? 0) + 1;
                }
                unset($values);
            }
            $after = $aggregation['buckets'] ? ($aggregation['after_key'] ?? null) : null;
        } while ($after !== null);
        return [$total, $facets];
    }
    public function coverage(string $index): array
    {
        $response = $this->client()->search(['index'=>$index,'body'=>['size'=>0,'query'=>['term'=>['record_type'=>'bill']],
            'track_total_hits'=>true,'aggs'=>['embedded'=>['filter'=>['term'=>['has_embeddings'=>true]]]]]])->asArray();
        $this->assertComplete($response);
        return ['index'=>$index,'catalog_bills'=>$response['hits']['total']['value'],
            'bills_with_embeddings'=>$response['aggregations']['embedded']['doc_count'],
            'note'=>'Published snapshot for this session. Budget line items and missing historical bodies are not covered.'];
    }
    private function assertComplete(array $response): void
    {
        if (($response['timed_out'] ?? false) || ($response['_shards']['failed'] ?? 0)) {
            throw new ServiceUnavailableHttpException(null, 'Search was incomplete; no exact bill count is available.');
        }
    }
}
