<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Bill;
use Survos\FieldBundle\Registry\EntityMetaRegistry;
use Survos\SearchBundle\Registry\UxSearchRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BillSearchController extends AbstractController
{
    #[Route('/bills/search', name: 'app_bill_meili_search', methods: ['GET'])]
    public function meili(): Response
    {
        return $this->render('bill/search_meili.html.twig');
    }

    #[Route('/bills/search/ux', name: 'app_bill_ux_search', methods: ['GET'])]
    public function ux(
        EntityMetaRegistry $entityMetaRegistry,
        UxSearchRegistry $uxSearchRegistry,
    ): Response {
        return $this->render('bill/search_ux.html.twig', [
            'descriptor' => $entityMetaRegistry->get(Bill::class),
            'search' => $uxSearchRegistry->forClass(Bill::class),
        ]);
    }
}
