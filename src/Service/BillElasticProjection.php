<?php

declare(strict_types=1);
namespace App\Service;
use Elastic\Elasticsearch\{Client, ClientBuilder};
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class BillElasticProjection
{
    private ?Client $client = null;
    public function __construct(#[Autowire('%env(ELASTICSEARCH_DSN)%')] private string $dsn, private BillEmbeddings $embeddings) {}
    public function client(): Client
    {
        if ($this->client) return $this->client;
        $parts = parse_url($this->dsn);
        if (!$parts || !isset($parts['host'])) throw new \RuntimeException('Invalid Elasticsearch configuration');
        parse_str($parts['query'] ?? '', $options);
        $scheme = in_array($parts['scheme'], ['https','elasticsearch+https'], true) ? 'https' : 'http';
        $builder=ClientBuilder::create()->setHosts([$scheme.'://'.$parts['host'].':'.($parts['port'] ?? 9200)]);
        if(isset($options['ca'])) $builder->setCABundle($options['ca']);
        if(isset($options['api_key'])) $builder->setApiKey($options['api_key']);
        elseif(isset($parts['user'])) $builder->setBasicAuthentication(rawurldecode($parts['user']),rawurldecode($parts['pass'] ?? ''));
        return $this->client=$builder->build();
    }
    public function alias(int $year): string { return 'rsun_bill_sections_'.$year; }
    public function find(string $query, int $year=2026, int $billId=0): array
    {
        if (mb_strlen($query)>500) throw new \InvalidArgumentException('Search query too long');
        if (''===trim($query)) return [];
        $filter=[['term'=>['year'=>$year]], ['exists'=>['field'=>'section_id']]];
        if($billId) $filter[]=['term'=>['bill_id'=>$billId]];
        $base=['index'=>$this->alias($year), 'size'=>50, '_source_excludes'=>['embedding']];
        $lex=$this->client()->search($base+['body'=>['query'=>['bool'=>['filter'=>$filter,'must'=>[['match'=>['text'=>['query'=>$query,'operator'=>'and']]]]]]]])->asArray();
        $vector=json_decode($this->embeddings->query('task: search result | query: '.$query),true,flags:JSON_THROW_ON_ERROR);
        $sem=$this->client()->search($base+['body'=>['knn'=>['field'=>'embedding','query_vector'=>$vector,'k'=>50,'num_candidates'=>500,'filter'=>['bool'=>['filter'=>$filter]]]]])->asArray();
        $convert=static fn($r)=>array_map(static fn($h)=>['id'=>$h['_source']['section_id'],'excerpt'=>$h['_source']['text']]+$h['_source'],$r['hits']['hits']);
        return BillRetrieval::fuse($convert($lex),$convert($sem));
    }
}
