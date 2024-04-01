Kikwik/AdminkBundle
===================

Admin by Kikwik for symfony 6.4

## Installation


1. require the bundle

```console
#!/bin/bash
composer require kikwik/admink-bundle
```

2. Import admin routes in `config/routes/kikwik_admink.yaml`:

```yaml
kikwik_admink_bundle_dashboard:
    resource: '@KikwikAdminkBundle/config/routes.xml'
    prefix: '/admin'
```