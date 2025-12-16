<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Entity\Partner;
use App\Repository\PartnerRepository;

/**
 * Basic Usage Example
 *
 * Simple query with 2-3 relations.
 * Perfect for getting started.
 */

// Get repository (via Symfony container in real app)
/** @var PartnerRepository $partnerRepository */
$partnerRepository = $container->get(PartnerRepository::class);

// Simple aggregated query
$partners = $partnerRepository->aggregatedQuery()
    ->withJsonRelation('profile', ['id', 'name', 'email'])
    ->withJsonRelation('country', ['id', 'name', 'code'])
    ->getResult();

// Display results
foreach ($partners as $partner) {
    printf(
        "Partner: %s\n",
        $partner['name']
    );

    if ($partner['profile']) {
        printf(
            "  Profile: %s (%s)\n",
            $partner['profile']['name'],
            $partner['profile']['email']
        );
    }

    if ($partner['country']) {
        printf(
            "  Country: %s (%s)\n",
            $partner['country']['name'],
            $partner['country']['code']
        );
    }

    echo "\n";
}

/**
 * Output:
 *
 * Partner: Acme Corp
 *   Profile: John Doe (john@acme.com)
 *   Country: USA (US)
 *
 * Partner: TechStart Inc
 *   Profile: Jane Smith (jane@techstart.io)
 *   Country: Armenia (AM)
 */