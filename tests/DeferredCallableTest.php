<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Container;
use Slim\DeferredCallable;
use Slim\Tests\Mocks\CallableTest;

class DeferredCallableTest extends TestCase
{
    public function testItResolvesCallable(): void
    {
        $container = new Container();
        $container['CallableTest'] = new CallableTest;

        $deferred = new DeferredCallable('CallableTest:toCall', $container);
        $deferred();

        $this->assertSame(1, CallableTest::$CalledCount);
    }

    public function testItBindsClosuresToContainer(): void
    {
        $assertCalled = $this->getMockBuilder('StdClass')->getMock();
        $assertCalled
            ->expects($this->once())
            ->method('foo');

        $container = new Container();

        $test = $this;

        $closure = function () use ($container, $test, $assertCalled): void {
            $assertCalled->foo();
            $test->assertSame($container, $this);
        };

        $deferred = new DeferredCallable($closure, $container);
        $deferred();
    }

    public function testItReturnsInvokedCallableResponse()
    {
        $container = new Container();
        $test = $this;
        $foo = 'foo';
        $bar = 'bar';

        $closure = function ($param) use ($test, $foo, $bar) {
            $test->assertSame($foo, $param);
            return $bar;
        };

        $deferred = new DeferredCallable($closure, $container);

        $response = $deferred($foo);
        $this->assertSame($bar, $response);
    }

    public function testGetCallable()
    {
        $container = new Container();

        $closure = function (): void {
        };

        $deferred = new DeferredCallable($closure, $container);
        $this->assertSame($closure, $deferred->getCallable());
    }
}
