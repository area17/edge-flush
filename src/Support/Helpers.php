<?php declare(strict_types=1);

namespace A17\EdgeFlush\Support;

use Throwable;
use A17\EdgeFlush\Models\Url;
use A17\EdgeFlush\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Helpers
{
    /**
     * Sanitize the URL rebuilding the list of query parameters according to the configuration
     */
    public static function sanitizeUrl(string $url): string
    {
        if (Helpers::configBool('edge-flush.urls.query.fully_cachable')) {
            return $url;
        }

        $parsed = static::parseUrl($url);

        $query = [];

        parse_str($parsed['query'] ?? '', $query);

        if (blank($query)) {
            return $url;
        }

        $routes = (array) config('edge-flush.urls.query.allow_routes');

        $list = $routes[$parsed['path']] ?? null;

        if (blank($list)) {
            try {
                $routeName = app('router')
                    ->getRoutes()
                    ->match(request()->create($url))
                    ->getName();
            } catch (\Throwable) {
                $routeName = '';
            }

            $list = $routes[$routeName] ?? null;
        }

        $list = collect($list);

        $drop = collect($query)->filter(
            fn($_, $name) => !$list->contains($name),
        );

        return static::rewriteUrl($query, $drop->keys(), $url);
    }

    /**
     * Deconstruct and rebuild the URL dropping query parameters
     */
    public static function rewriteUrl(
        array $parameters,
        array|Collection $dropQueries = [],
        string|null $url = null
    ): string {
        $url = filled($url) ? $url : url()->full();

        $url = static::parseUrl($url);

        $query = [];

        parse_str($url['query'] ?? null, $query);

        foreach ($parameters as $key => $parameter) {
            $query[$key] = $parameter;
        }

        foreach ($dropQueries as $parameter) {
            unset($query[$parameter]);
        }

        $url['query'] = $query;

        return static::rebuildUrl($url);
    }

    /**
     * Generate URL from its components (i.e., opposite of built-in php function, parse_url())
     */
    public static function rebuildUrl(array $components): string
    {
        $url = ($components['scheme'] ?? 'https') . '://';

        if (
            filled($components['username'] ?? null) &&
            filled($components['password'] ?? null)
        ) {
            $url .=
                $components['username'] . ':' . $components['password'] . '@';
        }

        $url .= $components['host'] ?? 'localhost';

        if (
            filled($components['port'] ?? null) &&
            (($components['scheme'] === 'http' && $components['port'] !== 80) ||
                ($components['scheme'] === 'https' &&
                    $components['port'] !== 443))
        ) {
            $url .= ':' . $components['port'];
        }

        if (filled($components['path'] ?? null)) {
            $url .= $components['path'];
        }

        if (filled($components['fragment'] ?? null)) {
            $url .= '#' . $components['fragment'];
        }

        if (filled($components['query'] ?? null)) {
            $url .= '?' . http_build_query($components['query']);
        }

        return $url;
    }

    public static function parseUrl(object|array|string|null $url): array
    {
        if (is_array($url)) {
            $url = '';
        }

        try {
            /** @throws Throwable */
            $url = (string) $url;
        } catch (Throwable) {
            $url = '';
        }

        /** Check if the string only a domain name **/
        if (
            filter_var($url, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ===
            $url
        ) {
            $url = "https://$url";
        }

        $url = parse_url($url);

        return [
            'scheme' => isset($url['scheme']) ? $url['scheme'] : null,
            'host' => isset($url['host']) ? $url['host'] : null,
            'port' => isset($url['port']) ? $url['port'] : null,
            'user' => isset($url['user']) ? $url['user'] : null,
            'pass' => isset($url['pass']) ? $url['pass'] : null,
            'path' => isset($url['path']) ? $url['path'] : null,
            'query' => isset($url['query']) ? $url['query'] : null,
            'fragment' => isset($url['fragment']) ? $url['fragment'] : null,
        ];
    }

    public static function debug(array|string $data): bool
    {
        $debugIsOn = (bool) config('edge-flush.debug', false);

        if (!$debugIsOn) {
            return false;
        }

        if (blank($data)) {
            return true;
        }

        if (!is_string($data)) {
            $data = json_encode($data);

            $data = $data === false ? '' : $data;
        }

        Log::debug('[EDGE-FLUSH] ' . $data);

        return true;
    }

    public static function toString(mixed $string): string
    {
        if (is_string($string) || is_numeric($string)) {
            return (string) $string;
        }

        if (is_array($string)) {
            return (string) json_encode($string);
        }

        if (is_object($string)) {
            return (string) json_encode($string);
        }

        return '';
    }

    public static function toInt(mixed $string): int
    {
        if (is_string($string) || is_numeric($string)) {
            return (int) $string;
        }

        return 0;
    }

    public static function toArray(mixed $array): array
    {
        if (is_array($array)) {
            return $array;
        }

        if ($array instanceof Collection) {
            return $array->toArray();
        }

        if (is_string($array) || is_numeric($array)) {
            return (array) $array;
        }

        if (is_object($array)) {
            return (array) $array;
        }

        return [];
    }

    public static function toBool(mixed $value): bool
    {
        return (bool) $value;
    }

    public static function configBool(string $key, mixed $default = null): bool
    {
        return static::toBool(config($key, $default));
    }

    public static function configArray(
        string $key,
        mixed $default = null
    ): array|null {
        if (is_null($value = config($key, $default))) {
            return null;
        }

        return static::toArray($value);
    }

    public static function configString(
        string $key,
        mixed $default = null
    ): string|null {
        if (is_null($value = config($key, $default))) {
            return null;
        }

        return static::toString($value);
    }

    public static function configInt(
        string $key,
        mixed $default = null
    ): int|null {
        if (is_null($value = config($key, $default))) {
            return null;
        }

        return static::toInt($value);
    }

    public static function getUrl(mixed $item): string|null
    {
        if ($item instanceof Url) {
            $url = $item->url;
        } elseif ($item instanceof Tag) {
            $url = $item->url->url;
        } else {
            $url = null;
        }

        return $url;
    }
}
