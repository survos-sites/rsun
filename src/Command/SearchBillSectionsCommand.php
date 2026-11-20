<?php

declare(strict_types=1);
namespace App\Command;
use App\Service\{BillRetrieval, BillElasticProjection};
use Symfony\Component\Console\Attribute\{AsCommand, Argument, Option};
use Symfony\Component\Console\Style\SymfonyStyle;
#[AsCommand('app:bills:search', 'Inspect hybrid section retrieval with citations')]
final readonly class SearchBillSectionsCommand
{
    public function __construct(private BillRetrieval $postgres, private BillElasticProjection $elastic) {}
    public function __invoke(SymfonyStyle $io, #[Argument('Natural-language query')] string $query,
        #[Option('postgres or es')] string $backend='postgres', #[Option('Session')] int $year=2026,
        #[Option('Optional bill ID')] int $bill=0): int
    {
        $start=microtime(true);
        $results=match($backend) {'postgres'=>$this->postgres->find($query,$year,$bill),'es'=>$this->elastic->find($query,$year,$bill),default=>throw new \InvalidArgumentException('Unknown backend')};
        $io->writeln(json_encode(['backend'=>$backend,'seconds'=>round(microtime(true)-$start,3),'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        return 0;
    }
}
