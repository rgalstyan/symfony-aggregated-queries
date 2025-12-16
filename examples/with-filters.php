<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Repository\PartnerRepository;

/**
 * Filtering and Sorting Example
 *
 * Apply WHERE conditions, sorting, and pagination.
 * Perfect for search pages and filtered listings.
 */

/** @var PartnerRepository $partnerRepository */
$partnerRepository = $container->get(PartnerRepository::class);

// Example 1: Basic filtering
echo "=== Active Partners Only ===\n\n";

$activePartners = $partnerRepository->aggregatedQuery()
    ->withJsonRelation('profile', ['id', 'name'])
    ->withJsonRelation('country', ['id', 'name'])
    ->where('status', 'active')
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->getResult();

foreach ($activePartners as $partner) {
    printf(
        "%s - %s (%s)\n",
        $partner['name'],
        $partner['profile']['name'] ?? 'N/A',
        $partner['country']['name'] ?? 'N/A'
    );
}

// Example 2: Multiple conditions
echo "\n=== Premium Partners in USA/Canada ===\n\n";

$premiumPartners = $partnerRepository->aggregatedQuery()
    ->withJsonRelation('profile')
    ->withJsonRelation('country')
    ->withCount('orders')
    ->where('status', 'active')
    ->where('typeId', 1) // Premium type
    ->whereIn('countryId', [1, 2]) // USA, Canada
    ->orderBy('createdAt', 'DESC')
    ->limit(20)
    ->getResult();

foreach ($premiumPartners as $partner) {
    printf(
        "%s - %d orders - %s\n",
        $partner['name'],
        $partner['orders_count'],
        $partner['country']['name']
    );
}

// Example 3: Pagination
echo "\n=== Paginated Results ===\n\n";

$page = 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$paginatedPartners = $partnerRepository->aggregatedQuery()
    ->withJsonRelation('profile')
    ->where('status', 'active')
    ->orderBy('name', 'ASC')
    ->limit($perPage)
    ->offset($offset)
    ->getResult();

printf("Page %d (%d records):\n", $page, count($paginatedPartners));
foreach ($paginatedPartners as $partner) {
    printf("  - %s\n", $partner['name']);
}

// Example 4: Search by name (case-insensitive)
echo "\n=== Search: 'tech' in name ===\n\n";

$searchResults = $partnerRepository->aggregatedQuery()
    ->withJsonRelation('profile')
    ->withJsonRelation('country')
    ->where('name', '%tech%', 'LIKE')
    ->orderBy('name', 'ASC')
    ->getResult();

foreach ($searchResults as $partner) {
    printf(
        "%s - %s\n",
        $partner['name'],
        $partner['country']['name'] ?? 'N/A'
    );
}

/**
 * Output:
 *
 * === Active Partners Only ===
 *
 * Acme Corp - John Doe (USA)
 * Beta Systems - Jane Smith (Canada)
 * TechStart Inc - Bob Johnson (Armenia)
 *
 * === Premium Partners in USA/Canada ===
 *
 * Acme Corp - 127 orders - USA
 * Beta Systems - 89 orders - Canada
 * Gamma Solutions - 45 orders - USA
 *
 * === Paginated Results ===
 *
 * Page 1 (10 records):
 *   - Acme Corp
 *   - Alpha Industries
 *   - Beta Systems
 *   ...
 *
 * === Search: 'tech' in name ===
 *
 * TechStart Inc - Armenia
 * FinTech Solutions - USA
 * HealthTech Corp - Canada
 */
