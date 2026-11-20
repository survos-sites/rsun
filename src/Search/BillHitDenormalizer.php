<?php

declare(strict_types=1);
namespace App\Search;

use ApiPlatform\Elasticsearch\Serializer\DocumentNormalizer;
use App\Service\BillSearchResult;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class BillHitDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $row = $data['_source'];
        unset($row['embedding'], $row['search_summary']);
        $row['id'] = $row['bill_id'];
        $row['score'] = $data['_score'];
        $row['excerpt'] = BillSearchResult::excerpt($row['text'] ?? $row['summary'] ?? '', $context['bill_query'] ?? '');
        unset($row['text']);
        return $row;
    }
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $format === DocumentNormalizer::FORMAT && $type === BillSearchInput::class;
    }
    public function getSupportedTypes(?string $format): array
    {
        return $format === DocumentNormalizer::FORMAT ? [BillSearchInput::class => true] : [];
    }
}
