<?php

namespace App\Enhance;

use Sentry\HttpClient\HttpClient;
use Survos\CoreBundle\Service\SurvosUtils;
use Survos\JsonlBundle\Event\JsonlConvertStartedEvent;
use Survos\JsonlBundle\Event\JsonlRecordEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;
use Survos\ImportBundle\Event\ImportConvertRowEvent;

class EnhanceRecordListener
{
    private $seen = [];

    public function __construct(
        private SluggerInterface    $asciiSlugger,
        private HttpClientInterface $httpClient,
    )
    {
    }

    #[AsEventListener(event: ImportConvertRowEvent::class)]
    final public function tweakRecord(ImportConvertRowEvent $event): void
    {
        $record = $event->row;
        $record = SurvosUtils::removeNullsAndEmptyArrays($record);
//        foreach ($event->tags as $tag)
        switch ($event->dataset) {
            case 'bills':
                $id = sprintf("%s-%s", $record['year'], $record['bill']);
                if (!in_array($id, $this->seen)) {
                    $record['id'] = $id;
                    $this->seen[] = $id;
                } else {
                    $record = null; // trigger to ignore
                }
                break;
        }

        $event->row = $record;

    }
}
