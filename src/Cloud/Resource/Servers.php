<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Model\Action;
use GoSuccess\Hetzner\Cloud\Model\CreatedServer;
use GoSuccess\Hetzner\Cloud\Model\CreateServer;
use GoSuccess\Hetzner\Cloud\Model\Server;
use GoSuccess\Hetzner\Exception\NotFoundException;
use GoSuccess\Hetzner\Http\Method;
use GoSuccess\Hetzner\Json;
use SensitiveParameter;

/**
 * The servers (`/servers`); filters `name`, `label_selector`, `status`, `sort`.
 *
 * @extends Listing<Server>
 */
final class Servers extends Listing
{
    protected const string PATH = '/servers';

    protected const string ITEMS = 'servers';

    protected const string ITEM = 'server';

    /**
     * One server; null if Hetzner does not know it.
     */
    public function get(int $id): ?Server
    {
        return $this->find($id);
    }

    /**
     * Order a server. Never retried: a repeated order would create a second
     * server. The order is kept out of stack traces: its user data may carry
     * secrets.
     */
    public function create(#[SensitiveParameter] CreateServer $request): CreatedServer
    {
        $data = $this->connection->request(Method::Post, self::PATH, body: $request->toJson());

        return new CreatedServer(
            Server::fromJson(Json::object($data, 'server', ''), 'server'),
            Action::fromJson(Json::object($data, 'action', ''), 'action'),
            Action::listFrom($data, 'next_actions', ''),
            Json::nullableString($data, 'root_password', ''),
        );
    }

    /**
     * Delete a server; false if it was already gone (404 `not_found`).
     */
    public function delete(int $id): bool
    {
        try {
            $this->connection->request(Method::Delete, self::PATH . "/{$id}");
        } catch (NotFoundException $e) {
            if ($e->errorCode === 'not_found') {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * Replace the labels; nothing else changes.
     *
     * @param array<string, string> $labels
     */
    public function updateLabels(int $id, array $labels): Server
    {
        $data = $this->connection->request(Method::Put, self::PATH . "/{$id}", body: ['labels' => (object) $labels]);

        return Server::fromJson(Json::object($data, 'server', ''), 'server');
    }

    /**
     * Turn on the daily backups (costs a surcharge, see the pricing).
     */
    public function enableBackup(int $id): Action
    {
        return $this->action($id, 'enable_backup', null);
    }

    /**
     * Protect against deletion and rebuilding, or lift it.
     */
    public function changeProtection(int $id, bool $delete, bool $rebuild): Action
    {
        return $this->action($id, 'change_protection', ['delete' => $delete, 'rebuild' => $rebuild]);
    }

    protected function map(array $data, string $path): Server
    {
        return Server::fromJson($data, $path);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function action(int $id, string $name, ?array $body): Action
    {
        $data = $this->connection->request(Method::Post, self::PATH . "/{$id}/actions/{$name}", body: $body);

        return Action::fromJson(Json::object($data, 'action', ''), 'action');
    }
}
