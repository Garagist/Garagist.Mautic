# Garagist.Mautic

[![Latest stable version]][packagist] [![GitHub stars]][stargazers] [![GitHub watchers]][subscription] [![GitHub license]][license] [![GitHub issues]][issues] [![GitHub forks]][network]

Neos Adapter for the Mauitc API

## Installation

Add the package in your site package:

```bash
composer require --no-update garagist/mautic
```

The run `composer update` in your project root.

Finally you need to run the following commands:

```bash
flow doctrine:migrate
flow eventstore:setupall
```

[packagist]: https://packagist.org/packages/garagist/mautic
[latest stable version]: https://poser.pugx.org/garagist/mautic/v/stable
[github issues]: https://img.shields.io/github/issues/Garagist/Garagist.Mautic
[issues]: https://github.com/Garagist/Garagist.Mautic/issues
[github forks]: https://img.shields.io/github/forks/Garagist/Garagist.Mautic
[network]: https://github.com/Garagist/Garagist.Mautic/network
[github stars]: https://img.shields.io/github/stars/Garagist/Garagist.Mautic
[stargazers]: https://github.com/Garagist/Garagist.Mautic/stargazers
[github license]: https://img.shields.io/github/license/Garagist/Garagist.Mautic
[license]: LICENSE
[github watchers]: https://img.shields.io/github/watchers/Garagist/Garagist.Mautic.svg
[subscription]: https://github.com/Garagist/Garagist.Mautic/subscription
