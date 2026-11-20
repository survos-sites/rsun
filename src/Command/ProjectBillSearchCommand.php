<?php

declare(strict_types=1);
namespace App\Command;
use App\Service\{BillElasticProjection, BillEmbeddings};
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\{AsCommand, Option};
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:bills:project-es', 'Atomically publish PostgreSQL bill chunks and existing vectors to Elasticsearch')]
final readonly class ProjectBillSearchCommand
{
    public function __construct(private Connection $db, private BillElasticProjection $es) {}
    public function __invoke(SymfonyStyle $io, #[Option('Session year')] int $year=2026): int
    {
        $missing=$this->db->fetchOne("SELECT count(*) FROM rsun_ai_chunk c JOIN rsun_ai_embedding e ON e.id=c.embedding_id JOIN bill_section s ON s.id=c.section_id JOIN bill_version v ON v.id=s.version_id JOIN bill b ON b.id=v.bill_id WHERE b.year=? AND (e.status <> 'complete' OR e.embedding IS NULL OR e.model <> ?)",[$year,BillEmbeddings::MODEL]);
        if($missing) throw new \RuntimeException($missing.' chunks still lack matching embeddings; projection not published.');
        $alias=$this->es->alias($year); $index=$alias.'_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3));
        $client=$this->es->client();
        $properties=[];
        foreach(['section_id','number','status','outcome','version','citation','model','source_hash'] as $field) $properties[$field]=['type'=>'keyword'];
        foreach(['text','title','heading','summary'] as $field) $properties[$field]=['type'=>'text','analyzer'=>'english'];
        foreach(['year','bill_id','position'] as $field) $properties[$field]=['type'=>'integer'];
        $properties['source_date']=['type'=>'keyword'];
        $properties['embedding']=['type'=>'dense_vector','dims'=>1024,'index'=>true,'similarity'=>'cosine'];
        $client->indices()->create(['index'=>$index,'body'=>['mappings'=>['dynamic'=>'strict','properties'=>$properties]]]);
        $body=[]; $count=0;
        $flush=function()use(&$body,$client,$index):void {
            if(!$body)return;
            $result=$client->bulk(['index'=>$index,'body'=>$body])->asArray();
            if($result['errors'])throw new \RuntimeException('Elasticsearch bulk projection failed; alias unchanged.');
            $body=[];
        };
        foreach($this->db->iterateAssociative("SELECT c.id,c.text,c.position,c.section_id,e.embedding::text,e.model,s.heading,v.code AS version,v.source_date,v.source_hash,b.id AS bill_id,b.year,b.number,b.catch_line AS title,b.summary,b.status,b.outcome FROM rsun_ai_chunk c JOIN rsun_ai_embedding e ON e.id=c.embedding_id JOIN bill_section s ON s.id=c.section_id JOIN bill_version v ON v.id=s.version_id JOIN bill b ON b.id=v.bill_id WHERE b.year=? ORDER BY c.id",[$year]) as $r) {
            $id=$r['id'];unset($r['id']);
            $r['embedding']=json_decode($r['embedding'],true,flags:JSON_THROW_ON_ERROR);
            if(count($r['embedding'])!==1024)throw new \RuntimeException('Invalid vector dimensions');
            $r['summary']=html_entity_decode(strip_tags($r['summary'] ?? ''));
            $r['citation']='/bills/sections/'.$r['section_id'];
            $body[]=['index'=>['_id'=>$id]]; $body[]=$r; ++$count;
            if(count($body)>=200)$flush();
        }
        $flush();
        if(!$count)throw new \RuntimeException('Refusing to publish an empty projection');
        $client->indices()->refresh(['index'=>$index]);
        if($client->count(['index'=>$index])->asArray()['count']!==$count)throw new \RuntimeException('Projection count mismatch');
        $actions=[];
        if($client->indices()->existsAlias(['name'=>$alias])->asBool()) $actions[]=['remove'=>['index'=>'*','alias'=>$alias]];
        $actions[]=['add'=>['index'=>$index,'alias'=>$alias]];
        $client->indices()->updateAliases(['body'=>['actions'=>$actions]]);
        $io->success($count.' chunks published to '.$alias.' using saved vectors; no model calls.');
        return 0;
    }
}
