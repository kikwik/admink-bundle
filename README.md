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

3. Make your admin controller extends `Kikwik\AdminkBundle\Controller\AbstractCRUDController`:

```php
namespace App\Controller\Admin;

use App\Entity\Famiglia;
use App\Form\FamigliaFormType;
use Kikwik\AdminkBundle\Controller\AbstractCRUDController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/{_locale}/famiglia', name: 'app_admin_famiglia')]
class FamigliaController extends AbstractCRUDController
{
    protected function getEntityClass(): string
    {
        return Famiglia::class;
    }

    protected function getListFields(): array
    {
        return [
            'nome'=> 'Nome',
            'tipo' => 'Tipo',
            'descrizione' => 'Descrizione',
            'numProdotti' => '# codici',
        ];
    }
    
    protected function getFormClass(): ?string
    {
        return FamigliaFormType::class;
    }
}
```


4. Configure admin in `config/packages/kikwik_admink.yaml`:

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

    sidebar:
        -
            title: Prodotti
            admins:
                - { title: Famiglie, icon: bi bi-lightbulb, route: app_admin_famiglia_list }
                - { title: Codici, icon: bi bi-lightbulb-fill, route: app_admin_codice_list }
        -
            title: Attributi
            admins:
                - { title: Colori, icon: bi bi-palette, route: app_admin_colore_list }
                - { title: Materiali, icon: bi bi-bricks, route: app_admin_materiale_list }
```
