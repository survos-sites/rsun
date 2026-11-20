<?php

declare(strict_types=1);
namespace App\Command;
use App\Service\{BillRetrieval, BillElasticProjection};
use Symfony\Component\Console\Attribute\{AsCommand, Option};
use Symfony\Component\Console\Style\SymfonyStyle;
#[AsCommand('app:bills:check-retrieval', 'Run named 2026 retrieval checks and a bill-scope isolation check')]
final readonly class CheckBillRetrievalCommand
{
    public function __construct(private BillRetrieval $postgres, private BillElasticProjection $elastic) {}
    public function __invoke(SymfonyStyle $io, #[Option('postgres or es')] string $backend='postgres'): int
    {
        $search=match($backend) {'postgres'=>$this->postgres,'es'=>$this->elastic,default=>throw new \InvalidArgumentException('Unknown backend')};
        $cases=[
            ['query'=>'minimum wage','expected'=>['hb1','sb1']],
            ['query'=>'retail tobacco licensing','expected'=>['hb308','sb620']],
            ['query'=>'local taxes on nicotine vapor products','expected'=>['hb1449','sb712']],
        ];
        $failed=false;
        foreach($cases as $case) {
            $start=microtime(true);
            $hits=$search->find($case['query']);
            $top=array_map(strtolower(...),array_column(array_slice($hits,0,5),'number'));
            $passed=(bool)array_intersect($case['expected'],$top);
            $failed=$failed || !$passed;
            $io->writeln(json_encode(['query'=>$case['query'],'passed'=>$passed,'top5'=>$top,'seconds'=>round(microtime(true)-$start,3)],JSON_THROW_ON_ERROR));
        }
        $scoped=$search->find('minimum wage',2026,83553);
        $isolated=count($scoped)>0 && !array_filter($scoped,static fn($hit)=>(int)$hit['bill_id']!==83553 || (int)$hit['year']!==2026);
        $io->writeln(json_encode(['billScopePassed'=>$isolated],JSON_THROW_ON_ERROR));
        if($failed || !$isolated) { $io->error('One or more retrieval checks failed.');return 1; }
        $io->success('Named retrieval and scope checks passed; this is not a comprehensive relevance benchmark.');
        return 0;
    }
}
