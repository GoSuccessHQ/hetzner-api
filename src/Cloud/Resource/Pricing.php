<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Model\Pricing as PricingModel;
use GoSuccess\Hetzner\Http\Connection;
use GoSuccess\Hetzner\Http\Method;
use GoSuccess\Hetzner\Json;

/**
 * The prices of the project (`GET /pricing`).
 */
final class Pricing
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection) {}

    /**
     * Currency, VAT, backup surcharge and primary IP prices.
     */
    public function get(): PricingModel
    {
        return PricingModel::fromJson(Json::object($this->connection->request(Method::Get, '/pricing'), 'pricing', ''), 'pricing');
    }
}
