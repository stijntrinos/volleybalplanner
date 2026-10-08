<?php

namespace App\Planner\Support;

/**
 * Looks up the IPv4 addresses of a host name through the system resolver
 * (hosts file included). A class of its own so tests don't need DNS.
 */
class HostResolver
{
    /** @return list<string> */
    public function ipv4(string $host): array
    {
        return gethostbynamel($host) ?: [];
    }
}
