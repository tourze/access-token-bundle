<?php

namespace AccessTokenBundle\Service;

use AccessTokenBundle\Controller\ListTokensController;
use AccessTokenBundle\Controller\RevokeTokenController;
use AccessTokenBundle\Controller\TestController;
use AccessTokenBundle\Controller\UserInfoController;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

#[AutoconfigureTag(name: 'routing.loader')]
class AttributeControllerLoader extends Loader implements RoutingAutoLoaderInterface
{
    private AttributeRouteControllerLoader $controllerLoader;

    public function __construct()
    {
        parent::__construct();
        $this->controllerLoader = new AttributeRouteControllerLoader();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->autoload();
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false;
    }

    public function autoload(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->addCollection($this->controllerLoader->load(UserInfoController::class));
        $collection->addCollection($this->controllerLoader->load(ListTokensController::class));
        $collection->addCollection($this->controllerLoader->load(RevokeTokenController::class));
        $collection->addCollection($this->controllerLoader->load(TestController::class));
        return $collection;
    }
}
