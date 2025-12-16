<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Repository\PartnerRepository;

/**
 * Multiple Relations Example
 *
 * Load multiple relations and counts in a single query.
 * Perfect for dashboards and complex listings.
 */

/** @var PartnerRepository $partnerRepository */
$partnerRepository = $container->get(PartnerRepository::class);

// Load multiple relations + collections + counts
$partners = $partnerRepository->aggregatedQuery()
    ->withJsonRelation('profile', ['id', 'name', 'email', 'avatar'])
    ->withJsonRelation('country', ['id', 'name', 'code'])
    ->withJsonRelation('type', ['id', 'name'])
    ->withJsonCollection('promocodes', ['id', 'code', 'discount'])
    ->withCount('promocodes')
    ->withCount('discountRules')
    ->withCount('orders')
    ->getResult();

// Display results
foreach ($partners as $partner) {
    printf("Partner: %s\n", $partner['name']);
    printf("  Type: %s\n", $partner['type']['name'] ?? 'N/A');
    printf("  Country: %s\n", $partner['country']['name'] ?? 'N/A');

    // Profile
    if ($partner['profile']) {
        printf("  Profile: %s\n", $partner['profile']['name']);
        printf("  Email: %s\n", $partner['profile']['email']);
    }

    // Counts
    printf("  Promocodes: %d\n", $partner['promocodes_count']);
    printf("  Discount Rules: %d\n", $partner['discount_rules_count']);
    printf("  Orders: %d\n", $partner['orders_count']);

    // Collection
    if (count($partner['promocodes']) > 0) {
        echo "  Active Promocodes:\n";
        foreach ($partner['promocodes'] as $promo) {
            printf(
                "    - %s (%d%% off)\n",
                $promo['code'],
                $promo['discount']
            );
        }
    }

    echo "\n";
}

/**
 * Output:
 *
 * Partner: Acme Corp
 *   Type: Premium
 *   Country: USA
 *   Profile: John Doe
 *   Email: john@acme.com
 *   Promocodes: 5
 *   Discount Rules: 3
 *   Orders: 127
 *   Active Promocodes:
 *     - SAVE10 (10% off)
 *     - SAVE20 (20% off)
 *     - SUMMER2024 (15% off)
 *
 * Performance:
 *   - Traditional Doctrine: 8 queries
 *   - Aggregated Query: 1 query
 *   - Improvement: 87.5% fewer queries
 */