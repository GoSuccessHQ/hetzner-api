<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit\Cloud;

use GoSuccess\Hetzner\ClientOptions;
use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Cloud\Model\CreatedFirewall;
use GoSuccess\Hetzner\Cloud\Model\Firewall;
use GoSuccess\Hetzner\Cloud\Model\FirewallDirection;
use GoSuccess\Hetzner\Cloud\Model\FirewallProtocol;
use GoSuccess\Hetzner\Cloud\Model\FirewallRule;
use GoSuccess\Hetzner\Cloud\Model\FirewallTarget;
use GoSuccess\Hetzner\Cloud\Model\FirewallTargetType;
use GoSuccess\Hetzner\Cloud\Resource\Firewalls;
use GoSuccess\Hetzner\Exception\ApiException;
use GoSuccess\Hetzner\Exception\NotFoundException;
use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Exception\ValidationException;
use GoSuccess\Hetzner\Http\Response;
use GoSuccess\Hetzner\Tests\Support\MockHttpClient;
use GoSuccess\Hetzner\Tests\Support\SpecExample;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Firewalls::class)]
#[CoversClass(Firewall::class)]
#[CoversClass(FirewallRule::class)]
#[CoversClass(FirewallTarget::class)]
#[CoversClass(CreatedFirewall::class)]
final class FirewallsTest extends TestCase
{
    public function testListWithRulesAndTargets(): void
    {
        $data = SpecExample::with(SpecExample::response('list_firewalls'), 'firewalls.0.applied_to', [
            ['type' => 'server', 'server' => ['id' => 7]],
            ['type' => 'label_selector', 'label_selector' => ['selector' => 'gosuccess-role=wpaas'], 'applied_to_resources' => [['type' => 'server', 'server' => ['id' => 8]], ['type' => 'server', 'server' => ['id' => 9]]]],
        ]);
        [$cloud, $http] = $this->cloud((string) json_encode($data));

        $firewall = iterator_to_array($cloud->firewalls()->all(['name' => 'gosuccess-x']), false)[0];

        self::assertSame('https://api.hetzner.cloud/v1/firewalls?name=gosuccess-x&page=1&per_page=50', $http->requests[0]->uri);
        self::assertSame(42, $firewall->id);
        self::assertSame('new-name', $firewall->name);
        self::assertSame(['environment' => 'prod', 'example.com/my' => 'label', 'just-a-key' => ''], $firewall->labels);
        self::assertEquals([new FirewallRule(FirewallDirection::In, FirewallProtocol::Tcp, '80', ['192.0.2.2/32', '192.0.2.0/24', '2001:0db8:9a3b:ee58:5ca:990c:8bc9:c03b/128'], [], 'string')], $firewall->rules);
        self::assertEquals([FirewallTarget::server(7), new FirewallTarget(FirewallTargetType::LabelSelector, null, 'gosuccess-role=wpaas', [8, 9])], $firewall->appliedTo);
        self::assertSame([7, 8, 9], $firewall->serverIds());
    }

    public function testFirewallWithoutLabels(): void
    {
        $data = SpecExample::without(SpecExample::response('get_firewall'), 'firewall.labels');
        [$cloud] = $this->cloud((string) json_encode($data));

        self::assertSame([], $cloud->firewalls()->get(38)?->labels);
    }

    public function testCreateSendsRulesTargetsAndLabelsObject(): void
    {
        [$cloud, $http] = $this->cloud(new Response(201, SpecExample::json('create_firewall')));

        $created = $cloud->firewalls()->create(
            'gosuccess-ssh',
            [
                new FirewallRule(FirewallDirection::In, FirewallProtocol::Tcp, '22', ['192.0.2.0/24'], description: 'SSH'),
                new FirewallRule(FirewallDirection::In, FirewallProtocol::Icmp, sourceIps: ['0.0.0.0/0', '::/0']),
                new FirewallRule(FirewallDirection::Out, FirewallProtocol::Udp, '53', destinationIps: ['198.51.100.1/32']),
            ],
            [FirewallTarget::labelSelector('gosuccess-role=wpaas')],
        );

        self::assertSame('POST', $http->requests[0]->method->value);
        self::assertSame('https://api.hetzner.cloud/v1/firewalls', $http->requests[0]->uri);
        self::assertSame('{"name":"gosuccess-ssh","labels":{},"rules":['
            . '{"direction":"in","protocol":"tcp","port":"22","source_ips":["192.0.2.0/24"],"description":"SSH"},'
            . '{"direction":"in","protocol":"icmp","source_ips":["0.0.0.0/0","::/0"]},'
            . '{"direction":"out","protocol":"udp","port":"53","destination_ips":["198.51.100.1/32"]}'
            . '],"apply_to":[{"type":"label_selector","label_selector":{"selector":"gosuccess-role=wpaas"}}]}', $http->requests[0]->body);
        self::assertSame(42, $created->firewall->id);
        self::assertNotEmpty($created->actions);
    }

    public function testRefusesAPortForIcmp(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FirewallRule(FirewallDirection::In, FirewallProtocol::Icmp, '22', ['0.0.0.0/0']);
    }

    public function testSetRulesReplacesAll(): void
    {
        [$cloud, $http] = $this->cloud(new Response(201, SpecExample::json('set_firewall_rules')), new Response(201, SpecExample::json('set_firewall_rules')));

        $actions = $cloud->firewalls()->setRules(38, [new FirewallRule(FirewallDirection::In, FirewallProtocol::Tcp, '80-443', ['::/0'])]);
        $cloud->firewalls()->setRules(38, []);

        self::assertSame('https://api.hetzner.cloud/v1/firewalls/38/actions/set_rules', $http->requests[0]->uri);
        self::assertSame('{"rules":[{"direction":"in","protocol":"tcp","port":"80-443","source_ips":["::/0"]}]}', $http->requests[0]->body);
        self::assertSame('{"rules":[]}', $http->requests[1]->body);
        self::assertSame(42, $actions[0]->id);
    }

    public function testApplyAndRemoveTargets(): void
    {
        [$cloud, $http] = $this->cloud(new Response(201, SpecExample::json('apply_firewall_to_resources')), new Response(201, SpecExample::json('remove_firewall_from_resources')));

        $cloud->firewalls()->applyToResources(38, [FirewallTarget::labelSelector('a=b')]);
        $cloud->firewalls()->removeFromResources(38, [FirewallTarget::server(7), FirewallTarget::labelSelector('a=b')]);

        self::assertSame('https://api.hetzner.cloud/v1/firewalls/38/actions/apply_to_resources', $http->requests[0]->uri);
        self::assertSame('{"apply_to":[{"type":"label_selector","label_selector":{"selector":"a=b"}}]}', $http->requests[0]->body);
        self::assertSame('https://api.hetzner.cloud/v1/firewalls/38/actions/remove_from_resources', $http->requests[1]->uri);
        self::assertSame('{"remove_from":[{"type":"server","server":{"id":7}},{"type":"label_selector","label_selector":{"selector":"a=b"}}]}', $http->requests[1]->body);
    }

    public function testDeleteSendsDelete(): void
    {
        [$cloud, $http] = $this->cloud(new Response(204, ''));

        self::assertTrue($cloud->firewalls()->delete(38));
        self::assertSame('DELETE', $http->requests[0]->method->value);
        self::assertSame('https://api.hetzner.cloud/v1/firewalls/38', $http->requests[0]->uri);
    }

    public function testDeleteNotFoundIsFalse(): void
    {
        [$cloud] = $this->cloud(new Response(404, '{"error":{"code":"not_found","message":"firewall not found","details":{}}}'));

        self::assertFalse($cloud->firewalls()->delete(38));
    }

    public function testAnother404CodeIsNoProofOfAbsence(): void
    {
        [$cloud] = $this->cloud(new Response(404, '{"error":{"code":"firewall_resource_not_found","message":"server not found","details":{}}}'));

        try {
            $cloud->firewalls()->removeFromResources(38, [FirewallTarget::server(7)]);
            self::fail('No exception.');
        } catch (NotFoundException $e) {
            self::assertSame('firewall_resource_not_found', $e->errorCode);
        }
    }

    public function testDeleteInUseRaisesWithCode(): void
    {
        [$cloud] = $this->cloud(new Response(422, '{"error":{"code":"resource_in_use","message":"firewall still applied","details":{}}}'));

        try {
            $cloud->firewalls()->delete(38);
            self::fail('No exception.');
        } catch (ApiException $e) {
            self::assertInstanceOf(ValidationException::class, $e);
            self::assertSame('resource_in_use', $e->errorCode);
        }
    }

    public function testUnknownTargetTypeRaises(): void
    {
        $data = SpecExample::with(SpecExample::response('get_firewall'), 'firewall.applied_to.0.type', 'load_balancer');
        [$cloud] = $this->cloud((string) json_encode($data));

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('firewall.applied_to[0].type');

        $cloud->firewalls()->get(38);
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
