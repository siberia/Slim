<?php
/**
 * Slim Framework (https://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2018 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/4.x/LICENSE.md (MIT License)
 */
namespace Slim\Tests\Middleware;

use FastRoute\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\RoutingResults;
use Slim\Middleware\RoutingMiddleware;
use Slim\Router;
use Slim\Tests\Test;
use Closure;

class RoutingMiddlewareTest extends Test
{
    protected function getRouter()
    {
        $router = new Router();
        $router->map(['GET'], '/hello/{name}', null);
        return $router;
    }

    public function testRouteIsStoredOnSuccessfulMatch()
    {
        $router = $this->getRouter();
        $mw = new RoutingMiddleware($router);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            // route is available
            $route = $request->getAttribute('route');
            $this->assertNotNull($route);
            $this->assertEquals('foo', $route->getArgument('name'));

            // routingResults is available
            $routingResults = $request->getAttribute('routingResults');
            $this->assertInstanceOf(RoutingResults::class, $routingResults);
            return $response;
        };
        Closure::bind($next, $this);

        $request = $this->createServerRequest('https://example.com:443/hello/foo');
        $response = $this->createResponse();
        $mw($request, $response, $next);
    }

    /**
     * @expectedException \Slim\Exception\HttpMethodNotAllowedException
     */
    public function testRouteIsNotStoredOnMethodNotAllowed()
    {
        $router = $this->getRouter();
        $mw = new RoutingMiddleware($router);

        $next = function (ServerRequestInterface $request, ResponseInterface $response) {
            // route is not available
            $route = $request->getAttribute('route');
            $this->assertNull($route);

            // routingResults is available
            $routingResults = $request->getAttribute('routingResults');
            $this->assertInstanceOf(RoutingResults::class, $routingResults);
            $this->assertEquals(Dispatcher::METHOD_NOT_ALLOWED, $routingResults->getRouteStatus());

            return $response;
        };
        Closure::bind($next, $this);

        $request = $this->createServerRequest('https://example.com:443/hello/foo', 'POST');
        $response = $this->createResponse();
        $mw($request, $response, $next);
    }
}
