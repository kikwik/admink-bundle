Kikwik/AdminkBundle
===================

EXPERIMENTAL: Do not use, this is an experimental bundle

Admin by Kikwik for symfony 6.4

## Installation


1. require the bundle

```console
#!/bin/bash
composer require kikwik/admink-bundle
```

2. Import admin routes in `config/routes/kikwik_admink.yaml`:

```yaml
kikwik_admink_bundle:
    resource: .
    type: kikwik_admink_routes
    prefix: '/admin/{_locale}'
```

3. Configure admin in `config/packages/kikwik_admink.yaml`:

```yaml
kikwik_admink:
    title: 'AdminK'
    assets:
        logo: 'bundles/kikwikadmink/logo/k.png'
        favicon: 'bundles/kikwikadmink/logo/favicon.png'
    routes:
        login: 'app_login'
        logout: 'app_logout'
        change_password: 'kikwik_user_password_change'
```