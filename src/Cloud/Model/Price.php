<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Json;

/**
 * A price in one location. Amounts are decimal strings as Hetzner sends them
 * (`"1.1900"`), never floats.
 */
final readonly class Price
{
    public function __construct(
        public string $location,
        public string $hourlyNet,
        public string $hourlyGross,
        public string $monthlyNet,
        public string $monthlyGross,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        $hourly = Json::object($data, 'price_hourly', $path);
        $monthly = Json::object($data, 'price_monthly', $path);

        return new self(
            Json::string($data, 'location', $path),
            Json::string($hourly, 'net', "{$path}.price_hourly"),
            Json::string($hourly, 'gross', "{$path}.price_hourly"),
            Json::string($monthly, 'net', "{$path}.price_monthly"),
            Json::string($monthly, 'gross', "{$path}.price_monthly"),
        );
    }

    /**
     * @internal
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<self>
     */
    public static function listFrom(array $data, string $key, string $path): array
    {
        $prices = [];

        foreach (Json::list($data, $key, $path) as $index => $price) {
            $prices[] = self::fromJson($price, Json::join(Json::join($path, $key), $index));
        }

        return $prices;
    }
}
