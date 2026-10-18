<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Exception\ActionTimeoutException;
use GoSuccess\Hetzner\Cloud\Model\Action;
use GoSuccess\Hetzner\Cloud\Model\ActionStatus;
use GoSuccess\Hetzner\Http\Connection;
use GoSuccess\Hetzner\Http\Method;
use GoSuccess\Hetzner\Json;
use InvalidArgumentException;

/**
 * Actions (`GET /actions/{id}`) and waiting for them to finish.
 */
final class Actions
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection) {}

    /**
     * The current state of an action.
     */
    public function get(int $id): Action
    {
        return Action::fromJson(Json::object($this->connection->request(Method::Get, "/actions/{$id}"), 'action', ''), 'action');
    }

    /**
     * Wait until an action is no longer running and return it, `success` or
     * `error` alike: the caller reads Hetzner's code and message from
     * {@see Action::$error}. Polls every `$interval` seconds; `$timeout` is wall
     * time, the requests included, and no poll starts that would end past it.
     *
     * @throws ActionTimeoutException if it is still running at the limit
     */
    public function wait(Action $action, float $timeout, float $interval = 1.0): Action
    {
        if (!is_finite($interval) || $interval <= 0.0 || !is_finite($timeout) || $timeout < 0.0) {
            throw new InvalidArgumentException('The interval must be positive and the timeout not negative.');
        }

        $deadline = $this->connection->now() + $timeout;

        while ($action->status === ActionStatus::Running) {
            if ($this->connection->now() + $interval > $deadline) {
                throw new ActionTimeoutException($action, $timeout);
            }

            $this->connection->sleep($interval);
            $action = $this->get($action->id);
        }

        return $action;
    }
}
