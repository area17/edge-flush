<?php

namespace A17\EdgeFlush\Support;

use Throwable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Helpers
{
    /**
     * Sanitize the URL rebuilding the list of query parameters according to the configuration
     */
    public static function sanitizeUrl(string $url): string
    {
        if (config('edge-flush.urls.query.fully_cachable')) {
            return $url;
        }

        $parsed = static::parseUrl($url);

        $query = [];

        parse_str($parsed['query'] ?? null, $query);

        if (blank($query)) {
            return $url;
        }

        $routes = (array) config('edge-flush.urls.query.allow_routes');

        $list = $routes[$parsed['path']] ?? null;

        if (blank($list)) {
            try {
                $routeName = app('router')
                    ->getRoutes()
                    ->match(app('request')->create($url))
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

        parse_str($url['query'] ?? '', $query);

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
        $url = $components['scheme'] . '://';

        if (
            !empty($components['username']) &&
            !empty($components['password'])
        ) {
            $url .=
                $components['username'] . ':' . $components['password'] . '@';
        }

        $url .= $components['host'];

        if (
            !empty($components['port']) &&
            (($components['scheme'] === 'http' && $components['port'] !== 80) ||
                ($components['scheme'] === 'https' &&
                    $components['port'] !== 443))
        ) {
            $url .= ':' . $components['port'];
        }

        if (!empty($components['path'])) {
            $url .= $components['path'];
        }

        if (!empty($components['fragment'])) {
            $url .= '#' . $components['fragment'];
        }

        if (!empty($components['query'])) {
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

    public static function debug(string|array|null $data = null): bool
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
}
