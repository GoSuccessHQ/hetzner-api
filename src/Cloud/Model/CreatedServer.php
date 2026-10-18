<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

/**
 * The answer to an order: the server, the action that creates it and the
 * actions that follow (e.g. starting it).
 */
final readonly class CreatedServer
{
    /**
     * @param list<Action> $nextActions
     * @param string|null  $rootPassword Only without SSH keys; never shown in dumps.
     */
    public function __construct(
        public Server $server,
        public Action $action,
        public array $nextActions,
        public ?string $rootPassword,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'server' => $this->server,
            'action' => $this->action,
            'nextActions' => $this->nextActions,
            'rootPassword' => $this->rootPassword === null ? null : '********',
        ];
    }
}
