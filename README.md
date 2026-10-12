<p align="center"><a href="https://pharaonic.dev/packages/php/fingerprint" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/php-fingerprint/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.0&color=blue&style=flat-square" alt="PHP Version : 8.0"></a>
  <img src="https://img.shields.io/static/v1?label=License&message=MIT&color=brightgreen&style=flat-square" alt="License">
  <img src="https://github.com/Pharaonic/php-fingerprint/actions/workflows/build.yml/badge.svg" alt="Tests">
  <br>
  <a href="https://packagist.org/packages/pharaonic/php-fingerprint" target="_blank"><img src="https://img.shields.io/static/v1?label=Packagist&message=pharaonic/php-fingerprint&color=blue&logo=packagist&logoColor=white" alt="Source"></a>
  <a href="https://packagist.org/packages/pharaonic/php-fingerprint" target="_blank"><img src="https://poser.pugx.org/pharaonic/php-fingerprint/v" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/pharaonic/php-fingerprint" target="_blank"><img src="https://poser.pugx.org/pharaonic/php-fingerprint/downloads" alt="Packagist Downloads"></a>
</p>

<h3 align="center">Deterministic fingerprints for PHP data.</h3>
<h5 align="center">The same data gives the same fingerprint, whatever the key order, the machine, the locale or the PHP version.</h5>
<br>

## Installation

```bash
composer require pharaonic/php-fingerprint
```

## Usage

```php
use Pharaonic\Fingerprint\Fingerprint;

Fingerprint::make(['id' => 7, 'tags' => ['php', 'laravel']]);
// Same result for ['tags' => ['php', 'laravel'], 'id' => 7]: key order never matters.
// Different result for ['id' => '7', ...] or a reordered list: types and list order do.

Fingerprint::for($order)
    ->ignore('updated_at')
    ->at('customer.email')->trim()->lower()
    ->hmac($secret)
    ->make();
```

For protocols that sign fields in a fixed order, such as payment gateway callbacks, `Fingerprint::signature($payload)->fields([...])` builds and verifies the exact ordered message instead.

## Documentation

Full documentation: https://pharaonic.dev/packages/php/fingerprint

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
