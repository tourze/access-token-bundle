<?php

namespace Tourze\AccessTokenBundle\Service;

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\AccessTokenBundle\Controller\ListTokensController;
use Tourze\AccessTokenBundle\Controller\RevokeTokenController;
use Tourze\AccessTokenBundle\Controller\TestController;
use Tourze\AccessTokenBundle\Controller\UserInfoController;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

#[AutoconfigureTag(name: 'routing.loader')]
class AttributeControllerLoader extends Loader implements RoutingAutoLoaderInterface
{
    private AttributeRouteControllerLoader $controllerLoader;

    private RouteCollection $collection;

    public function __construct()
    {
        parent::__construct();
        $this->controllerLoader = new AttributeRouteControllerLoader();

        $this->collection = new RouteCollection();
        $this->collection->addCollection($this->controllerLoader->load(UserInfoController::class));
        $this->collection->addCollection($this->controllerLoader->load(ListTokensController::class));
        $this->collection->addCollection($this->controllerLoader->load(RevokeTokenController::class));
        $this->collection->addCollection($this->controllerLoader->load(TestController::class));
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false;
    }

    public function autoload(): RouteCollection
    {
        return $this->collection;
    }
}
