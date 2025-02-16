<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Slim\Container;
use Slim\DeferredCallable;
use Slim\Http\Body;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Uri;
use Slim\Route;
use Slim\Tests\Mocks\CallableTest;
use Slim\Tests\Mocks\InvocationStrategyTest;
use Slim\Tests\Mocks\MiddlewareStub;

class RouteTest extends TestCase
{
    public function routeFactory(): Route
    {
        $methods = ['GET', 'POST'];
        $pattern = '/hello/{name}';
        $callable = function ($req, $res, $args): void {
            // Do something
        };

        return new Route($methods, $pattern, $callable);
    }

    public function testConstructor(): void
    {
        $methods = ['GET', 'POST'];
        $pattern = '/hello/{name}';
        $callable = function ($req, $res, $args): void {
            // Do something
        };
        $route = new Route($methods, $pattern, $callable);

        $this->assertAttributeEquals($methods, 'methods', $route);
        $this->assertAttributeEquals($pattern, 'pattern', $route);
        $this->assertAttributeEquals($callable, 'callable', $route);
    }

    public function testGetMethodsReturnsArrayWhenContructedWithString(): void
    {
        $route = new Route('GET', '/hello', function ($req, $res, $args): void {
            // Do something
        });

        $this->assertSame(['GET'], $route->getMethods());
    }

    public function testGetMethods(): void
    {
        $this->assertSame(['GET', 'POST'], $this->routeFactory()->getMethods());
    }

    public function testGetPattern(): void
    {
        $this->assertSame('/hello/{name}', $this->routeFactory()->getPattern());
    }

    public function testGetCallable()
    {
        $callable = $this->routeFactory()->getCallable();

        $this->assertInternalType('callable', $callable);
    }

    public function testArgumentSetting()
    {
        $route = $this->routeFactory();
        $route->setArguments(['foo' => 'FOO', 'bar' => 'BAR']);
        $this->assertSame($route->getArguments(), ['foo' => 'FOO', 'bar' => 'BAR']);
        $route->setArgument('bar', 'bar');
        $this->assertSame($route->getArguments(), ['foo' => 'FOO', 'bar' => 'bar']);
        $route->setArgument('baz', 'BAZ');
        $this->assertSame($route->getArguments(), ['foo' => 'FOO', 'bar' => 'bar', 'baz' => 'BAZ']);

        $route->setArguments(['a' => 'b']);
        $this->assertSame($route->getArguments(), ['a' => 'b']);
        $this->assertSame('b', $route->getArgument('a', 'default'));
        $this->assertSame('default', $route->getArgument('b', 'default'));

        $this->assertEquals($route, $route->setArgument('c', null));
        $this->assertEquals($route, $route->setArguments(['d' => null]));
    }


    public function testBottomMiddlewareIsRoute()
    {
        $route = $this->routeFactory();
        $bottom = null;
        $mw = function ($req, $res, $next) use (&$bottom) {
            $bottom = $next;
            return $res;
        };
        $route->add($mw);
        $route->finalize();

        $route->callMiddlewareStack(
            $this->getMockBuilder(\Psr\Http\Message\ServerRequestInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(\Psr\Http\Message\ResponseInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );

        $this->assertEquals($route, $bottom);
    }

    public function testAddMiddleware()
    {
        $route = $this->routeFactory();
        $called = 0;

        $mw = function ($req, $res, $next) use (&$called) {
            $called++;
            return $res;
        };

        $route->add($mw);
        $route->finalize();

        $route->callMiddlewareStack(
            $this->getMockBuilder(\Psr\Http\Message\ServerRequestInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(\Psr\Http\Message\ResponseInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );

        $this->assertSame(1, $called);
    }

    public function testRefinalizing()
    {
        $route = $this->routeFactory();
        $called = 0;

        $mw = function ($req, $res, $next) use (&$called) {
            $called++;
            return $res;
        };

        $route->add($mw);

        $route->finalize();
        $route->finalize();

        $route->callMiddlewareStack(
            $this->getMockBuilder(\Psr\Http\Message\ServerRequestInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(\Psr\Http\Message\ResponseInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );

        $this->assertSame(1, $called);
    }


    public function testIdentifier()
    {
        $route = $this->routeFactory();
        $this->assertSame('route0', $route->getIdentifier());
    }

    public function testSetName()
    {
        $route = $this->routeFactory();
        $this->assertEquals($route, $route->setName('foo'));
        $this->assertSame('foo', $route->getName());
    }

    public function testSetInvalidName()
    {
        $route = $this->routeFactory();

        $this->setExpectedException('InvalidArgumentException');

        $route->setName(false);
    }

    public function testSetOutputBuffering()
    {
        $route = $this->routeFactory();

        $route->setOutputBuffering(false);
        $this->assertFalse($route->getOutputBuffering());

        $route->setOutputBuffering('append');
        $this->assertSame('append', $route->getOutputBuffering());

        $route->setOutputBuffering('prepend');
        $this->assertSame('prepend', $route->getOutputBuffering());

        $this->assertEquals($route, $route->setOutputBuffering(false));
    }

    public function testSetInvalidOutputBuffering()
    {
        $route = $this->routeFactory();

        $this->setExpectedException('InvalidArgumentException');

        $route->setOutputBuffering('invalid');
    }

    public function testAddMiddlewareAsString()
    {
        $route = $this->routeFactory();

        $container = new Container();
        $container['MiddlewareStub'] = new MiddlewareStub();

        $route->setContainer($container);
        $route->add('MiddlewareStub:run');

        $env = Environment::mock();
        $uri = Uri::createFromString('https://example.com:80');
        $headers = new Headers();
        $cookies = [
            'user' => 'john',
            'id' => '123',
        ];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);

        $response = new Response;
        $result = $route->callMiddlewareStack($request, $response);

        $this->assertInstanceOf(\Slim\Http\Response::class, $result);
    }

    public function testControllerInContainer()
    {

        $container = new Container();
        $container['CallableTest'] = new CallableTest;

        $deferred = new DeferredCallable('CallableTest:toCall', $container);

        $route = new Route(['GET'], '/', $deferred);
        $route->setContainer($container);

        $uri = Uri::createFromString('https://example.com:80');
        $body = new Body(fopen('php://temp', 'r+'));
        $request = new Request('GET', $uri, new Headers(), [], Environment::mock()->all(), $body);

        CallableTest::$CalledCount = 0;

        $result = $route->callMiddlewareStack($request, new Response);

        $this->assertInstanceOf(\Slim\Http\Response::class, $result);
        $this->assertSame(1, CallableTest::$CalledCount);
    }

    public function testInvokeWhenReturningAResponse()
    {
        $callable = fn($req, $res, $args) => $res->write('foo');
        $route = new Route(['GET'], '/', $callable);

        $env = Environment::mock();
        $uri = Uri::createFromString('https://example.com:80');
        $headers = new Headers();
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $response = new Response;

        $response = $route->__invoke($request, $response);

        $this->assertSame('foo', (string)$response->getBody());
    }

    public function testInvokeWhenReturningAString()
    {
        $callable = fn($req, $res, $args) => "foo";
        $route = new Route(['GET'], '/', $callable);

        $env = Environment::mock();
        $uri = Uri::createFromString('https://example.com:80');
        $headers = new Headers();
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $response = new Response;

        $response = $route->__invoke($request, $response);

        $this->assertSame('foo', (string)$response->getBody());
    }

    /**
     * @expectedException Exception
     */
    public function testInvokeWithException()
    {
        $callable = function ($req, $res, $args): void {
            throw new Exception();
        };
        $route = new Route(['GET'], '/', $callable);

        $env = Environment::mock();
        $uri = Uri::createFromString('https://example.com:80');
        $headers = new Headers();
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $response = new Response;

        $response = $route->__invoke($request, $response);
    }

    public function testInvokeWhenDisablingOutputBuffer()
    {
        ob_start();
        $callable = function ($req, $res, $args) {
            echo 'foo';
            return $res->write('bar');
        };
        $route = new Route(['GET'], '/', $callable);
        $route->setOutputBuffering(false);

        $env = Environment::mock();
        $uri = Uri::createFromString('https://example.com:80');
        $headers = new Headers();
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $response = new Response;

        $response = $route->__invoke($request, $response);

        $this->assertSame('bar', (string)$response->getBody());

        $output = ob_get_clean();
        $this->assertSame('foo', $output);
    }

    public function testInvokeDeferredCallable()
    {
        $container = new Container();
        $container['CallableTest'] = new CallableTest;
        $container['foundHandler'] = fn() => new InvocationStrategyTest();

        $route = new Route(['GET'], '/', 'CallableTest:toCall');
        $route->setContainer($container);

        $uri = Uri::createFromString('https://example.com:80');
        $body = new Body(fopen('php://temp', 'r+'));
        $request = new Request('GET', $uri, new Headers(), [], Environment::mock()->all(), $body);

        $result = $route->callMiddlewareStack($request, new Response);

        $this->assertInstanceOf(\Slim\Http\Response::class, $result);
        $this->assertEquals([$container['CallableTest'], 'toCall'], InvocationStrategyTest::$LastCalledFor);
    }

    public function testPatternCanBeChanged()
    {
        $route = $this->routeFactory();
        $route->setPattern('/hola/{nombre}');
        $this->assertSame('/hola/{nombre}', $route->getPattern());
    }

    public function testChangingCallable()
    {
        $container = new Container();
        $container['CallableTest2'] = new CallableTest;
        $container['foundHandler'] = fn() => new InvocationStrategyTest();

        $route = new Route(['GET'], '/', 'CallableTest:toCall'); //Note that this doesn't actually exist
        $route->setContainer($container);

        $route->setCallable('CallableTest2:toCall'); //Then we fix it here.

        $uri = Uri::createFromString('https://example.com:80');
        $body = new Body(fopen('php://temp', 'r+'));
        $request = new Request('GET', $uri, new Headers(), [], Environment::mock()->all(), $body);

        $result = $route->callMiddlewareStack($request, new Response);

        $this->assertInstanceOf(\Slim\Http\Response::class, $result);
        $this->assertEquals([$container['CallableTest2'], 'toCall'], InvocationStrategyTest::$LastCalledFor);
    }
}
