<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Integration\Cloud;

use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Tests\Support\TestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Reads everything the client covers from the live API (about a dozen
 * requests). Skipped without HETZNER_CLOUD_TOKEN.
 */
#[CoversNothing]
final class ReadTest extends TestCase
{
    private HetznerCloud $cloud;

    protected function setUp(): void
    {
        $cloud = TestProject::cloud(writes: false);

        if (\is_string($cloud)) {
            self::markTestSkipped($cloud);
        }

        $this->cloud = $cloud;
    }

    public function testCatalog(): void
    {
        $locations = iterator_to_array($this->cloud->locations()->all(), false);
        self::assertNotEmpty($locations);
        self::assertSame($locations[0]->name, $this->cloud->locations()->get($locations[0]->id)?->name);
        self::assertNull($this->cloud->locations()->get(999_999_999));

        $types = iterator_to_array($this->cloud->serverTypes()->all(), false);
        self::assertNotEmpty($types);
        self::assertNotEmpty($types[0]->locations);

        $images = iterator_to_array($this->cloud->images()->all(['type' => 'system']), false);
        self::assertNotEmpty($images);

        foreach ($images as $image) {
            self::assertSame('system', $image->type);
        }

        iterator_to_array($this->cloud->networks()->all());
        iterator_to_array($this->cloud->placementGroups()->all());
        iterator_to_array($this->cloud->sshKeys()->all());

        $pricing = $this->cloud->pricing()->get();
        self::assertSame('EUR', $pricing->currency);
        self::assertNotEmpty($pricing->primaryIps);

        self::assertNotNull($this->cloud->rateLimit());
    }

    public function testServersAndFirewalls(): void
    {
        iterator_to_array($this->cloud->servers()->all());
        iterator_to_array($this->cloud->firewalls()->all());

        self::assertNull($this->cloud->servers()->get(999_999_999));
        self::assertNull($this->cloud->firewalls()->get(999_999_999));
    }
}
