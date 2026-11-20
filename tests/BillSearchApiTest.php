<?php

declare(strict_types=1);
namespace App\Tests;

use App\Service\{BillElasticProjection, BillEmbeddings, BillSearchEngine};
use Doctrine\DBAL\Connection;
use Elastic\Elasticsearch\ClientBuilder;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\{MockHttpClient, Psr18Client};
use Symfony\Component\HttpClient\Response\MockResponse;

final class BillSearchApiTest extends WebTestCase
{
    private array $bodies = [];
    private bool $partial = false;
    private function installEngine(): void
    {
        $db = $this->createStub(Connection::class);
        $db->method('fetchAssociative')->willReturn(['status'=>'complete']);
        $db->method('fetchOne')->willReturn(json_encode(array_fill(0, 1024, 0.01)));
        $embeddings = new BillEmbeddings($db, $this->createStub(PlatformInterface::class), new MockHttpClient(), 'unused');
        $projection = new BillElasticProjection('http://localhost:9200', $embeddings);
        $http = new MockHttpClient(function ($method, $url, $options) {
            $body = json_decode($options['body'] ?? '{}', true) ?? [];
            $this->bodies[] = $body;
            $response = [];
            if (str_contains($url, '_mapping')) $response = ['generation'=>['mappings'=>['_meta'=>['bill_search_schema'=>2]]]];
            elseif (str_contains($url, '_alias')) $response = ['generation'=>['aliases'=>['rsun_bill_sections_2026'=>new \stdClass()]]];
            elseif (isset($body['aggs']['bills'])) {
                // Two distinct bills despite 52 underlying chunks; exercise composite pagination.
                $after = $body['aggs']['bills']['composite']['after'] ?? null;
                $response = ['aggregations'=>['bills'=>$after ? ['buckets'=>[]] : ['after_key'=>['bill'=>2], 'buckets'=>[
                    ['key'=>['bill'=>1], 'doc_count'=>40, 'metadata'=>['hits'=>['hits'=>[['_source'=>['outcome'=>'passed','chamber'=>'house','patron_party'=>'D']]]]]],
                    ['key'=>['bill'=>2], 'doc_count'=>12, 'metadata'=>['hits'=>['hits'=>[['_source'=>['outcome'=>'failed','chamber'=>'senate','patron_party'=>'R']]]]]],
                ]]]];
            } elseif (isset($body['aggs']['embedded'])) {
                $response = ['hits'=>['total'=>['value'=>3,'relation'=>'eq']], 'aggregations'=>['embedded'=>['doc_count'=>2]]];
            } elseif (isset($body['collapse'])) {
                $response = ['hits'=>['total'=>['value'=>52,'relation'=>'eq'], 'hits'=>[
                    ['_index'=>'generation','_id'=>'chunk-1','_score'=>3.5,'_source'=>['bill_id'=>1,'title'=>'Youth opportunities','text'=>'A provision about children.','citation'=>'/bills/sections/abc','patron'=>'Example','patron_party'=>'D']],
                ]]];
            }
            if ($this->partial && isset($body['aggs']['bills'])) $response['timed_out'] = true;
            return new MockResponse(json_encode($response), ['http_code'=>200,'response_headers'=>['Content-Type: application/json','X-Elastic-Product: Elasticsearch']]);
        });
        $es = ClientBuilder::create()->setHosts(['http://localhost:9200'])->setHttpClient(new Psr18Client($http))->build();
        (new \ReflectionProperty(BillElasticProjection::class, 'client'))->setValue($projection, $es);
        self::getContainer()->set(BillSearchEngine::class, new BillSearchEngine($projection, $embeddings));
    }
    public function testHttpCountsBillsNotChunksAndSharesCountQuery(): void
    {
        $client = self::createClient();
        $this->installEngine();
        $client->request('GET', '/api/bills/search?query=youth&mode=fulltext&limit=1');
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(2, $data['total']);
        self::assertSame('bills', $data['unit']);
        self::assertSame(1, $data['returnedCount']);
        self::assertTrue($data['hasMore']);
        self::assertSame(['passed'=>1,'failed'=>1], $data['facets']['outcome']);
        self::assertSame(3, $data['coverage']['catalog_bills']);
        self::assertSame(1, $data['bills'][0]['id']);
        $search = array_values(array_filter($this->bodies, fn ($b)=>isset($b['collapse'])))[0];
        $count = array_values(array_filter($this->bodies, fn ($b)=>isset($b['aggs']['bills'])))[0];
        self::assertSame($search['query'], $count['query']);
    }
    public function testNativeMcpAndHttpHaveIdenticalResults(): void
    {
        $client = self::createClient(); $client->disableReboot(); $this->installEngine();
        $client->request('GET', '/api/bills/search?query=youth&limit=1');
        self::assertResponseIsSuccessful();
        $http = json_decode($client->getResponse()->getContent(), true);
        $headers = ['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json, text/event-stream'];
        $client->request('POST', '/mcp', server:$headers, content:json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>'initialize','params'=>['protocolVersion'=>'2025-03-26','capabilities'=>new \stdClass(),'clientInfo'=>['name'=>'test','version'=>'1']]]));
        self::assertResponseIsSuccessful();
        $headers['HTTP_MCP_SESSION_ID'] = $client->getResponse()->headers->get('Mcp-Session-Id');
        $client->request('POST', '/mcp', server:$headers, content:json_encode(['jsonrpc'=>'2.0','id'=>2,'method'=>'tools/list']));
        $tools = json_decode($client->getResponse()->getContent(), true)['result']['tools'];
        self::assertSame(1, count(array_filter($tools, fn ($t)=>$t['name']==='find_bills')));
        $client->request('POST', '/mcp', server:$headers, content:json_encode(['jsonrpc'=>'2.0','id'=>3,'method'=>'tools/call','params'=>['name'=>'find_bills','arguments'=>['query'=>'youth','limit'=>1]]]));
        $result = json_decode($client->getResponse()->getContent(), true)['result'];
        self::assertFalse($result['isError'] ?? false, json_encode($result));
        self::assertSame($http['bills'], $result['structuredContent']['bills']);
        self::assertSame($http['total'], $result['structuredContent']['total']);
        $client->request('POST', '/mcp', server:$headers, content:json_encode(['jsonrpc'=>'2.0','id'=>4,'method'=>'tools/call','params'=>['name'=>'count_bills','arguments'=>['query'=>'youth']]]));
        $count = json_decode($client->getResponse()->getContent(), true)['result'];
        self::assertFalse($count['isError'] ?? false, json_encode($count));
        self::assertSame($http['total'], $count['structuredContent']['total']);
    }
    public function testInvalidArgumentsDoNotReachSearch(): void
    {
        $client = self::createClient(); $client->disableReboot(); $this->installEngine();
        foreach (['query[]=x','limit=51','offset=-1','mode=other','party=X','minimumSimilarity=1.1','surprise=x'] as $query) {
            $client->request('GET', '/api/bills/search?'.$query);
            self::assertContains($client->getResponse()->getStatusCode(), [400, 422]);
        }
        self::assertSame([], $this->bodies);
    }
    public function testIncompleteCountsFailInsteadOfClaimingExactTotal(): void
    {
        $client = self::createClient(); $this->installEngine(); $this->partial = true;
        $client->request('GET', '/api/bills/search?mode=fulltext');
        self::assertResponseStatusCodeSame(503);
    }
    public function testCatalogAndFiltersApplyToCountsAndHits(): void
    {
        $client = self::createClient(); $this->installEngine();
        $client->request('GET', '/api/bills/search?party=D&outcome=passed&chamber=house&patron=Smith');
        self::assertResponseIsSuccessful();
        $search = array_values(array_filter($this->bodies, fn ($b)=>isset($b['collapse'])))[0];
        $count = array_values(array_filter($this->bodies, fn ($b)=>isset($b['aggs']['bills'])))[0];
        self::assertSame($search['query'], $count['query']);
        $filters = $search['query']['bool']['filter'];
        foreach ([['term'=>['record_type'=>'bill']], ['term'=>['outcome'=>'passed']], ['term'=>['chamber'=>'house']], ['term'=>['patron_party'=>'D']]] as $filter) self::assertContains($filter, $filters);
        self::assertStringNotContainsString('script_score', json_encode($search));
    }
    public function testSelectedChatScopeOverridesModelArguments(): void
    {
        self::bootKernel(); $this->installEngine();
        $requests = self::getContainer()->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $request = \Symfony\Component\HttpFoundation\Request::create('/bills/chat?year=2026&bill=123');
        $request->attributes->set('_route', 'app_bill_chat'); $requests->push($request);
        try {
            $result = self::getContainer()->get(\App\State\BillSearchProvider::class)->search(['year'=>2023,'billId'=>456,'mode'=>'fulltext']);
            self::assertSame(2026, $result->criteria['year']);
            self::assertSame(123, $result->criteria['billId']);
        } finally { $requests->pop(); }
    }

}
