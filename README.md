# Hetzner API for PHP

A modern, strongly-typed, dependency-free PHP client for the
[Hetzner Cloud API](https://docs.hetzner.cloud/).

## Features

- **Servers**: list, read, order, delete, labels, backups and protection.
- **Firewalls**: list, read, create, replace rules, apply to and remove from
  servers and label selectors, delete.
- **Catalog**: locations, server types with prices and availability, images,
  networks, placement groups, SSH keys, pricing.
- **Actions**: read them, and wait until they finish, within your limit.
- **Lists** as generators over every page, or one page at a time.
- **Typed models** checked against Hetzner's specification: a test pins every
  operation and field the client uses against a committed snapshot, and a
  weekly workflow reports when Hetzner changes it.
- **Safe defaults**: an order is never retried, a 404 means "gone" only when
  Hetzner says `not_found`, the token never appears in messages, dumps or
  stack traces.
- **No dependencies**: only `ext-curl` and `ext-json`; bring your own transport
  if you like.

## Requirements

- PHP 8.4 or newer
- `ext-curl`, `ext-json`

## Installation

```bash
composer require gosuccess/hetzner-api
```

## Quick start

```php
use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Cloud\Model\CreateServer;

$cloud = new HetznerCloud($token);

foreach ($cloud->servers()->all(['label_selector' => 'env=prod']) as $server) {
    echo "{$server->name} {$server->status} {$server->ipv4}\n";
}

$created = $cloud->servers()->create(new CreateServer('web-1', 'cx23', 'ubuntu-24.04', 'nbg1', sshKeys: ['me']));
$action = $cloud->actions()->wait($created->action, timeout: 300.0);
```

## Resources

| Method | Operations |
|---|---|
| `locations()` | `list_locations`, `get_location` |
| `serverTypes()` | `list_server_types` |
| `images()` | `list_images` |
| `networks()` | `list_networks` |
| `placementGroups()` | `list_placement_groups` |
| `sshKeys()` | `list_ssh_keys` |
| `pricing()` | `get_pricing` |
| `servers()` | `list_servers`, `get_server`, `create_server`, `update_server` (labels), `delete_server`, `enable_server_backup`, `change_server_protection` |
| `firewalls()` | `list_firewalls`, `get_firewall`, `create_firewall`, `set_firewall_rules`, `apply_firewall_to_resources`, `remove_firewall_from_resources`, `delete_firewall` |
| `actions()` | `get_action`, `wait()` |

Lists have `list(page, perPage, filters)` and `all(filters)`; filters are the
operation's query parameters. `get()` returns null and `delete()` false only
when Hetzner answers 404 with `not_found`; another 404 (a proxy, or
`firewall_resource_not_found`) raises. Details: [docs/cloud.md](docs/cloud.md).

## Actions

Changes return actions that finish later. `wait()` returns the action once it
is no longer running, `success` or `error` alike (Hetzner's code and message in
`$action->error`), and throws `ActionTimeoutException` when it is still running
at your limit.

## Errors and retries

Every exception implements `GoSuccess\Hetzner\Exception\HetznerException`.
API errors keep Hetzner's `error.code` in `errorCode` and, for `invalid_input`,
the messages per field in `fields`; a body outside the specification raises
`SerializationException` naming the field.

Network failures and 5xx are retried with backoff for `GET`, `PUT` and
`DELETE`, never for `POST`, since a repeated `POST /servers` orders a second
server. A `429` waits for `RateLimit-Reset` and is retried, or throws at once
with `ClientOptions(retryOnRateLimit: false)`. `$cloud->rateLimit()` returns
the limit, what is left and when it resets.

## Configuration and custom transport

`ClientOptions` sets timeouts (30 s), retries (2), backoff, user agent and the
rate-limit retry. A custom transport implements `GoSuccess\Hetzner\Http\HttpClient`.

## Tested against

The live API on 2026-10-18 in an empty test project: every read above, and
writing with the cheapest type that could be ordered (`cpx02` in `fsn1`, IPv6
only, not started, about 20 seconds): order, labels, backups, protection, a
refused delete while protected, delete; a firewall with rules, applied to the
test server's label and refused as `resource_in_use` while it covered the
server, removed and deleted. A firewall whose selector matches no server can be
deleted while applied.

## Not covered yet

The rest of the Cloud API (certificates, floating and primary IPs, load
balancers, volumes, ISOs, DNS zones, the other server actions, …), the Hetzner
API with Storage Boxes, and Robot. See [docs/not-covered.md](docs/not-covered.md),
also for why the client is hand-written rather than generated.

## Development

```bash
composer check                    # code style, static analysis, unit tests
composer specs                    # refresh the specification snapshot
set -a; . ./.env; set +a
composer test:integration         # live; writing tests need HETZNER_ALLOW_WRITES=1
```

Live tests read `HETZNER_CLOUD_TOKEN` from a `.env` that is never committed.
Writing tests run only in a project that holds nothing but `gosuccess-test-*`
servers and firewalls, create only such resources, and delete them before and
after each test, also after a failure. They cost a few cents.

## License

MIT
