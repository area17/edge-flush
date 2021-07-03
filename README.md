# CDN Cache Control and Invalidation

[![Latest Version on Packagist](https://img.shields.io/packagist/v/area17/cdn.svg?style=flat-square)](https://packagist.org/packages/area17/cdn)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/area17/cdn/run-tests?label=tests)](https://github.com/area17/cdn/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/area17/cdn/Check%20&%20fix%20styling?label=code%20style)](https://github.com/area17/cdn/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/area17/cdn.svg?style=flat-square)](https://packagist.org/packages/area17/cdn)

---

This repo can be used as to scaffold a Laravel package. Follow these steps to get started:

1. Press the "Use template" button at the top of this repo to create a new repo with the contents of this cdn
2. Run "./configure-cdn.sh" to run a script that will replace all placeholders throughout all the files
3. Remove this block of text.
4. Have fun creating your package.
5. If you need help creating a package, consider picking up our <a href="https://laravelpackage.training">Laravel Package Training</a> video course.

---

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/CDN.jpg?t=1" width="419px" />](https://area17.com/github-ad-click/CDN)

We invest a lot of resources into creating [best in class open source packages](https://area17.com/open-source). You can support us by [buying one of our paid products](https://area17.com/open-source/support-us).

## Installation

You can install the package via composer:

```bash
composer require area17/cdn
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --provider="Area17\CDN\ServiceProvider" --tag="cdn-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="Area17\CDN\ServiceProvider" --tag="cdn-config"
```

This is the contents of the published config file:

```php
return [];
```

## Usage

```php
$cdn = new Area17\CDN();
echo $cdn->echoPhrase('Hello, AREA 17!');
```

## Testing

```bash
composer test
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
