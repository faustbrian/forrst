[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

Forrst protocol implementation for PHP - internal microservice RPC with per-function versioning, built-in observability, and rich query capabilities. Build type-safe intra-service APIs with Laravel integration and automatic discovery.

## Requirements

> **Requires [PHP 8.5+](https://php.net/releases/)**

## Installation

```bash
composer require cline/forrst
```

## Documentation

- **[Getting Started](docs/getting-started.md)** - Installation and basic usage
- **[Servers](docs/servers.md)** - Configure Forrst servers and middleware
- **[Functions](docs/functions.md)** - Build function handlers with validation
- **[Extensions](docs/extensions.md)** - Add caching, idempotency, and rate limiting
- **[Clients](docs/clients.md)** - Build type-safe Forrst clients
- **[Testing](docs/testing.md)** - Test Forrst functions with Pest
- **[Protocol Specification](spec/index.md)** - Deep dive into the Forrst protocol

## Quick Start

### Create a Function

```php
<?php

namespace App\Http\Functions;

use Cline\Forrst\Functions\AbstractFunction;

class UserListFunction extends AbstractFunction
{
    public function __invoke(): array
    {
        return User::all()->toArray();
    }
}
```

### Configure the Server

```php
// config/rpc.php
return [
    'namespaces' => [
        'functions' => 'App\\Http\\Functions',
    ],
    'paths' => [
        'functions' => app_path('Http/Functions'),
    ],
    'servers' => [
        [
            'name' => env('APP_NAME'),
            'path' => '/rpc',
            'route' => 'rpc',
            'functions' => null, // Auto-discover
        ],
    ],
];
```

### Make a Request

```bash
curl -X POST http://localhost/rpc \
  -H "Content-Type: application/json" \
  -d '{
    "protocol": { "name": "forrst", "version": "0.1.0" },
    "id": "req_001",
    "call": {
      "function": "app.user_list",
      "version": "1.0.0",
      "arguments": {}
    }
  }'
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/forrst/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/forrst.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/forrst.svg

[link-tests]: https://github.com/faustbrian/forrst/actions
[link-packagist]: https://packagist.org/packages/cline/forrst
[link-downloads]: https://packagist.org/packages/cline/forrst
[link-security]: https://github.com/faustbrian/forrst/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
