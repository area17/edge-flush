# Laravel CDN Cache Control and Invalidations

[![Latest Version on Packagist](https://img.shields.io/packagist/v/area17/cdn.svg?style=flat-square)](https://packagist.org/packages/area17/cdn)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/area17/cdn/run-tests?label=tests)](https://github.com/area17/cdn/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/area17/cdn/Check%20&%20fix%20styling?label=code%20style)](https://github.com/area17/cdn/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/area17/cdn.svg?style=flat-square)](https://packagist.org/packages/area17/cdn)

This package was created to help managing CDN granular caching and invalidations. While using Akamai or CloudFront, we usually bust the whole cache when we update something on our backend. This pacakge will do it granularly by doing the following: 

- To allow granular invalidation this package will crete a collection of all models that impacts one page and when one of those models change, all pages that had that model rendered in previous requests will be purged from CDN.
- Allow granular control of Cache-Control headers: you will be able to configure it differently per request, telling the CDN to store some pages for one week and others for 5 seconds, for example.
- Allow defining different strategies for Cache-Control: web pages may have a different cache strategy than api endpoints.
- Prevents from caching pages containing forms.
- Caches only frontend pages, leaving the CMS uncashed, if needed.
- Allow disabling caching for some pages using a middlware.
- Configure HTTP methods that allow caching or not: cache GET but not POST.
- Configure HTTP response status codes that allow caching or not: Cache 200 and 301 but not 400+ status codes.
- Configure what routes can and cannot be cached by CDN.
- Configure what type of responses can be cached: cache Response but not JsonResponse, for example.
- Configure what Model classes can be cached or not.  
- Remember what pages have been cached and command your CDN service to burst only those when you save something on your backend.
- Supports CloudFront invalidations.
- Supports Akamai EdgeCacheTags invalidations.
- Allow override of Services and easy implementation to support new CDN Services.

## Installation

You can install the package via composer:

```bash
composer require area17/cdn
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="A17\CDN\ServiceProvider"
```

And run the migrations:

```bash
php artisan migrate
```

## Configuration


## Dependencies

The supported CDN services have these package dependencies that you need to install yourself:

Akamai: akamai-open/edgegrid-auth
CloudFront: aws/aws-sdk-php

## Usage

Do a full read on the `config/cdn.php` there's a lot of configuration items and we tried to document them all.

Define your CDN service class on `config/cdn.php`:

``` php
'classes' => [
    'cdn' => A17\CDN\Services\CloudFront\Service::class,
    
    ...
]
```

Add the trait `A17\CDN\Behaviours\CachedOnCDN` to your models and repositories.

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
    \A17\CDN\Middleware::class,
    ...
];
```

Cache-Control max-age is set automatically, but if you need to change it depending on the current request you can use the following method: 

``` php
CacheControl::setMaxAge(5000);
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
CDN_ENABLED=true
CDN_WARMER_ENABLED=true
```

## CDN third-party service configuration

Please check the respective environment variables needed for supported services to work:

- [Akamai](https://github.com/area17/cdn/blob/unstable/config/cdn.php#L188)
- [CloudFront](https://github.com/area17/cdn/blob/unstable/config/cdn.php#L195)

## Rewarming cache

Purged cache pages can load slowly for the next users or even Google Bot, if you want to prevent this you can enable (on config) the cache warmer and add the job to the schedule:

``` php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new WarmCache())->everyMinute();
}
```

Note that the most hit (or frequently updated) pages will be warmed first. 

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
