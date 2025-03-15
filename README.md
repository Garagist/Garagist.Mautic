# Garagist.Mautic

[![Latest stable version]][packagist] [![GitHub stars]][stargazers] [![GitHub watchers]][subscription]
[![GitHub license]][license] [![GitHub issues]][issues] [![GitHub forks]][network]

This package makes it possible to **send personalized newsletters** from Neos with [Mautic], **include forms** from
Mautic as a content element and add **Mautic tracking**.

## Installation

Add the package in your site package:

```bash
composer require --no-update garagist/mautic
```

The run `composer update` in your project root.

## Configure Mautic

1. Visit your Mautic installation and [create a user for API].
2. [Enable API and HTTP basic auth]. Optional: Be sure your Mautic installation is running on HTTPS for the sake of
   security.
3. If you want to send test emails, please install our [GaragistMauticApiBundle] plugin.
4. Skip this, if your website and Mautic are running on the same server:
   - [Enable CORS], add your site to `valid domains`.

## Configure Neos

The default values are set in [`Settings.Garagist.yaml`].

### `api` setting

Set your credentials `userName` and `password` from Mautic. `baseUrl` is the URL where Mautic can be reached via PHP.
This may be different from the `publicUrl` (see next section) if Mautic is running in its own Docker container.

### `publicUrl` setting

Set here the URL where the Mautic installation is publicly accessible. This will be used for tracking, forms and links
in the newsletter module.

### `enableTracking` setting

Enable the Javascript tracking code from Mautic. By default it is set to `false` in the development context and to
`true` in the production context.

### `form` setting

`hide` sets the IDs of the forms you want to hide in the inpsector. You can pass an array (eg. `[1, 2, 3]`) or an
integer

## Personalization

It is possible to send personalized emails. To use this, simply apply the following markup in the text on your page:

```
{#ifNewsletter}Hello #FIRSTNAME# #Lastname#, this is your newsletter{:else}Fallback for Webview{/if}
```

Availble fields are every field from contactfield, surounded by an # on both sides (case insensitive)

## NodeTypes

### [Garagist.Mautic:Mixin.Email]

Add this mixin to any document to enable the ability to send newsletter.

### [Garagist.Mautic:Mixin.Form]

Add this mixin to a node to add the selector for Mautic forms. Be aware that you need to include the Fusion prototype
[Garagist.Mautic:Component.Form] somewhere in your markup

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
[mautic]: https://www.mautic.org
[`settings.garagist.yaml`]: Configuration/Settings.Garagist.yaml
[garagist.mjml]: https://github.com/Garagist/Garagist.Mjml
[create a user for api]: https://docs.acquia.com/campaign-studio/settings/users-roles/
[enable api and http basic auth]: https://docs.acquia.com/campaign-studio/settings/api-quick-start/
[enable cors]: https://docs.acquia.com/campaign-studio/settings/configuration/#cors-settings
[garagistmauticapibundle]: https://github.com/Garagist/GaragistMauticApiBundle
[garagist.mautic:mixin.email]: NodeTypes/Mixin/Email.yaml
[garagist.mautic:mixin.category]: NodeTypes/Mixin/Category.yaml
[garagist.mautic:mixin.donottrack]: NodeTypes/Mixin/DoNotTrack.yaml
[garagist.mautic:mixin.form]: NodeTypes/Mixin/Form.yaml
[garagist.mautic:component.form]: Resources/Private/Fusion/Component/Form.fusion
