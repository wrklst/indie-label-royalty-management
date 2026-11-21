<?php

namespace App\Support;

use Closure;

/**
 * Whether an address the server is asked to fetch is out on the internet.
 * An address that points at the server itself, its private network or a
 * cloud's metadata service would let whoever supplied it look around in
 * there through the answers.
 */
final class PublicAddress
{
    /** @var (Closure(string): list<string>)|null */
    private static ?Closure $resolver = null;

    /**
     * True for an http or https address whose host resolves to public
     * addresses only.
     */
    public static function is(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || blank($parts['host'] ?? null)) {
            return false;
        }

        $host = trim((string) $parts['host'], '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::resolve($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answer for name lookups in place of the network, in tests.
     *
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }

        $addresses = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            $addresses[] = (string) ($record['ipv6'] ?? '');
        }

        return array_values(array_filter($addresses));
    }
}
