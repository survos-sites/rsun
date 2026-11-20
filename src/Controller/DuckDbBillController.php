<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DuckDbBillController extends AbstractController
{
    #[Route('/bills/duckdb', name: 'app_bill_duckdb', methods: ['GET'])]
    public function search(): Response
    {
        return $this->redirectToRoute('app_bill_search');
    }

    #[Route('/bills/duckdb/{id}', name: 'app_bill_duckdb_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        return $this->redirectToRoute('app_bill_show', ['id' => $id]);
    }
}
