<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use GoSuccess\Hetzner\Json;

/**
 * The project's prices: currency, VAT, the backup surcharge and the primary IPs.
 * Server type prices come with the server types.
 */
final readonly class Pricing
{
    /**
     * @param string               $vatRate                Percent, e.g. `"19.00"`.
     * @param string               $serverBackupPercentage Surcharge on the server price, e.g. `"20.00"`.
     * @param list<PrimaryIpPrice> $primaryIps
     */
    public function __construct(
        public string $currency,
        public string $vatRate,
        public string $serverBackupPercentage,
        public array $primaryIps,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        $primaryIps = [];

        foreach (Json::list($data, 'primary_ips', $path) as $index => $type) {
            $primaryIps[] = PrimaryIpPrice::fromJson($type, "{$path}.primary_ips[{$index}]");
        }

        return new self(
            Json::string($data, 'currency', $path),
            Json::string($data, 'vat_rate', $path),
            Json::string(Json::object($data, 'server_backup', $path), 'percentage', "{$path}.server_backup"),
            $primaryIps,
        );
    }
}
