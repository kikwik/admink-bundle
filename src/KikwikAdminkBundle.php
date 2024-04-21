<?php

namespace Kikwik\AdminkBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class KikwikAdminkBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('twig',[
            'globals' => [
                'adminkonfig' => '@kikwik_admink.service.admin_konfig'
            ]
        ]);
    }


    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('title')->defaultValue('AdminK')->end()
                ->arrayNode('assets')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('logo')->defaultValue('bundles/kikwikadmink/logo/k.png')->end()
                        ->scalarNode('favicon')->defaultValue('bundles/kikwikadmink/logo/favicon.png')->end()
                    ->end()
                ->end()
                ->arrayNode('routes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('login')->defaultValue('app_login')->end()
                        ->scalarNode('logout')->defaultValue('app_logout')->end()
                        ->scalarNode('change_password')->defaultValue('kikwik_user_password_change')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }


    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // load an XML, PHP or Yaml file
        $container->import('../config/services.xml');

        $container->services()
            ->get('kikwik_admink.service.admin_konfig')
            ->arg('$adminTitle',$config['title'])
            ->arg('$assets',$config['assets'])
            ->arg('$routes',$config['routes'])
        ;
    }

}