<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit\Cloud;

use GoSuccess\Hetzner\ClientOptions;
use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Cloud\Model\CreatedServer;
use GoSuccess\Hetzner\Cloud\Model\CreateServer;
use GoSuccess\Hetzner\Cloud\Model\Server;
use GoSuccess\Hetzner\Cloud\Model\ServerProtection;
use GoSuccess\Hetzner\Cloud\Resource\Servers;
use GoSuccess\Hetzner\Exception\ApiException;
use GoSuccess\Hetzner\Exception\NotFoundException;
use GoSuccess\Hetzner\Exception\SerializationException;
use GoSuccess\Hetzner\Exception\TransportException;
use GoSuccess\Hetzner\Http\Response;
use GoSuccess\Hetzner\Tests\Support\ExceptionTrace;
use GoSuccess\Hetzner\Tests\Support\MockHttpClient;
use GoSuccess\Hetzner\Tests\Support\SpecExample;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Servers::class)]
#[CoversClass(Server::class)]
#[CoversClass(ServerProtection::class)]
#[CoversClass(CreateServer::class)]
#[CoversClass(CreatedServer::class)]
final class ServersTest extends TestCase
{
    private const string NOT_FOUND = '{"error":{"code":"not_found","message":"server not found","details":{}}}';

    public function testListAllPages(): void
    {
        $first = SpecExample::with(SpecExample::response('list_servers'), 'meta.pagination.next_page', 2);
        $second = SpecExample::with(SpecExample::with(SpecExample::response('list_servers'), 'servers.0.id', 43), 'meta.pagination.page', 2);
        [$cloud, $http] = $this->cloud((string) json_encode($first), (string) json_encode($second));

        $ids = array_map(static fn(Server $server): int => $server->id, iterator_to_array($cloud->servers()->all(), false));

        self::assertSame([42, 43], $ids);
        self::assertSame(2, $http->callCount());
    }

    public function testFilterByName(): void
    {
        [$cloud, $http] = $this->cloud(SpecExample::json('list_servers'));

        iterator_to_array($cloud->servers()->all(['name' => 'gs-wp-1']));

        self::assertSame('https://api.hetzner.cloud/v1/servers?name=gs-wp-1&page=1&per_page=50', $http->requests[0]->uri);
    }

    public function testGetReturnsServerWithNetAndProtection(): void
    {
        [$cloud, $http] = $this->cloud(SpecExample::json('get_server'));

        $server = $cloud->servers()->get(42);

        self::assertNotNull($server);
        self::assertSame('https://api.hetzner.cloud/v1/servers/42', $http->requests[0]->uri);
        self::assertSame(42, $server->id);
        self::assertSame('my-resource', $server->name);
        self::assertSame('running', $server->status);
        self::assertSame('1.2.3.4', $server->ipv4);
        self::assertSame('2001:db8::/64', $server->ipv6);
        self::assertSame('fsn1', $server->location);
        self::assertSame('cpx22', $server->serverType);
        self::assertSame('ubuntu-24.04', $server->image);
        self::assertSame(['environment' => 'prod', 'example.com/my' => 'label', 'just-a-key' => ''], $server->labels);
        self::assertSame('2016-01-30T23:55:00+00:00', $server->created->format(\DATE_ATOM));
        self::assertEquals(new ServerProtection(false, false), $server->protection);
        self::assertSame([4711], $server->privateNetworks);
    }

    public function testServerWithoutIpsImageAndPlacementGroup(): void
    {
        $data = SpecExample::response('get_server');

        foreach (['server.public_net.ipv4', 'server.public_net.ipv6', 'server.image', 'server.placement_group', 'server.backup_window'] as $path) {
            $data = SpecExample::with($data, $path, null);
        }

        [$cloud] = $this->cloud((string) json_encode($data));
        $server = $cloud->servers()->get(42);

        self::assertNotNull($server);
        self::assertNull($server->ipv4);
        self::assertNull($server->ipv6);
        self::assertNull($server->image);
        self::assertNull($server->placementGroup);
        self::assertNull($server->backupWindow);
    }

    public function testGetNotFoundIsNull(): void
    {
        [$cloud] = $this->cloud(new Response(404, self::NOT_FOUND));

        self::assertNull($cloud->servers()->get(42));
    }

    public function testGet404WithoutCodeRaises(): void
    {
        [$cloud] = $this->cloud(new Response(404, '<html>Not Found</html>'));

        $this->expectException(NotFoundException::class);

        $cloud->servers()->get(42);
    }

    public function testCreateSendsTheApisBody(): void
    {
        [$cloud, $http] = $this->cloud(new Response(201, SpecExample::json('create_server')));

        $cloud->servers()->create(new CreateServer(
            name: 'gs-wp-1',
            serverType: 'cx23',
            image: 'ubuntu-24.04',
            location: 'nbg1',
            sshKeys: [7, 'paul'],
            firewalls: [11, 12],
            networks: [4711],
            placementGroup: 3,
            labels: ['managed-by' => 'gosuccess-control'],
            enableIpv4: false,
            userData: "#cloud-config\n",
        ));

        self::assertSame('POST', $http->requests[0]->method->value);
        self::assertSame('https://api.hetzner.cloud/v1/servers', $http->requests[0]->uri);
        self::assertSame([
            'name' => 'gs-wp-1',
            'server_type' => 'cx23',
            'image' => 'ubuntu-24.04',
            'location' => 'nbg1',
            'ssh_keys' => [7, 'paul'],
            'firewalls' => [['firewall' => 11], ['firewall' => 12]],
            'labels' => ['managed-by' => 'gosuccess-control'],
            'public_net' => ['enable_ipv4' => false, 'enable_ipv6' => true],
            'start_after_create' => true,
            'networks' => [4711],
            'placement_group' => 3,
            'user_data' => "#cloud-config\n",
        ], $http->jsonBody());
    }

    public function testCreateOmitsWhatIsNotSetButSendsEmptyLabelsAsObject(): void
    {
        [$cloud, $http] = $this->cloud(new Response(201, SpecExample::json('create_server')));

        $cloud->servers()->create(new CreateServer('gs-test', 'cx23', 'ubuntu-24.04'));

        self::assertSame('{"name":"gs-test","server_type":"cx23","image":"ubuntu-24.04","ssh_keys":[],"firewalls":[],"labels":{},"public_net":{"enable_ipv4":true,"enable_ipv6":true},"start_after_create":true}', $http->requests[0]->body);
    }

    public function testCreateReturnsActionAndNextActions(): void
    {
        [$cloud] = $this->cloud(new Response(201, SpecExample::json('create_server')));

        $created = $cloud->servers()->create(new CreateServer('gs-test', 'cx23', 'ubuntu-24.04'));

        self::assertSame(42, $created->server->id);
        self::assertSame(42, $created->action->id);
        self::assertCount(1, $created->nextActions);
        self::assertSame('YItygq1v3GYjjMomLaKc', $created->rootPassword);
    }

    public function testCreateRejectedKeepsCode(): void
    {
        [$cloud, $http] = $this->cloud(new Response(412, '{"error":{"code":"resource_unavailable","message":"server type cx23 is unavailable in nbg1","details":{}}}'));

        try {
            $cloud->servers()->create(new CreateServer('gs-test', 'cx23', 'ubuntu-24.04', 'nbg1'));
            self::fail('No exception.');
        } catch (ApiException $e) {
            self::assertSame('resource_unavailable', $e->errorCode);
            self::assertSame(412, $e->statusCode);
        }

        self::assertSame(1, $http->callCount());
    }

    public function testUserDataAndRootPasswordAreMasked(): void
    {
        [$cloud] = $this->cloud(new Response(201, SpecExample::json('create_server')));
        $request = new CreateServer('gs-test', 'cx23', 'ubuntu-24.04', userData: 'token=bootstrap-secret');

        $created = $cloud->servers()->create($request);

        self::assertStringNotContainsString('bootstrap-secret', print_r($request, true));
        self::assertStringNotContainsString('YItygq1v3GYjjMomLaKc', print_r($created, true));
    }

    public function testUserDataStaysOutOfTraces(): void
    {
        // Thrown inside the transport, so the trace holds every frame of the call.
        $http = new MockHttpClient();
        $http->onSend = static fn(): never => throw new TransportException('timeout');
        $cloud = new HetznerCloud('token', new ClientOptions(maxRetries: 0), $http, static function (float $seconds): void {});

        $e = ExceptionTrace::capture(static fn(): CreatedServer => $cloud->servers()->create(new CreateServer('gs-test', 'cx23', 'ubuntu-24.04', userData: 'token=bootstrap-secret')));

        self::assertInstanceOf(TransportException::class, $e);
        self::assertStringNotContainsString('bootstrap-secret', ExceptionTrace::packageArguments($e));
    }

    public function testRootPasswordStaysOutOfTraces(): void
    {
        $body = SpecExample::with(SpecExample::response('create_server'), 'server', null);
        $body = SpecExample::with($body, 'root_password', 'TOPSECRETPW');
        $http = new MockHttpClient(new Response(201, (string) json_encode($body)));
        $cloud = new HetznerCloud('token', new ClientOptions(maxRetries: 0), $http, static function (float $seconds): void {});

        $e = ExceptionTrace::capture(static fn(): CreatedServer => $cloud->servers()->create(new CreateServer('gs-test', 'cx23', 'ubuntu-24.04')));

        self::assertInstanceOf(SerializationException::class, $e);
        self::assertStringNotContainsString('TOPSECRETPW', ExceptionTrace::arguments($e));
    }

    public function testDeleteReturnsTrue(): void
    {
        [$cloud, $http] = $this->cloud(new Response(200, SpecExample::json('delete_server')));

        self::assertTrue($cloud->servers()->delete(42));
        self::assertSame('DELETE', $http->requests[0]->method->value);
        self::assertSame('https://api.hetzner.cloud/v1/servers/42', $http->requests[0]->uri);
    }

    public function testDeleteNotFoundIsFalse(): void
    {
        [$cloud] = $this->cloud(new Response(404, self::NOT_FOUND));

        self::assertFalse($cloud->servers()->delete(42));
    }

    public function testDeleteProtectedRaisesWithCode(): void
    {
        [$cloud] = $this->cloud(new Response(403, '{"error":{"code":"protected","message":"server is delete protected","details":{}}}'));

        try {
            $cloud->servers()->delete(42);
            self::fail('No exception.');
        } catch (ApiException $e) {
            self::assertSame('protected', $e->errorCode);
        }
    }

    public function testUpdateLabelsSendsOnlyLabelsAsObject(): void
    {
        [$cloud, $http] = $this->cloud(SpecExample::json('update_server'), SpecExample::json('update_server'));

        $server = $cloud->servers()->updateLabels(42, ['a' => 'b']);
        $cloud->servers()->updateLabels(42, []);

        self::assertSame(42, $server->id);
        self::assertSame('PUT', $http->requests[0]->method->value);
        self::assertSame('{"labels":{"a":"b"}}', $http->requests[0]->body);
        self::assertSame('{"labels":{}}', $http->requests[1]->body);
    }

    public function testEnableBackup(): void
    {
        [$cloud, $http] = $this->cloud(new Response(201, SpecExample::json('enable_server_backup')));

        $action = $cloud->servers()->enableBackup(42);

        self::assertSame('https://api.hetzner.cloud/v1/servers/42/actions/enable_backup', $http->requests[0]->uri);
        self::assertSame('POST', $http->requests[0]->method->value);
        self::assertSame(42, $action->id);
    }

    public function testChangeProtectionSendsBothFlags(): void
    {
        [$cloud, $http] = $this->cloud(new Response(201, SpecExample::json('change_server_protection')));

        $cloud->servers()->changeProtection(42, delete: true, rebuild: false);

        self::assertSame('https://api.hetzner.cloud/v1/servers/42/actions/change_protection', $http->requests[0]->uri);
        self::assertSame('{"delete":true,"rebuild":false}', $http->requests[0]->body);
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
