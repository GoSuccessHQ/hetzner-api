<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Model\Location;

/**
 * The locations (`GET /locations`).
 *
 * @extends Listing<Location>
 */
final class Locations extends Listing
{
    protected const string PATH = '/locations';

    protected const string ITEMS = 'locations';

    protected const string ITEM = 'location';

    /**
     * One location; null if Hetzner does not know it.
     */
    public function get(int $id): ?Location
    {
        return $this->find($id);
    }

    protected function map(array $data, string $path): Location
    {
        return Location::fromJson($data, $path);
    }
}
