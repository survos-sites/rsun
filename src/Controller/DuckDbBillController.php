<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DuckDbBills;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DuckDbBillController extends AbstractController
{
    #[Route('/bills/duckdb', name: 'app_bill_duckdb', methods: ['GET'])]
    public function search(Request $request, DuckDbBills $bills): Response
    {
        $query = mb_substr(trim($request->query->getString('q')), 0, 300);
        $year = $request->query->getInt('year') ?: null;
        $chamber = $request->query->getString('chamber');

        return $this->render('bill/duckdb.html.twig', [
            'query' => $query, 'year' => $year, 'chamber' => $chamber,
            'result' => $bills->search($query, $year, $chamber, $request->query->getInt('page', 1)),
            'metadata' => $bills->metadata(),
        ]);
    }

    #[Route('/bills/duckdb/{id}', name: 'app_bill_duckdb_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, DuckDbBills $bills): Response
    {
        $record = $bills->bill($id) ?? throw $this->createNotFoundException('Bill not found');

        return $this->render('bill/duckdb_show.html.twig', ['bill' => $record]);
    }
}
