[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)
[![Maintenance level: Love](https://img.shields.io/badge/maintenance-%E2%99%A1%E2%99%A1%E2%99%A1-ff69b4.svg)](https://www.flownative.com/en/products/open-source.html)

# Twig Adapter for Flow Framework

This [Flow](https://flow.neos.io) package integrates the
[Twig](https://twig.symfony.com/) template engine as a first-class view
implementation. It is a drop-in alternative to the Fluid-based
`Neos.FluidAdaptor` and can coexist with Fluid – individual controllers
can be switched to Twig while the rest of the application keeps using
Fluid or any other view implementation.

## Feature Overview

Tis is a drop-in `ViewInterface` implementation for Flow's MVC system. The 
template path resolution follows Flow's conventions (`Resources/Private/Templates/{Controller}/{Action}.html.twig`).

Twig template references (`{% extends %}`, `{% include %}`) are interpreted 
relative to `Resources/Private/`. That leaves you completely free introducing
your own directory structures (for Atomic Design, component libraries, etc.).

As a replacement for Fluid's view helpers, this package contains Flow-specific 
Twig functions: `uri_action()`, `uri_resource()`, `csrf_token()`, `csrf_field()`, `translate()`.

Compiled templates are cached using Flow's cache framework. During development,
Twig files are also watched for changes using Flow's file monitor and caches
are flushed automatically.

The `app` global variable provides the current request, environment and some 
user context. And the Twig `dump()` function is supported in Development context 
via Twig's DebugExtension.

Apart from the regular view, there's also the `StandaloneTwigView` which does
not require an HTTP context. You can use the standalone view for rendering templates
outside MVC (for example in emails, PDFs, or generally via CLI).

## Requirements

- PHP 8.2 or higher
- Flow Framework 9.x
- Twig 3.x

## Installation

```
composer require flownative/twig-adapter
```

## Configuration

### Activate for specific controllers (recommended)

Create or edit `Configuration/Views.yaml` in your package to route
specific controllers to `TwigView`:

```yaml
-
  requestFilter: 'isPackage("Your.Package") && isController("Dashboard")'
  viewObjectName: 'Flownative\TwigAdapter\View\TwigView'
```

This way, Fluid and Twig coexist: only the controllers you specify
use Twig, everything else keeps using Fluid.

### Activate globally

To make Twig the default view for all controllers:

```yaml
Neos:
  Flow:
    mvc:
      view:
        defaultImplementation: 'Flownative\TwigAdapter\View\TwigView'
```

### Adapter settings

The adapter comes with sensible defaults. You can override them in
your `Settings.yaml`:

```yaml
Flownative:
  TwigAdapter:
    defaultExtension: '.html.twig'
    cache:
      enabled: true
    twigOptions:
      autoescape: 'html'
      strict_variables: true
```

## Template Conventions

### Directory structure

Templates follow Flow's standard resource layout. All paths inside
templates are relative to `Resources/Private/`:

```
Resources/Private/
├── Templates/
│   └── {Subpackage}/
│       └── {Controller}/
│           └── {Action}.html.twig
├── Layouts/
│   └── Default.html.twig
└── Components/           ← or any structure you prefer
    ├── Atom/
    ├── Molecule/
    └── Organism/
```

### Extends and includes

Because the base path is `Resources/Private/`, references in templates
map directly to the filesystem:

```twig
{% extends "Layouts/Default.html.twig" %}
{% include "Components/Molecule/MemberCard.html.twig" %}
```

## Available Twig Functions

### uri_action

Generate a URL to a controller action:

```twig
{{ uri_action('index') }}
{{ uri_action('show', 'Products', arguments={id: product.id}) }}
{{ uri_action('edit', 'Users', 'Vendor.Package', {id: 42}, absolute=true) }}
```

Parameters: `action`, `controller`, `package`, `arguments`,
`subpackage`, `section`, `format`, `additionalParams`,
`addQueryString`, `absolute`.

### uri_resource

Generate a URL to a static package resource:

```twig
<script src="{{ uri_resource('JavaScript/app.js') }}"></script>
<link rel="stylesheet" href="{{ uri_resource('Styles/main.css', 'Vendor.Package') }}" />
```

### csrf_token / csrf_field

CSRF protection for forms:

```twig
<form method="post" action="{{ uri_action('update') }}">
    {{ csrf_field() }}
    …
</form>
```

`csrf_token()` returns the raw token string, `csrf_field()` returns
a complete `<input type="hidden">` element.

### translate

I18n translation using Flow's `Translator`:

```twig
{{ translate('dashboard.welcome') }}
{{ translate('items.count', {count: items|length}, 'Main', 'Vendor.Package') }}
```

Falls back to the ID string if no translation is found.

## Global Variables

The `app` variable is available in every template:

| Variable           | Type            | Description                              |
|--------------------|-----------------|------------------------------------------|
| `app.request`      | `ActionRequest` | The current MVC request                  |
| `app.environment`  | `string`        | Flow context (`Development`, `Production`) |
| `app.debug`        | `bool`          | `true` in Development context            |
| `app.user`         | `Account\|null` | Authenticated account (lazy-loaded)      |

## Debugging

In Development context, Twig's `dump()` function is automatically
available:

```twig
{{ dump(someVariable) }}
{{ dump() }}  {# dumps all template variables #}
```

`dump()` is disabled in Production context.

## Caching

Compiled Twig templates are cached using Flow's cache framework.
The cache is registered as `Flownative_TwigAdapter_Templates` with
`PhpFrontend` and `SimpleFileBackend`.

- **Development**: `auto_reload` is enabled – Twig checks source file
  timestamps on each request and recompiles changed templates
  automatically.
- **Production**: templates are compiled once and served from cache.
  Run `flow:cache:flush` after deploying template changes.

The Twig cache is flushed together with all other caches when you
run:

```
./flow flow:cache:flush
```

## Standalone Rendering

For rendering outside of the MVC context (emails, PDFs, CLI output),
use `StandaloneTwigView`:

```php
$view = new StandaloneTwigView();
$view->setTemplatePathAndFilename(
    'resource://Vendor.Package/Private/Templates/Email/Welcome.html.twig'
);
$view->assign('user', $user);
$html = (string)$view->render();
```

The view automatically detects `Resources/Private/` in the path and
uses it as the base, so `{% extends "Layouts/..." %}` works as
expected.

## Credits and Support

This library was developed by Flownative. Feel free to suggest new features, 
report bugs or provide bug fixes in our GitHub project.
