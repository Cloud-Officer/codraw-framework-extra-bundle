<?php

namespace Draw\Bundle\FrameworkExtraBundle;

use Draw\Bundle\FrameworkExtraBundle\DependencyInjection\DrawFrameworkExtraExtension;
use Draw\Component\DependencyInjection\Integration\ContainerBuilderIntegrationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DrawFrameworkExtraBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $containerExtension = $this->getContainerExtension();

        if (!$containerExtension instanceof DrawFrameworkExtraExtension) {
            throw new \RuntimeException(\sprintf('The container extension must be an instance of "%s", "%s" given.', DrawFrameworkExtraExtension::class, get_debug_type($containerExtension)));
        }

        foreach ($containerExtension->getIntegrations() as $integration) {
            if ($integration instanceof ContainerBuilderIntegrationInterface) {
                $integration->buildContainer($container);
            }
        }
    }
}
