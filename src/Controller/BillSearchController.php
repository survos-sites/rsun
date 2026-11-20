<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BillRepository;
use Survos\SearchBundle\Search\SearchProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BillSearchController extends AbstractController
{
    #[Route('/bills/{id}', name: 'app_bill_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, BillRepository $repository): Response
    {
        $bill = $repository->find($id) ?? throw $this->createNotFoundException('Bill not found');
        return $this->render('bill/show.html.twig', [
            'bill' => $bill,
        ]);
    }

    private const string SEARCH = 'app_bill';

    private const array FACETS = [
        'year' => 'Session year',
        'chamber' => 'Chamber',
        'status' => 'Status',
        'outcome' => 'Outcome',
        'patronParty' => 'Patron party',
        'committeeShortname' => 'Committee',
    ];

    #[Route('/bills/search', name: 'app_bill_search', methods: ['GET'])]
    #[Route('/bills/search/ux', name: 'app_bill_ux_search', methods: ['GET'])]
    public function search(SearchProvider $provider): Response
    {
        $name = 'app_bill_pg';
        $search = $provider->getSearch($name)->create();
        $available = array_map(static fn ($facet) => $facet->getProperty(), $search->getFacets());
        $sorts = array_map(
            static fn ($sort) => ['value' => $name.'::'.$sort->getKey(), 'label' => $sort->getLabel()],
            array_values(array_filter($search->getAvailableSorts(), static fn ($sort) => null !== $sort->getKey() && '' !== $sort->getKey())),
        );

        return $this->render('bill/search.html.twig', [
            'name' => $name,
            'backendLabel' => 'app_bill_es' === $name ? 'Elasticsearch / BM25' : 'PostgreSQL FTS / BM25',
            'facets' => array_intersect_key(self::FACETS, array_flip($available)),
            'sorts' => $sorts,
        ]);
    }

    #[Route('/search-template/bill', name: 'app_bill_search_template', methods: ['GET'])]
    public function template(): Response
    {
        return new Response(
            file_get_contents($this->getParameter('kernel.project_dir').'/templates/bill/bill.browser.twig'),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=300'],
        );
    }
}
