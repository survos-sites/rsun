<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Bill;
use Survos\SearchBundle\Search\SearchProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BillSearchController extends AbstractController
{
    #[Route('/bills/{id}', name: 'app_bill_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Bill $bill): Response
    {
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
        $search = $provider->getSearch(self::SEARCH)->create();
        $available = array_map(static fn ($facet) => $facet->getProperty(), $search->getFacets());
        $sorts = array_map(
            static fn ($sort) => ['value' => self::SEARCH.'::'.$sort->getKey(), 'label' => $sort->getLabel()],
            $search->getAvailableSorts(),
        );

        return $this->render('bill/search.html.twig', [
            'name' => self::SEARCH,
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
