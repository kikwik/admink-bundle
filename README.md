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