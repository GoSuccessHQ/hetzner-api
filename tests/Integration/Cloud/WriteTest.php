<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Integration\Cloud;

use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Cloud\Model\ActionStatus;
use GoSuccess\Hetzner\Cloud\Model\CreateServer;
use GoSuccess\Hetzner\Cloud\Model\FirewallDirection;
use GoSuccess\Hetzner\Cloud\Model\FirewallProtocol;
use GoSuccess\Hetzner\Cloud\Model\FirewallRule;
use GoSuccess\Hetzner\Cloud\Model\FirewallTarget;
use GoSuccess\Hetzner\Cloud\Model\ServerType;
use GoSuccess\Hetzner\Exception\ApiException;
use GoSuccess\Hetzner\Tests\Support\TestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Creates, changes and deletes resources in the empty test project: one
 * firewall, and one server of the cheapest available type, IPv6 only and not
 * started, for a few minutes. Needs HETZNER_CLOUD_TOKEN and
 * HETZNER_ALLOW_WRITES=1; everything is named `gosuccess-test-*` and swept
 * before and after each test.
 */
#[CoversNothing]
final class WriteTest extends TestCase
{
    private const float ACTION_TIMEOUT = 300.0;

    private ?HetznerCloud $cloud = null;

    protected function setUp(): void
    {
        $cloud = TestProject::cloud(writes: true);

        if (\is_string($cloud)) {
            self::markTestSkipped($cloud);
        }

        $foreign = TestProject::foreignResources($cloud);

        if ($foreign !== null) {
            self::markTestSkipped($foreign);
        }

        $this->cloud = $cloud;
        TestProject::sweep($cloud);
    }

    protected function tearDown(): void
    {
        if ($this->cloud !== null) {
            TestProject::sweep($this->cloud);
        }
    }

    public function testFirewallLifecycle(): void
    {
        $cloud = $this->cloud();
        $created = $cloud->firewalls()->create(
            TestProject::name('fw'),
            [new FirewallRule(FirewallDirection::In, FirewallProtocol::Tcp, '22', ['192.0.2.0/24'], description: 'test')],
            labels: ['gosuccess-test' => '1'],
        );
        $this->waitAll($created->actions);
        $id = $created->firewall->id;

        $this->waitAll($cloud->firewalls()->setRules($id, [
            new FirewallRule(FirewallDirection::In, FirewallProtocol::Tcp, '80-443', ['0.0.0.0/0', '::/0']),
            new FirewallRule(FirewallDirection::In, FirewallProtocol::Icmp, sourceIps: ['0.0.0.0/0']),
        ]));
        $this->waitAll($cloud->firewalls()->applyToResources($id, [FirewallTarget::labelSelector('gosuccess-test=1')]));

        $firewall = $cloud->firewalls()->get($id);
        self::assertNotNull($firewall);
        self::assertCount(2, $firewall->rules);
        self::assertSame('80-443', $firewall->rules[0]->port);
        self::assertNull($firewall->rules[1]->port);
        self::assertEquals([FirewallTarget::labelSelector('gosuccess-test=1')], $firewall->appliedTo);
        self::assertSame(['gosuccess-test' => '1'], $firewall->labels);

        $this->waitAll($cloud->firewalls()->removeFromResources($id, [FirewallTarget::labelSelector('gosuccess-test=1')]));
        self::assertSame([], $cloud->firewalls()->get($id)?->appliedTo);
        self::assertTrue($cloud->firewalls()->delete($id));
        self::assertFalse($cloud->firewalls()->delete($id));
    }

    public function testFirewallOnASelectorWithoutServersCanBeDeleted(): void
    {
        $cloud = $this->cloud();
        $created = $cloud->firewalls()->create(TestProject::name('fw'), applyTo: [FirewallTarget::labelSelector('gosuccess-test=nothing')]);
        $this->waitAll($created->actions);

        // Measured 2026-10-18: resource_in_use only while the selector covers a server.
        self::assertTrue($cloud->firewalls()->delete($created->firewall->id));
    }

    public function testServerLifecycle(): void
    {
        $cloud = $this->cloud();
        [$type, $location] = $this->cheapest();
        $image = null;

        foreach ($cloud->images()->all(['type' => 'system', 'name' => 'ubuntu-24.04', 'architecture' => $type->architecture]) as $candidate) {
            $image = $candidate;
        }

        self::assertNotNull($image, "No ubuntu-24.04 for {$type->architecture}.");

        $created = $cloud->servers()->create(new CreateServer(
            name: TestProject::name('srv'),
            serverType: $type->name,
            image: (string) $image->id,
            location: $location,
            labels: ['gosuccess-test' => '1'],
            enableIpv4: false,
            startAfterCreate: false,
        ));
        $id = $created->server->id;
        self::assertSame(ActionStatus::Success, $cloud->actions()->wait($created->action, self::ACTION_TIMEOUT)->status);
        $this->waitAll($created->nextActions);

        $server = $cloud->servers()->get($id);
        self::assertNotNull($server);
        self::assertNull($server->ipv4);
        self::assertNotNull($server->ipv6);
        self::assertSame($location, $server->location);
        self::assertSame($type->name, $server->serverType);

        self::assertSame(['gosuccess-test' => '1', 'step' => 'labels'], $cloud->servers()->updateLabels($id, ['gosuccess-test' => '1', 'step' => 'labels'])->labels);

        $this->firewallCoveringTheServer($id);

        self::assertSame(ActionStatus::Success, $cloud->actions()->wait($cloud->servers()->enableBackup($id), self::ACTION_TIMEOUT)->status);
        self::assertNotNull($cloud->servers()->get($id)?->backupWindow);

        self::assertSame(ActionStatus::Success, $cloud->actions()->wait($cloud->servers()->changeProtection($id, true, true), self::ACTION_TIMEOUT)->status);

        try {
            $cloud->servers()->delete($id);
            self::fail('A protected server was deleted.');
        } catch (ApiException $e) {
            self::assertSame('protected', $e->errorCode);
        }

        self::assertSame(ActionStatus::Success, $cloud->actions()->wait($cloud->servers()->changeProtection($id, false, false), self::ACTION_TIMEOUT)->status);
        self::assertTrue($cloud->servers()->delete($id));
    }

    /**
     * A firewall on the test label covers the server once Hetzner matched it
     * (a few seconds), and cannot be deleted while it does.
     */
    private function firewallCoveringTheServer(int $serverId): void
    {
        $cloud = $this->cloud();
        $created = $cloud->firewalls()->create(TestProject::name('fw'), applyTo: [FirewallTarget::labelSelector('gosuccess-test=1')]);
        $this->waitAll($created->actions);
        $id = $created->firewall->id;
        $covered = [];

        for ($attempt = 0; $attempt < 15 && $covered === []; $attempt++) {
            sleep(2);
            $covered = $cloud->firewalls()->get($id)?->serverIds() ?? [];
        }

        self::assertSame([$serverId], $covered);

        try {
            $cloud->firewalls()->delete($id);
            self::fail('A firewall covering a server was deleted.');
        } catch (ApiException $e) {
            self::assertSame('resource_in_use', $e->errorCode);
        }

        $this->waitAll($cloud->firewalls()->removeFromResources($id, [FirewallTarget::labelSelector('gosuccess-test=1')]));
        self::assertTrue($cloud->firewalls()->delete($id));
    }

    /**
     * The server type with the lowest monthly gross price in a location where
     * it can be ordered now, and that location.
     *
     * @return array{ServerType, string}
     */
    private function cheapest(): array
    {
        $best = null;

        foreach ($this->cloud()->serverTypes()->all() as $type) {
            if ($type->deprecation !== null) {
                continue;
            }

            foreach ($type->locations as $location) {
                if (!$location->available || $location->deprecation !== null) {
                    continue;
                }

                foreach ($type->prices as $price) {
                    if ($price->location === $location->name && ($best === null || (float) $price->monthlyGross < $best[2])) {
                        $best = [$type, $location->name, (float) $price->monthlyGross];
                    }
                }
            }
        }

        self::assertNotNull($best, 'No server type is available anywhere.');

        return [$best[0], $best[1]];
    }

    /**
     * @param list<\GoSuccess\Hetzner\Cloud\Model\Action> $actions
     */
    private function waitAll(array $actions): void
    {
        foreach ($actions as $action) {
            self::assertSame(ActionStatus::Success, $this->cloud()->actions()->wait($action, self::ACTION_TIMEOUT)->status, "Action {$action->command} failed.");
        }
    }

    private function cloud(): HetznerCloud
    {
        self::assertNotNull($this->cloud);

        return $this->cloud;
    }
}
