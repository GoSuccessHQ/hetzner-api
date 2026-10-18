<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Tests\Support;

use GoSuccess\Hetzner\Cloud\HetznerCloud;
use GoSuccess\Hetzner\Cloud\Model\Firewall;
use GoSuccess\Hetzner\Cloud\Model\Server;

/**
 * Guards and cleans the test project of the live tests. Writing tests run only
 * in a project that holds nothing but `gosuccess-test-*` servers and firewalls,
 * so a wrong token never touches another project; whatever they create carries
 * that prefix and is deleted afterwards, also after a failure.
 */
final class TestProject
{
    public const string PREFIX = 'gosuccess-test-';

    /** Seconds to wait for one action of the sweep. */
    private const float ACTION_TIMEOUT = 120.0;

    /**
     * The client for the live tests, or the reason they are skipped.
     *
     * @return HetznerCloud|string
     */
    public static function cloud(bool $writes): HetznerCloud|string
    {
        $token = getenv('HETZNER_CLOUD_TOKEN');

        if (!\is_string($token) || $token === '') {
            return 'HETZNER_CLOUD_TOKEN is not set.';
        }

        if ($writes && getenv('HETZNER_ALLOW_WRITES') !== '1') {
            return 'Writing tests need HETZNER_ALLOW_WRITES=1.';
        }

        return new HetznerCloud($token);
    }

    /**
     * Why the project is not the empty test project, or null if it is.
     */
    public static function foreignResources(HetznerCloud $cloud): ?string
    {
        $foreign = [];

        foreach ($cloud->servers()->all() as $server) {
            if (!str_starts_with($server->name, self::PREFIX)) {
                $foreign[] = "server {$server->id}";
            }
        }

        foreach ($cloud->firewalls()->all() as $firewall) {
            if (!str_starts_with($firewall->name, self::PREFIX)) {
                $foreign[] = "firewall {$firewall->id}";
            }
        }

        return $foreign === [] ? null : 'The project holds resources the tests did not create (' . implode(', ', $foreign) . '); writing tests run only in the empty test project.';
    }

    /**
     * Delete every `gosuccess-test-*` server (protection off first) and firewall.
     */
    public static function sweep(HetznerCloud $cloud): void
    {
        foreach (iterator_to_array($cloud->servers()->all(), false) as $server) {
            if (str_starts_with($server->name, self::PREFIX)) {
                self::deleteServer($cloud, $server);
            }
        }

        foreach (iterator_to_array($cloud->firewalls()->all(), false) as $firewall) {
            if (str_starts_with($firewall->name, self::PREFIX)) {
                self::deleteFirewall($cloud, $firewall);
            }
        }
    }

    /**
     * A name for a new resource.
     */
    public static function name(string $kind): string
    {
        return self::PREFIX . $kind . '-' . bin2hex(random_bytes(4));
    }

    private static function deleteServer(HetznerCloud $cloud, Server $server): void
    {
        if ($server->protection->delete || $server->protection->rebuild) {
            $cloud->actions()->wait($cloud->servers()->changeProtection($server->id, false, false), self::ACTION_TIMEOUT);
        }

        $cloud->servers()->delete($server->id);
    }

    private static function deleteFirewall(HetznerCloud $cloud, Firewall $firewall): void
    {
        if ($firewall->appliedTo !== []) {
            foreach ($cloud->firewalls()->removeFromResources($firewall->id, $firewall->appliedTo) as $action) {
                $cloud->actions()->wait($action, self::ACTION_TIMEOUT);
            }
        }

        $cloud->firewalls()->delete($firewall->id);
    }
}
