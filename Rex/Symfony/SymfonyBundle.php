<?php

namespace Rex\Symfony;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class SymfonyBundle extends Bundle {
    public function build(ContainerBuilder $container)
    {
      print_r("ASDFASDF");
        parent::build($container);

        // register extensions that do not follow the conventions manually
        $container->registerExtension(new UnconventionalExtensionClass());
    }
}
