<?php

declare(strict_types=1);

namespace App\Controller;

use Survos\SearchBundle\Http\InstantSearchGateway;
use Survos\SearchBundle\Search\SearchProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BillComparisonController extends AbstractController
{
    private const array SEARCHES = ['duckdb' => 'app_bill', 'postgres' => 'app_bill_pg', 'es' => 'app_bill_es'];
    private const array LABELS = ['duckdb' => 'DuckDB FTS / BM25', 'postgres' => 'PostgreSQL FTS / BM25', 'es' => 'Elasticsearch / BM25'];

    private function assertBackendAvailable(string $backend): void
    {
        if ('duckdb' === $backend && 'test' !== $this->getParameter('kernel.environment')) {
            throw $this->createNotFoundException();
        }
    }

    #[Route('/bills/search/{backend}', name: 'app_bill_compare', requirements: ['backend' => 'duckdb|postgres|es'], methods: ['GET'])]
    public function page(string $backend, SearchProvider $provider): Response
    {
        $this->assertBackendAvailable($backend);
        $name = self::SEARCHES[$backend];
        $search = $provider->getSearch($name)->create();
        $facets = [];
        foreach ($search->getFacets() as $facet) {
            $facets[$facet->getProperty()] = match ($facet->getProperty()) {
                'year' => 'Session year', 'patronParty' => 'Patron party', 'committeeShortname' => 'Committee',
                default => ucfirst($facet->getProperty()),
            };
        }
        $sorts = [];
        foreach ($search->getAvailableSorts() as $sort) {
            if ($sort->getKey()) {
                $sorts[] = ['value' => $name.'::'.$sort->getKey(), 'label' => $sort->getLabel()];
            }
        }

        return $this->render('bill/search.html.twig', [
            'name' => $name, 'facets' => $facets, 'sorts' => $sorts,
            'backendLabel' => self::LABELS[$backend],
            'endpoint' => $this->generateUrl('app_bill_compare_query', ['backend' => $backend]),
        ]);
    }

    #[Route('/bills/search/{backend}/query', name: 'app_bill_compare_query', requirements: ['backend' => 'duckdb|postgres|es'], methods: ['POST'])]
    public function query(string $backend, Request $request, InstantSearchGateway $gateway): JsonResponse
    {
        $this->assertBackendAvailable($backend);
        try {
            if (strlen($request->getContent()) > 65536) {
                throw new \InvalidArgumentException('Request is too large.');
            }
            $requests = $request->toArray()['requests'] ?? null;
            if (!is_array($requests)) {
                throw new \InvalidArgumentException('Search requests are required.');
            }
            foreach ($requests as $item) {
                if (!is_array($item) || !is_string($item['indexName'] ?? null)
                    || explode('::', $item['indexName'], 2)[0] !== self::SEARCHES[$backend]) {
                    throw new \InvalidArgumentException('This endpoint only accepts its own search backend.');
                }
            }

            return new JsonResponse($gateway->search($requests));
        } catch (\InvalidArgumentException|\Symfony\Component\HttpFoundation\Exception\JsonException $error) {
            return new JsonResponse(['message' => $error->getMessage()], 400);
        }
    }
}
