<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Unit\Cloud;

use DateTimeImmutable;
use GoSuccess\Hetzner\ClientOptions;
use GoSuccess\Hetzner\Cloud\Exception\ActionTimeoutException;
use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Cloud\Model\Action;
use GoSuccess\Hetzner\Cloud\Model\ActionError;
use GoSuccess\Hetzner\Cloud\Model\ActionResource;
use GoSuccess\Hetzner\Cloud\Model\ActionStatus;
use GoSuccess\Hetzner\Cloud\Resource\Actions;
use GoSuccess\Hetzner\Http\Response;
use GoSuccess\Hetzner\Tests\Support\MockHttpClient;
use GoSuccess\Hetzner\Tests\Support\SpecExample;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Actions::class)]
#[CoversClass(Action::class)]
#[CoversClass(ActionError::class)]
#[CoversClass(ActionResource::class)]
#[CoversClass(ActionTimeoutException::class)]
final class ActionsTest extends TestCase
{
    /** @var list<float> */
    private array $sleeps = [];

    public function testGetReadsTheAction(): void
    {
        [$cloud, $http] = $this->cloud(SpecExample::json('get_action'));

        $action = $cloud->actions()->get(42);

        self::assertSame('https://api.hetzner.cloud/v1/actions/42', $http->requests[0]->uri);
        self::assertSame(42, $action->id);
        self::assertSame('start_resource', $action->command);
        self::assertSame(ActionStatus::Running, $action->status);
        self::assertSame(100, $action->progress);
        self::assertSame('2016-01-30T23:55:00+00:00', $action->started->format(\DATE_ATOM));
        self::assertEquals([new ActionResource(42, 'server')], $action->resources);
        self::assertEquals(new ActionError('action_failed', 'Action failed'), $action->error);
    }

    public function testWaitReturnsFinishedActionWithoutPolling(): void
    {
        [$cloud, $http] = $this->cloud();
        $action = $this->action(ActionStatus::Success);

        self::assertSame($action, $cloud->actions()->wait($action, 10.0));
        self::assertSame(0, $http->callCount());
        self::assertSame([], $this->sleeps);
    }

    public function testWaitPollsUntilSuccess(): void
    {
        [$cloud, $http] = $this->cloud($this->json('running', 50), $this->json('success', 100));

        $done = $cloud->actions()->wait($this->action(ActionStatus::Running), 10.0, 2.0);

        self::assertSame(ActionStatus::Success, $done->status);
        self::assertSame(2, $http->callCount());
        self::assertSame([2.0, 2.0], $this->sleeps);
    }

    public function testWaitReturnsErrorAction(): void
    {
        [$cloud] = $this->cloud($this->json('error', 100, ['code' => 'action_failed', 'message' => 'Action failed']));

        $done = $cloud->actions()->wait($this->action(ActionStatus::Running), 10.0);

        self::assertSame(ActionStatus::Error, $done->status);
        self::assertEquals(new ActionError('action_failed', 'Action failed'), $done->error);
    }

    public function testWaitStopsAtTheLimit(): void
    {
        [$cloud, $http] = $this->cloud($this->json('running', 10), $this->json('running', 20), $this->json('running', 30));

        try {
            $cloud->actions()->wait($this->action(ActionStatus::Running), 5.0, 2.0);
            self::fail('No exception.');
        } catch (ActionTimeoutException $e) {
            self::assertSame(20, $e->action->progress);
            self::assertStringContainsString('42', $e->getMessage());
        }

        self::assertSame([2.0, 2.0], $this->sleeps);
        self::assertSame(2, $http->callCount());
    }

    public function testWaitCountsTheTimeOfTheRequestsToo(): void
    {
        $clock = 0.0;
        $http = new MockHttpClient(...array_fill(0, 5, new Response(200, $this->json('running', 10))));
        // Every poll takes three seconds.
        $http->onSend = static function () use (&$clock): void {
            $clock += 3.0;
        };
        $cloud = new HetznerCloud('token', new ClientOptions(maxRetries: 0), $http, static function (float $seconds) use (&$clock): void {
            $clock += $seconds;
        }, static function () use (&$clock): float {
            return $clock;
        });

        $this->expectException(ActionTimeoutException::class);

        try {
            $cloud->actions()->wait($this->action(ActionStatus::Running), 5.0, 1.0);
        } finally {
            self::assertSame(2, $http->callCount());
        }
    }

    public function testWaitRefusesANonPositiveInterval(): void
    {
        [$cloud] = $this->cloud();

        $this->expectException(InvalidArgumentException::class);

        $cloud->actions()->wait($this->action(ActionStatus::Running), 5.0, 0.0);
    }

    /**
     * @return array{HetznerCloud, MockHttpClient}
     */
    private function cloud(string ...$bodies): array
    {
        $http = new MockHttpClient(...array_map(static fn(string $body): Response => new Response(200, $body), $bodies));

        // A clock that only moves while sleeping: requests take no time here.
        return [new HetznerCloud('token', new ClientOptions(maxRetries: 0), $http, function (float $seconds): void {
            $this->sleeps[] = $seconds;
        }, fn(): float => array_sum($this->sleeps)), $http];
    }

    private function action(ActionStatus $status): Action
    {
        return new Action(42, 'create_server', $status, 0, new DateTimeImmutable('2026-10-18T10:00:00Z'), null, [], null);
    }

    /**
     * @param array{code: string, message: string}|null $error
     */
    private function json(string $status, int $progress, ?array $error = null): string
    {
        return (string) json_encode(['action' => [
            'id' => 42, 'command' => 'create_server', 'status' => $status, 'progress' => $progress,
            'started' => '2026-10-18T10:00:00Z', 'finished' => $status === 'running' ? null : '2026-10-18T10:00:30Z',
            'resources' => [['id' => 7, 'type' => 'server']], 'error' => $error,
        ]]);
    }
}
