# EdgeFlush

<!--
[![Latest Version on Packagist](https://img.shields.io/packagist/v/area17/edge-flush.svg?style=flat-square)](https://packagist.org/packages/area17/edge-flush)
-->
[![GitHub PHPUnit Action Status](https://img.shields.io/github/workflow/status/area17/edge-flush/phpunit?label=PHPUnit)](https://github.com/area17/edge-flush/actions?query=workflow%3Aphpunit+branch%3A1.x)
[![GitHub PHPStan Action Status](https://img.shields.io/github/workflow/status/area17/edge-flush/phpstan?label=PHPStan)](https://github.com/area17/edge-flush/actions?query=workflow%phpstan+branch%3A1.x)
<!--
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/area17/edge-flush/Check%20&%20fix%20styling?label=code%20style)](https://github.com/area17/edge-flush/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3A1.x)
[![Total Downloads](https://img.shields.io/packagist/dt/area17/edge-flush.svg?style=flat-square)](https://packagist.org/packages/area17/edge-flush)
-->

EdgeFlush is Laravel package intended to help developers manage CDN granular caching and invalidations. Having Akamai, CloudFront (or any other CDN) in front of a website, data modification usually forces us to bust the whole cache, leading to a website slow (for the first users) until the whole cache is rebuilt, and if the "first user" is Google Bot, for example, this can also impact on your website's rank. This pacakge aims to do invalidations granularly.

## Feature list

- Granular invalidation: this package will create a collection of all models that impacts one page and when one of those models change, all pages that had that model rendered in previous requests will be purged from CDN.
- Granular control of Cache-Control headers: you will be able to configure it differently per request, telling the CDN to store some pages for one week and others for 5 seconds, for example.
- Single [Akamai Edge Cache Tag](#akamai-edge-cache-tags) relating to all models touched by a page render.
- Define different strategies for Cache-Control: web pages may have a different cache strategy than api endpoints.
- Prevents from caching pages containing forms.
- Caches only frontend pages, leaving the CMS uncashed, if needed.
- [Re-warm](#rewarming-cache) pages purged from cache.
- Strip cookies from cachable responses.
- Disable caching for some pages using a middlware.
- Define HTTP methods that allow caching or not: cache GET but not POST.
- Define HTTP response status codes that allow caching or not: Cache 200 and 301 but not 400+ status codes.
- Define what routes can and cannot be cached by CDN.
- Define what type of responses can be cached: cache Response but not JsonResponse, for example.
- Define what Model classes can be cached or not.
- Remember what pages have been cached and command your CDN service to burst only those when you save something on your backend.
- Supports CloudFront invalidations.
- Supports Akamai EdgeCacheTags invalidations.
- Allow override of Services and easy implementation to support new CDN Services.
- [Spatie's Laravel Response Cache](#laravel-response-cache-integration) granular invalidations.

## Installation

Install the package via composer:

```bash
composer require area17/edge-flush
```

Publish the config file with:

```bash
php artisan vendor:publish --provider="A17\EdgeFlush\ServiceProvider"
```

And run the migrations:

```bash
php artisan migrate
```

## Dependencies

The supported CDN services have these package dependencies that you need to choose according to your setup:

Akamai: [akamai-open/edgegrid-auth](https://github.com/akamai/AkamaiOPEN-edgegrid-php)
CloudFront: [aws/aws-sdk-php](https://github.com/aws/aws-sdk-php)

## Usage

Do a full read on the `config/edge-flush.php` there's a lot of configuration items and we tried to document them all.

Define your CDN service class on `config/edge-flush.php`:

``` php
'classes' => [
    'cdn' => A17\EdgeFlush\Services\CloudFront\Service::class,
    
    ...
]
```

Add the trait `A17\EdgeFlush\Behaviours\CachedOnCDN` to your models and repositories.

Call `$this->invalidateCDNCache($model)` every time a model (on your base model or repository save() method). This example takes in consideration [Twill's](https://twill.io/) repositories:

``` php
public function afterSave($object, $fields)
{
    $this->invalidateCDNCache($object);

    parent::afterSave($object, $fields);
}
```

Call `$this->cacheModelOnCDN($model)` method on model's `getAttribute()`:

``` php
public function getAttribute($key)
{
    $this->cacheModelOnCDN($this);

    return parent::getAttribute($key);
}
```

Add the Middlware to the `Kernel.php` file:

```
protected $middleware = [
    \A17\EdgeFlush\Middleware::class,
    ...
];
```

Cache-Control max-age and s-maxage is set automatically, but if you need to change it depending on the current request you can use the following method:

``` php
CacheControl::setMaxAge(5000); // in seconds

CacheControl::setMaxAge('1 month'); // as a DateTime string period

CacheControl::setSMaxAge('2 weeks');
```

If you want to invalidate your paths in batches, add a scheduler setting the desired frequency for this to happen:

``` php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new PurgeTags())->everyMinute();
}
```

You need to enable the package and the warmer on your `.env` file

``` sh
EDGE_FLUSH_ENABLED=true
EDGE_FLUSH_WARMER_ENABLED=true
```

## CDN third-party service configuration

Please check the respective environment variables needed for supported services to work:

- [Akamai](https://github.com/area17/edge-flush/blob/unstable/config/edge-flush.php#L188)
- [CloudFront](https://github.com/area17/edge-flush/blob/unstable/config/edge-flush.php#L195)

## Rewarming cache

Purged cache pages can load slowly for the next users or even Google Bot, if you want to prevent this you can enable (on config) the cache warmer and add the job to the schedule:

``` php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new WarmCache())->everyMinute();
}
```

Note that the most hit (or frequently updated) pages will be warmed first.

## Akamai Edge Cache Tags

Akamai has a 128 bytes limit for the tag list, so if one page is impacted by lots of models, we would have no other way than busting the whole cache every time. This package creates a single Edge Cache Tag that relates to all models touched when the page was rendered, and adds it yo the response header:

```
edge-cache-tag: app-production-7e0ae085d699003a64e5fa7b75daae3d78ace842
```

## Invalidating the full cache from the command line

In case you need to invalidate the whole CDN cache locally or on a deployment routing, you can:

```
php artisan edge-flush:invalidate-all
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [AREA 17](https://github.com/area17)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
