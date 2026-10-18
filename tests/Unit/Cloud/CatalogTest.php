<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit\Cloud;

use DateTimeImmutable;
use GoSuccess\Hetzner\ClientOptions;
use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Cloud\Model\Deprecation;
use GoSuccess\Hetzner\Cloud\Model\Image;
use GoSuccess\Hetzner\Cloud\Model\Location;
use GoSuccess\Hetzner\Cloud\Model\Network;
use GoSuccess\Hetzner\Cloud\Model\PlacementGroup;
use GoSuccess\Hetzner\Cloud\Model\Price;
use GoSuccess\Hetzner\Cloud\Model\Pricing;
use GoSuccess\Hetzner\Cloud\Model\PrimaryIpPrice;
use GoSuccess\Hetzner\Cloud\Model\ServerType;
use GoSuccess\Hetzner\Cloud\Model\ServerTypeLocation;
use GoSuccess\Hetzner\Cloud\Model\SshKey;
use GoSuccess\Hetzner\Cloud\Model\Subnet;
use GoSuccess\Hetzner\Cloud\Resource\Listing;
use GoSuccess\Hetzner\Exception\NotFoundException;
use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Http\Response;
use GoSuccess\Hetzner\Tests\Support\MockHttpClient;
use GoSuccess\Hetzner\Tests\Support\SpecExample;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HetznerCloud::class)]
#[CoversClass(Listing::class)]
#[CoversClass(Location::class)]
#[CoversClass(ServerType::class)]
#[CoversClass(ServerTypeLocation::class)]
#[CoversClass(Price::class)]
#[CoversClass(Deprecation::class)]
#[CoversClass(Image::class)]
#[CoversClass(Network::class)]
#[CoversClass(Subnet::class)]
#[CoversClass(PlacementGroup::class)]
#[CoversClass(SshKey::class)]
#[CoversClass(Pricing::class)]
#[CoversClass(PrimaryIpPrice::class)]
final class CatalogTest extends TestCase
{
    public function testLocations(): void
    {
        [$cloud, $http] = $this->cloud(SpecExample::json('list_locations'));

        $locations = iterator_to_array($cloud->locations()->all(), false);

        self::assertEquals([new Location(42, 'fsn1', 'Falkenstein DC Park 1', 'DE', 'Falkenstein', 50.47612, 12.370071, 'eu-central')], $locations);
        self::assertSame('https://api.hetzner.cloud/v1/locations?page=1&per_page=50', $http->requests[0]->uri);
    }

    public function testOnePageOfLocations(): void
    {
        [$cloud, $http] = $this->cloud(SpecExample::json('list_locations'));

        $page = $cloud->locations()->list(page: 2, perPage: 1, filters: ['name' => 'fsn1']);

        self::assertCount(1, $page->items);
        self::assertSame('https://api.hetzner.cloud/v1/locations?name=fsn1&page=2&per_page=1', $http->requests[0]->uri);
    }

    public function testGetLocation(): void
    {
        [$cloud, $http] = $this->cloud(SpecExample::json('get_location'));

        self::assertSame('fsn1', $cloud->locations()->get(42)?->name);
        self::assertSame('https://api.hetzner.cloud/v1/locations/42', $http->requests[0]->uri);
    }

    public function testMissingLocationIsNull(): void
    {
        [$cloud] = $this->cloud(new Response(404, '{"error":{"code":"not_found","message":"location not found","details":{}}}'));

        self::assertNull($cloud->locations()->get(42));
    }

    public function testA404WithoutCodeIsNoProofOfAbsence(): void
    {
        [$cloud] = $this->cloud(new Response(404, '<html>Not Found</html>'));

        $this->expectException(NotFoundException::class);

        $cloud->locations()->get(42);
    }

    public function testServerTypesWithLocationsAndPrices(): void
    {
        [$cloud] = $this->cloud(SpecExample::json('list_server_types'));

        $type = iterator_to_array($cloud->serverTypes()->all(), false)[0];
        $deprecation = new Deprecation(new DateTimeImmutable('2023-06-01T00:00:00Z'), new DateTimeImmutable('2023-09-01T00:00:00Z'));

        self::assertSame(1, $type->id);
        self::assertSame('cpx22', $type->name);
        self::assertSame('CPX22', $type->description);
        self::assertSame(2, $type->cores);
        self::assertSame(4.0, $type->memory);
        self::assertSame(80, $type->disk);
        self::assertSame('shared', $type->cpuType);
        self::assertSame('local', $type->storageType);
        self::assertSame('x86', $type->architecture);
        self::assertEquals($deprecation, $type->deprecation);
        self::assertEquals([new Price('fsn1', '1.0000', '1.1900', '1.0000', '1.1900')], $type->prices);
        self::assertEquals([new ServerTypeLocation(42, 'fsn1', $deprecation, false, true)], $type->locations);
    }

    public function testServerTypeWithoutDeprecation(): void
    {
        $data = SpecExample::with(SpecExample::response('list_server_types'), 'server_types.0.deprecation', null);
        $data = SpecExample::with($data, 'server_types.0.locations.0.deprecation', null);
        [$cloud] = $this->cloud((string) json_encode($data));

        $type = iterator_to_array($cloud->serverTypes()->all(), false)[0];

        self::assertNull($type->deprecation);
        self::assertNull($type->locations[0]->deprecation);
    }

    public function testSystemImagesOnly(): void
    {
        [$cloud, $http] = $this->cloud(SpecExample::json('list_images'));

        $image = iterator_to_array($cloud->images()->all(['type' => 'system', 'architecture' => 'x86']), false)[0];

        self::assertSame('https://api.hetzner.cloud/v1/images?type=system&architecture=x86&page=1&per_page=50', $http->requests[0]->uri);
        self::assertSame(42, $image->id);
        self::assertSame('ubuntu-24.04', $image->name);
        self::assertSame('ubuntu', $image->osFlavor);
        self::assertSame('x86', $image->architecture);
        self::assertSame('snapshot', $image->type);
        self::assertSame('24.04', $image->osVersion);
        self::assertSame(['environment' => 'prod', 'example.com/my' => 'label', 'just-a-key' => ''], $image->labels);
        self::assertSame('available', $image->status);
    }

    public function testNetworksWithSubnets(): void
    {
        [$cloud] = $this->cloud(SpecExample::json('list_networks'));

        $network = iterator_to_array($cloud->networks()->all(), false)[0];

        self::assertSame('10.0.0.0/16', $network->ipRange);
        self::assertEquals([new Subnet('cloud', '10.0.1.0/24', 'eu-central', '10.0.0.1')], $network->subnets);
        self::assertSame([42], $network->servers);
    }

    public function testPlacementGroups(): void
    {
        [$cloud] = $this->cloud(SpecExample::json('list_placement_groups'));

        $group = iterator_to_array($cloud->placementGroups()->all(), false)[0];

        self::assertSame('spread', $group->type);
        self::assertSame([42], $group->servers);
    }

    public function testSshKeys(): void
    {
        [$cloud] = $this->cloud(SpecExample::json('list_ssh_keys'));

        $key = iterator_to_array($cloud->sshKeys()->all(), false)[0];

        self::assertSame('my-resource', $key->name);
        self::assertStringStartsWith('b7:2f:30', $key->fingerprint);
        self::assertStringStartsWith('ssh-rsa ', $key->publicKey);
        self::assertSame('2016-01-30T23:55:00+00:00', $key->created->format(\DATE_ATOM));
    }

    public function testPricing(): void
    {
        [$cloud, $http] = $this->cloud(SpecExample::json('get_pricing'));

        $pricing = $cloud->pricing()->get();

        self::assertSame('https://api.hetzner.cloud/v1/pricing', $http->requests[0]->uri);
        self::assertSame('EUR', $pricing->currency);
        self::assertSame('19.00', $pricing->vatRate);
        self::assertSame('20.00', $pricing->serverBackupPercentage);
        self::assertEquals([new PrimaryIpPrice('ipv4', [new Price('fsn1', '1.0000', '1.1900', '1.0000', '1.1900')])], $pricing->primaryIps);
    }

    public function testMalformedItemNamesItsPath(): void
    {
        $data = SpecExample::with(SpecExample::response('list_server_types'), 'server_types.0.locations.0.name', 7);
        [$cloud] = $this->cloud((string) json_encode($data));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('server_types[0].locations[0].name');

        iterator_to_array($cloud->serverTypes()->all());
    }

    /**
     * @return array{HetznerCloud, MockHttpClient}
     */
    private function cloud(Response|string ...$responses): array
    {
        $http = new MockHttpClient(...array_map(static fn(Response|string $r): Response => \is_string($r) ? new Response(200, $r) : $r, $responses));

        return [new HetznerCloud('token', new ClientOptions(maxRetries: 0), $http, static function (float $seconds): void {}), $http];
    }
}
