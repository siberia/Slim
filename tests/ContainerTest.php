<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Slim\Container;

class ContainerTest extends TestCase
{
    /**
     * @var Container
     */
    protected $container;

    public function setUp(): void
    {
        $this->container = new Container;
    }

    public function testGet(): void
    {
        $this->assertInstanceOf(\Slim\Http\Environment::class, $this->container->get('environment'));
    }

    /**
     * @expectedException \Slim\Exception\ContainerValueNotFoundException
     */
    public function testGetWithValueNotFoundError(): void
    {
        $this->container->get('foo');
    }

    /**
     * Test `get()` throws something that is a ContainerException - typically a NotFoundException, when there is a DI
     * config error
     *
     * @expectedException \Slim\Exception\ContainerValueNotFoundException
     */
    public function testGetWithDiConfigErrorThrownAsContainerValueNotFoundException()
    {
        $container = new Container;
        $container['foo'] =
            fn(ContainerInterface $container) => $container->get('doesnt-exist')
        ;
        $container->get('foo');
    }

    /**
     * Test `get()` recasts InvalidArgumentException as psr/container exceptions when an error is present
     * in the DI config
     *
     * @expectedException \Slim\Exception\ContainerException
     */
    public function testGetWithDiConfigErrorThrownAsInvalidArgumentException()
    {
        $container = new Container;
        $container['foo'] =
            fn(ContainerInterface $container) => $container['doesnt-exist']
        ;
        $container->get('foo');
    }

    /**
     * Test `get()` does not recast exceptions which are thrown in a factory closure
     *
     * @expectedException InvalidArgumentException
     */
    public function testGetWithErrorThrownByFactoryClosure()
    {
        $invokable = $this->getMockBuilder('StdClass')->getMock();
        /** @var callable $invokable */
        $invokable
            ->method('__invoke')
            ->willThrowException(new InvalidArgumentException());

        $container = new Container;
        $container['foo'] =
            function (ContainerInterface $container) use ($invokable): void {
                call_user_func($invokable);
            }
        ;
        $container->get('foo');
    }

    public function testGetRequest()
    {
        $this->assertInstanceOf(\Psr\Http\Message\RequestInterface::class, $this->container['request']);
    }

    public function testGetResponse()
    {
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $this->container['response']);
    }

    public function testGetRouter()
    {
        $this->assertInstanceOf(\Slim\Router::class, $this->container['router']);
    }

    public function testGetErrorHandler()
    {
        $this->assertInstanceOf(\Slim\Handlers\Error::class, $this->container['errorHandler']);
    }

    public function testGetNotAllowedHandler()
    {
        $this->assertInstanceOf(\Slim\Handlers\NotAllowed::class, $this->container['notAllowedHandler']);
    }

    public function testSettingsCanBeEdited()
    {
        $this->assertSame('1.1', $this->container->get('settings')['httpVersion']);

        $this->container->get('settings')['httpVersion'] = '1.2';
        $this->assertSame('1.2', $this->container->get('settings')['httpVersion']);
    }

    public function testMagicIssetMethod()
    {
        $this->assertEquals(true, $this->container->__isset('settings'));
    }

    public function testMagicGetMethod()
    {
        $this->container->get('settings')['httpVersion'] = '1.2';
        $this->assertSame('1.2', $this->container->__get('settings')['httpVersion']);
    }

    public function testRouteCacheDisabledByDefault()
    {
        $this->assertFalse($this->container->get('settings')['routerCacheFile']);
    }
}
