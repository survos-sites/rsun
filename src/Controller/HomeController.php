<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Survos\FieldBundle\Model\EntityMetaDescriptor;
use Survos\FieldBundle\Registry\EntityMetaRegistry;
use Survos\MeiliBundle\Registry\MeiliRegistry;
use Survos\SearchBundle\Registry\UxSearchRegistry;
use Survos\StateBundle\Service\WorkflowHelperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly EntityMetaRegistry $entityMetaRegistry,
        private readonly ManagerRegistry $doctrine,
        private readonly RouterInterface $router,
        private readonly UxSearchRegistry $uxSearchRegistry,
        private readonly MeiliRegistry $meiliRegistry,
        private readonly WorkflowHelperService $workflowHelper,
    ) {}

    #[Route('/', name: 'app_homepage')]
    public function __invoke(): Response
    {
        return $this->render('home/index.html.twig', [
            'entityCards' => array_map(
                fn (EntityMetaDescriptor $descriptor): array => $this->entityCard($descriptor),
                $this->entityMetaRegistry->getBrowsable(),
            ),
        ]);
    }

    private function entityCard(EntityMetaDescriptor $descriptor): array
    {
        $class = $descriptor->class;
        $meiliBaseName = $this->meiliBaseNameForClass($class);
        $workflows = $this->workflowsForClass($class);

        return [
            'descriptor' => $descriptor,
            'rowCount' => $this->rowCount($class),
            'dashboardUrl' => $this->routeUrl('survos_entity_dashboard', ['code' => $descriptor->code]),
            'uxSearchUrl' => $this->uxSearchRegistry->forClass($class)
                ? $this->routeUrl('survos_entity_ux_search', ['code' => $descriptor->code])
                : null,
            'meiliSearchUrl' => $meiliBaseName
                ? $this->routeUrl('meili_insta', ['indexName' => $meiliBaseName])
                : null,
            'meiliDiagnosticsUrl' => $meiliBaseName
                ? $this->routeUrl('meili_admin_meili_index_dashboard', ['indexName' => $meiliBaseName])
                : null,
            'workflows' => array_map(fn (string $name): array => [
                'name' => $name,
                'url' => $this->routeUrl('survos_workflow', ['flowCode' => $name]),
            ], $workflows),
        ];
    }

    private function rowCount(string $class): ?int
    {
        $manager = $this->doctrine->getManagerForClass($class);
        if (!$manager) {
            return null;
        }

        try {
            return (int) $manager->getRepository($class)->count([]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function meiliBaseNameForClass(string $class): ?string
    {
        foreach ($this->meiliRegistry->names() as $baseName) {
            if ($this->meiliRegistry->classFor((string) $baseName) === $class) {
                return (string) $baseName;
            }
        }

        return null;
    }

    private function workflowsForClass(string $class): array
    {
        $grouped = $this->workflowHelper->getWorkflowsGroupedByClass();
        $workflows = $grouped[$class] ?? [];
        foreach ($grouped as $supportedClass => $names) {
            if ($supportedClass !== $class && is_a($class, (string) $supportedClass, true)) {
                $workflows = array_merge($workflows, (array) $names);
            }
        }

        return array_values(array_unique($workflows));
    }

    private function routeUrl(string $route, array $parameters = []): ?string
    {
        if (!$this->router->getRouteCollection()->get($route)) {
            return null;
        }

        try {
            return $this->router->generate($route, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }
}
