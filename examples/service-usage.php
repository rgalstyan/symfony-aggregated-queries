<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Service\PartnerService;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Service Layer Usage Example
 *
 * How to use aggregated queries in Symfony services and controllers.
 * Perfect for understanding architecture and best practices.
 */

// Example Service
class PartnerApiService
{
    public function __construct(
        private readonly PartnerRepository $partnerRepository
    ) {}

    /**
     * Get partners for API response.
     */
    public function getAllPartnersForApi(): array
    {
        $partners = $this->partnerRepository->aggregatedQuery()
            ->withJsonRelation('profile', ['id', 'name', 'email'])
            ->withJsonRelation('country', ['id', 'name', 'code'])
            ->withJsonCollection('promocodes', ['id', 'code', 'discount'])
            ->withCount('promocodes')
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->limit(100)
            ->getResult();

        // Transform to API format
        return array_map(function ($partner) {
            return [
                'id' => $partner['id'],
                'name' => $partner['name'],
                'profile' => $partner['profile'] ? [
                    'name' => $partner['profile']['name'],
                    'email' => $partner['profile']['email'],
                ] : null,
                'country' => $partner['country'] ? [
                    'code' => $partner['country']['code'],
                    'name' => $partner['country']['name'],
                ] : null,
                'promocodes' => array_map(fn($p) => [
                    'code' => $p['code'],
                    'discount' => $p['discount'] . '%',
                ], $partner['promocodes']),
                'stats' => [
                    'promocode_count' => $partner['promocodes_count'],
                ],
            ];
        }, $partners);
    }

    /**
     * Get partner statistics.
     */
    public function getPartnerStats(): array
    {
        $partners = $this->partnerRepository->aggregatedQuery()
            ->withCount('orders')
            ->withCount('promocodes')
            ->withCount('discountRules')
            ->where('status', 'active')
            ->getResult();

        $totalOrders = array_sum(array_column($partners, 'orders_count'));
        $totalPromocodes = array_sum(array_column($partners, 'promocodes_count'));
        $avgOrders = count($partners) > 0 ? $totalOrders / count($partners) : 0;

        return [
            'total_partners' => count($partners),
            'total_orders' => $totalOrders,
            'total_promocodes' => $totalPromocodes,
            'avg_orders_per_partner' => round($avgOrders, 2),
        ];
    }
}

// Example Controller
class PartnerController extends AbstractController
{
    public function __construct(
        private readonly PartnerApiService $partnerApiService
    ) {}

    #[Route('/api/partners', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $partners = $this->partnerApiService->getAllPartnersForApi();

        return $this->json([
            'success' => true,
            'data' => $partners,
            'meta' => [
                'count' => count($partners),
                'query_time' => '4ms', // Much faster than traditional
            ],
        ]);
    }

    #[Route('/api/partners/stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $stats = $this->partnerApiService->getPartnerStats();

        return $this->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}

// Example Repository Method
class PartnerRepository extends ServiceEntityRepository
{
    use AggregatedRepositoryTrait;

    /**
     * Get partners for dashboard.
     */
    public function findForDashboard(): array
    {
        return $this->aggregatedQuery()
            ->withJsonRelation('profile', ['id', 'name', 'avatar'])
            ->withJsonRelation('country', ['id', 'name', 'flag'])
            ->withJsonRelation('type', ['id', 'name'])
            ->withCount('orders')
            ->withCount('promocodes')
            ->where('status', 'active')
            ->orderBy('createdAt', 'DESC')
            ->limit(50)
            ->getResult();
    }

    /**
     * Get partner by ID with all relations.
     */
    public function findOneOptimized(int $id): ?array
    {
        $results = $this->aggregatedQuery()
            ->withJsonRelation('profile')
            ->withJsonRelation('country')
            ->withJsonRelation('type')
            ->withJsonCollection('promocodes')
            ->withJsonCollection('discountRules')
            ->withCount('orders')
            ->where('id', $id)
            ->getResult();

        return $results[0] ?? null;
    }
}

/**
 * API Response Example:
 *
 * GET /api/partners
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 1,
 *       "name": "Acme Corp",
 *       "profile": {
 *         "name": "John Doe",
 *         "email": "john@acme.com"
 *       },
 *       "country": {
 *         "code": "US",
 *         "name": "USA"
 *       },
 *       "promocodes": [
 *         {"code": "SAVE10", "discount": "10%"},
 *         {"code": "SAVE20", "discount": "20%"}
 *       ],
 *       "stats": {
 *         "promocode_count": 5
 *       }
 *     }
 *   ],
 *   "meta": {
 *     "count": 50,
 *     "query_time": "4ms"
 *   }
 * }
 *
 * GET /api/partners/stats
 * {
 *   "success": true,
 *   "data": {
 *     "total_partners": 150,
 *     "total_orders": 12847,
 *     "total_promocodes": 450,
 *     "avg_orders_per_partner": 85.65
 *   }
 * }
 */