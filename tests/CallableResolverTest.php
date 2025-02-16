<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests;

use PHPUnit\Framework\TestCase;
use Slim\CallableResolver;
use Slim\Container;
use Slim\Tests\Mocks\CallableTest;
use Slim\Tests\Mocks\InvokableTest;

class CallableResolverTest extends TestCase
{
    /**
     * @var Container
     */
    private $container;

    public function setUp(): void
    {
        CallableTest::$CalledCount = 0;
        InvokableTest::$CalledCount = 0;
        $this->container = new Container();
    }

    public function testClosure(): void
    {
        $test = function () {
            static $called_count = 0;
            return $called_count++;
        };
        $resolver = new CallableResolver($this->container);
        $callable = $resolver->resolve($test);
        $callable();
        $this->assertSame(1, $callable());
    }

    public function testFunctionName(): void
    {
        // @codingStandardsIgnoreStart
        function testCallable()
        {
            static $called_count = 0;
            return $called_count++;
        };
        // @codingStandardsIgnoreEnd

        $resolver = new CallableResolver($this->container);
        $callable = $resolver->resolve(__NAMESPACE__ . '\testCallable');
        $callable();
        $this->assertSame(1, $callable());
    }

    public function testObjMethodArray()
    {
        $obj = new CallableTest();
        $resolver = new CallableResolver($this->container);
        $callable = $resolver->resolve($obj->toCall(...));
        $callable();
        $this->assertSame(1, CallableTest::$CalledCount);
    }

    public function testSlimCallable()
    {
        $resolver = new CallableResolver($this->container);
        $callable = $resolver->resolve('Slim\Tests\Mocks\CallableTest:toCall');
        $callable();
        $this->assertSame(1, CallableTest::$CalledCount);
    }

    public function testSlimCallableContainer()
    {
        $resolver = new CallableResolver($this->container);
        $resolver->resolve('Slim\Tests\Mocks\CallableTest:toCall');
        $this->assertEquals($this->container, CallableTest::$CalledContainer);
    }

    public function testContainer()
    {
        $this->container['callable_service'] = new CallableTest();
        $resolver = new CallableResolver($this->container);
        $callable = $resolver->resolve('callable_service:toCall');
        $callable();
        $this->assertSame(1, CallableTest::$CalledCount);
    }

    public function testResolutionToAnInvokableClassInContainer()
    {
        $this->container['an_invokable'] = fn($c) => new InvokableTest();
        $resolver = new CallableResolver($this->container);
        $callable = $resolver->resolve('an_invokable');
        $callable();
        $this->assertSame(1, InvokableTest::$CalledCount);
    }

    public function testResolutionToAnInvokableClass()
    {
        $resolver = new CallableResolver($this->container);
        $callable = $resolver->resolve(\Slim\Tests\Mocks\InvokableTest::class);
        $callable();
        $this->assertSame(1, InvokableTest::$CalledCount);
    }

    public function testMethodNotFoundThrowException()
    {
        $this->container['callable_service'] = new CallableTest();
        $resolver = new CallableResolver($this->container);
        $this->setExpectedException('\RuntimeException');
        $resolver->resolve('callable_service:noFound');
    }

    public function testFunctionNotFoundThrowException()
    {
        $resolver = new CallableResolver($this->container);
        $this->setExpectedException('\RuntimeException');
        $resolver->resolve('noFound');
    }

    public function testClassNotFoundThrowException()
    {
        $resolver = new CallableResolver($this->container);
        $this->setExpectedException('\RuntimeException', 'Callable Unknown does not exist');
        $resolver->resolve('Unknown:notFound');
    }

    public function testCallableClassNotFoundThrowException()
    {
        $resolver = new CallableResolver($this->container);
        $this->setExpectedException('\RuntimeException', 'is not resolvable');
        $resolver->resolve(['Unknown', 'notFound']);
    }

    public function testCallableInvalidTypeThrowException()
    {
        $resolver = new CallableResolver($this->container);
        $this->setExpectedException('\RuntimeException', 'is not resolvable');
        $resolver->resolve(__LINE__);
    }
}
