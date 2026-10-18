# Not covered yet

The package covers what the GoSuccess API uses of the Cloud API, plus waiting
for actions. Everything else is planned for later.

## Why hand-written

Measured on 2026-10-18, both specifications are OpenAPI 3.1.2. `cloud.spec.json`
has 190 operations, no `$ref` at all, 1,853 inline object schemas (479 with a
`title`; Server, Location, ServerType, Image, SSH key and Network have none),
nullability as type arrays about 1,090 times, 15 `oneOf` and 541 inline enums.
The generator of `gosuccess/bunny-api` names models after `$ref` components and
supports neither inline enums nor real `oneOf`; adapting it would have cost one
to two weeks for 13 % of the API. Instead, `tests/Unit/Cloud/SpecTest.php` pins
every operation, every field the models read (required or nullable) and every
field the client sends against the committed snapshot, and a weekly workflow
fails when the snapshot changes.

## Cloud API

- Certificates, Floating IPs, Primary IPs, Load Balancers and their types,
  Volumes, ISOs, Data Centers.
- Zones and RRSets (DNS).
- Servers: power actions, rebuild, rescue, resize, ISO, metrics, the remaining
  actions, attaching networks, placement groups and volumes, changing the name.
- Images: update, delete, actions.
- Networks, placement groups, SSH keys: create, update, delete; network actions.
- Firewalls: update; the actions list of a firewall.
- Lists of actions per resource.

## Hetzner API

`https://api.hetzner.com/v1` (specification `hetzner.spec.json`, 32 operations
on 2026-10-18): Storage Boxes, their subaccounts, snapshots and actions. Planned
as `GoSuccess\Hetzner\Hetzner\HetznerApi`; the Http layer takes the base URL
already, so it needs no change for it.

## Robot

Hetzner's API for dedicated servers (`GoSuccess\Hetzner\Robot`): planned, not
built. It has no OpenAPI specification.
