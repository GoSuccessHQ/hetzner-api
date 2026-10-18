<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Model;

use DateTimeImmutable;
use GoSuccess\Hetzner\Json;

/**
 * A firewall with its rules and the resources it applies to.
 */
final readonly class Firewall
{
    /**
     * @param array<string, string> $labels
     * @param list<FirewallRule>    $rules
     * @param list<FirewallTarget>  $appliedTo
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $labels,
        public array $rules,
        public array $appliedTo,
        public DateTimeImmutable $created,
    ) {}

    /**
     * Every server the firewall covers: directly applied ones first, then those
     * of its label selectors, each once.
     *
     * @return list<int>
     */
    public function serverIds(): array
    {
        $ids = [];

        foreach ($this->appliedTo as $target) {
            if ($target->serverId !== null) {
                $ids[] = $target->serverId;
            }
        }

        foreach ($this->appliedTo as $target) {
            array_push($ids, ...$target->coveredServerIds);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        $rules = [];

        foreach (Json::list($data, 'rules', $path) as $index => $rule) {
            $rules[] = FirewallRule::fromJson($rule, "{$path}.rules[{$index}]");
        }

        $targets = [];

        foreach (Json::list($data, 'applied_to', $path) as $index => $target) {
            $targets[] = FirewallTarget::fromJson($target, "{$path}.applied_to[{$index}]");
        }

        return new self(
            Json::int($data, 'id', $path),
            Json::string($data, 'name', $path),
            \array_key_exists('labels', $data) ? Json::labels($data, 'labels', $path) : [],
            $rules,
            $targets,
            Json::time($data, 'created', $path),
        );
    }
}
