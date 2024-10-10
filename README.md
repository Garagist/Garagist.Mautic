# Garagist.Mautic

[![Latest stable version]][packagist] [![GitHub stars]][stargazers] [![GitHub watchers]][subscription]
[![GitHub license]][license] [![GitHub issues]][issues] [![GitHub forks]][network]

This package makes it possible to **send personalized newsletters** from Neos with [Mautic], **include forms** from
Mautic as a content element and add **Mautic tracking**. The forms are integrated via the Javascript API. All backend
requests to Mautic are event-sourced. This ensures that the tasks are processed even if Mautic is not available.

![Screenshot with the overview of emails](https://user-images.githubusercontent.com/4510166/192508665-7dded40f-34e4-4035-b794-ca42eb8f12f0.png)
![Screenshot with the view of a document](https://user-images.githubusercontent.com/4510166/192508719-a5c3e5c0-4994-4e24-b989-0943cce25ec6.png)
![Screenshot with the detail view of an email](https://user-images.githubusercontent.com/4510166/192508772-b8958a6b-fc75-4d78-8b4d-756395e4d8a5.png)

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

## Configure Mautic

1. Visit your Mautic installation and [create a user for API].
2. [Enable API and HTTP basic auth]. Optional: Be sure your Mautic installation is running on HTTPS for the sake of security.
3. If you want to send test emails, please install our [GaragistMauticApiBundle] plugin.
4. Skip this, if your website and Mautic are running on the same server:
   - [Enable CORS], add your site to `valid domains`.

## Configure Neos

The default values are set in [`Settings.Garagist.yaml`].

### `routeArgument` setting

`htmlTemplate` sets the argument used to call the `HTML` variant of the newsletter and send it to Mautic.

`plaintextTemplate` sets the argument used to call the plaintext variant of the newsletter and send it to Mautic.

If you use [Garagist.Mjml] `htmlTemplate` is automatically set to `mjml`. The important thing is simply to respect the
loading order of the composer and load [Garagist.Mjml] after Garagist.Mautic.

### `api` setting

Set your credentials `userName` and `password` from Mautic. `baseUrl` is the URL where Mautic can be reached via PHP.
This may be different from the `publicUrl` (see next section) if Mautic is running in its own Docker container.

### `publicUrl` setting

Set here the URL where the Mautic installation is publicly accessible. This will be used for tracking, forms and links
in the newsletter module.

### `enableTracking` setting

Enable the Javascript tracking code from Mautic. By default it is set to `false` in the development context and to
`true` in the production context.

### `mail` setting

`trackingPixel` injects the tracking pixel from Mautic right before the closing `body` tag.

### `form` setting

`hide` sets the IDs of the forms you want to hide in the inpsector. You can pass an array (eg. `[1, 2, 3]`) or an
integer

### `category` setting

`newsletter` sets the ID of the category you want to use for the newsletters. Be aware that the category must exist in
Mautic.

### `testMail` setting

`recipients` defines the email addresses you want to use for send test emails. You can pass an array (eg.
`['test@mail.example', 'user@mail.example']`) or an string (eg. `'test@mail.example'`). Note that the
[GaragistMauticApiBundle] plugin must be installed in your Mautic installation. Also the setting `action.test` need to
set to `true`.

### `segment` setting

- `lockPrefilled` set if an segment is prefilled from the creation/edit dialog, the user can't unselect it. Defaults to
  `true`
- `mapping`: The ID of the segment to use for the newsletter. But you can also define an array/object to handle the
  segment in your own data provider.
- `choose`: Add here to segments to choose from on creation/edit dialog. You can pass an array (eg. `[1, 2, 3]`) or an
  integer.
- `hide`: Add here the IDs of the segments you want to hide (eg. for unconfirmed contacts). You can pass an array (eg.
  `[1, 2, 3]`) or an integer.

### `action` setting

In this group you can enable/disable folwing actions:

- `delete` Ability to delete emails, defaults to `true`
- `publish` Ability to publish emails, defaults to `true`
- `unpublish` Ability to unpublish emails, defaults to `true`
- `send` Ability to send emails, defaults to `true`
- `update` Ability to update emails, defaults to `true`
- `edit` Ability to change subject, preview text and/or receiver, defaults to `true`
- `test` Ability to send test (aka example) emails, defaults to `false`. You need to install the
  [GaragistMauticApiBundle] plugin in your Mautic installation

## Personalization

It is possible to send personalized emails. To use this, simply apply the following markup in the text on your page:

```
{#ifNewsletter}Hello #FIRSTNAME# #Lastname#, this is your newsletter{:else}Fallback for Webview{/if}
```

Availble fields are every field from contactfield, surounded by an # on both sides (case insensitive)

## NodeTypes

### [Garagist.Mautic:Mixin.Email]

Add this mixin to any document to enable the ability to send newsletter.

### [Garagist.Mautic:Mixin.Category]

Add this mixin to any document to define this as a category for newsletters. This is used in the overview of the
newsletter module.

### [Garagist.Mautic:Mixin.DoNotTrack]

Add this mixin to any document to have the ability to disable the Mautic tracking for this specific page.

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
