# Cloud API

`GoSuccess\Hetzner\Cloud\HetznerCloud` talks to `https://api.hetzner.cloud/v1`
with one project's API token (Hetzner Console → Security → API tokens).

```php
$cloud = new HetznerCloud($token);
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
| `actions()` | `get_action`, and `wait()` |

## Lists

Every list resource has `list(page, perPage, filters)` for one page (a `Page`
with `items` and `pagination`) and `all(filters)`, a generator over every page
with 50 entries each. Filters are the operation's query parameters; a list value
is sent as repeated keys (`['sort' => ['name', 'id']]` → `sort=name&sort=id`).

```php
foreach ($cloud->servers()->all(['label_selector' => 'managed-by=gosuccess-control']) as $server) {
    echo "{$server->name} {$server->ipv4}\n";
}
```

The walk ends when `next_page` is null or does not grow, so a server that
repeats a page cannot loop it; one that keeps announcing new pages raises
`SerializationException` after `ClientOptions::$maxPages` pages (100, i.e.
5,000 entries).

## Single reads and deletes

`get($id)` returns null only when Hetzner answers 404 with the code `not_found`.
A 404 without it (a proxy's page) or with another code (e.g.
`firewall_resource_not_found` when removing a firewall from a server that is
gone) raises `NotFoundException`: it is no proof that the resource is gone.
`delete($id)` returns false in the same case and true otherwise.

## Actions

Most changes return actions that finish later. `wait()` polls until the action
is no longer running and returns it, `success` or `error` alike; Hetzner's code
and message are in `$action->error`. The limit is wall time, the polling
requests included; no poll starts that would end past it, and
`ActionTimeoutException` (with the last state in `$action`) comes instead.

```php
$action = $cloud->actions()->wait($cloud->servers()->enableBackup($id), timeout: 120.0);
```

## Errors

Every exception implements `GoSuccess\Hetzner\Exception\HetznerException`.
Errors keep Hetzner's `error.code` in `errorCode` (`not_found`,
`uniqueness_error`, `invalid_input`, `locked`, `protected`, `resource_in_use`,
`resource_unavailable`, …), and `invalid_input` the messages per field in
`fields`. The class follows the status (`AuthenticationException` 401,
`ForbiddenException` 403, `NotFoundException` 404, `ConflictException` 409,
`ValidationException` 422, `RateLimitException` 429, `ServerException` 5xx);
a redirect is an error too, since the client never follows one. An answer
outside the specification raises `SerializationException` with the path of the
field (`servers[3].server_type.name`), never its value.

## Retries and the rate limit

Network failures and 5xx are retried with backoff for `GET`, `PUT` and
`DELETE`, never for `POST`: a repeated `POST /servers` would order a second
server. A `429` is retried for every method (Hetzner did not run the request)
after waiting for `RateLimit-Reset`, at least `retryBaseDelay` and at most
`maxRetryDelay`; with `ClientOptions(retryOnRateLimit: false)` it throws at once.
`$cloud->rateLimit()` returns what the last response reported: requests per
hour (3600 by default, per project), what is left, and when it is full again.

## Secrets

The token, a server's user data (which may carry a bootstrap token) and a root
password are masked in `print_r`/`var_dump` and kept out of exception messages
and of the arguments in stack traces (`#[SensitiveParameter]`).
