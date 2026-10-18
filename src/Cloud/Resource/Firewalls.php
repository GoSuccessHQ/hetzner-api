<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Model\Action;
use GoSuccess\Hetzner\Cloud\Model\CreatedFirewall;
use GoSuccess\Hetzner\Cloud\Model\Firewall;
use GoSuccess\Hetzner\Cloud\Model\FirewallRule;
use GoSuccess\Hetzner\Cloud\Model\FirewallTarget;
use GoSuccess\Hetzner\Exception\NotFoundException;
use GoSuccess\Hetzner\Http\Method;
use GoSuccess\Hetzner\Json;

/**
 * The firewalls (`/firewalls`); filters `name`, `label_selector`, `sort`.
 *
 * @extends Listing<Firewall>
 */
final class Firewalls extends Listing
{
    protected const string PATH = '/firewalls';

    protected const string ITEMS = 'firewalls';

    protected const string ITEM = 'firewall';

    /**
     * One firewall; null if Hetzner does not know it.
     */
    public function get(int $id): ?Firewall
    {
        return $this->find($id);
    }

    /**
     * Create a firewall; it is active at once for every target.
     *
     * @param list<FirewallRule>    $rules
     * @param list<FirewallTarget>  $applyTo
     * @param array<string, string> $labels
     */
    public function create(string $name, array $rules = [], array $applyTo = [], array $labels = []): CreatedFirewall
    {
        $data = $this->connection->request(Method::Post, self::PATH, body: [
            'name' => $name,
            'labels' => (object) $labels,
            'rules' => array_map(static fn(FirewallRule $rule): array => $rule->toJson(), $rules),
            'apply_to' => array_map(static fn(FirewallTarget $target): array => $target->toJson(), $applyTo),
        ]);

        return new CreatedFirewall(
            Firewall::fromJson(Json::object($data, 'firewall', ''), 'firewall'),
            \array_key_exists('actions', $data) ? Action::listFrom($data, 'actions', '') : [],
        );
    }

    /**
     * Replace all rules in one call; an empty list removes every rule.
     *
     * @param list<FirewallRule> $rules
     *
     * @return list<Action>
     */
    public function setRules(int $id, array $rules): array
    {
        return $this->actions($id, 'set_rules', ['rules' => array_map(static fn(FirewallRule $rule): array => $rule->toJson(), $rules)]);
    }

    /**
     * @param list<FirewallTarget> $targets
     *
     * @return list<Action>
     */
    public function applyToResources(int $id, array $targets): array
    {
        return $this->actions($id, 'apply_to_resources', ['apply_to' => array_map(static fn(FirewallTarget $target): array => $target->toJson(), $targets)]);
    }

    /**
     * @param list<FirewallTarget> $targets
     *
     * @return list<Action>
     */
    public function removeFromResources(int $id, array $targets): array
    {
        return $this->actions($id, 'remove_from_resources', ['remove_from' => array_map(static fn(FirewallTarget $target): array => $target->toJson(), $targets)]);
    }

    /**
     * Delete a firewall; false if it was already gone (404 `not_found`). Hetzner
     * refuses one that is still applied (422 `resource_in_use`).
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

    protected function map(array $data, string $path): Firewall
    {
        return Firewall::fromJson($data, $path);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<Action>
     */
    private function actions(int $id, string $name, array $body): array
    {
        return Action::listFrom($this->connection->request(Method::Post, self::PATH . "/{$id}/actions/{$name}", body: $body), 'actions', '');
    }
}
