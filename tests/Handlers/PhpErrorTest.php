<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests\Handlers;

use Exception;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Handlers\PhpError;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;
use UnexpectedValueException;

class PhpErrorTest extends TestCase
{
    public static function phpErrorProvider(): \Iterator
    {
        yield ['application/json', 'application/json', '{'];
        yield ['application/vnd.api+json', 'application/json', '{'];
        yield ['application/xml', 'application/xml', '<error>'];
        yield ['application/hal+xml', 'application/xml', '<error>'];
        yield ['text/xml', 'text/xml', '<error>'];
        yield ['text/html', 'text/html', '<html>'];
    }

    /**
     * Test invalid method returns the correct code and content type
     *
     * @requires PHP 7.0
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('phpErrorProvider')]
    public function testPhpError($acceptHeader, $contentType, $startOfBody)
    {
        $error = new PhpError();

        /** @var Response $res */
        $res = $error->__invoke($this->getRequest('GET', $acceptHeader), new Response(), new Exception());

        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame($contentType, $res->getHeaderLine('Content-Type'));
        $this->assertSame(0, strpos((string)$res->getBody(), (string) $startOfBody));
    }

    /**
     * Test invalid method returns the correct code and content type
     *
     * @requires PHP 7.0
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('phpErrorProvider')]
    public function testPhpErrorDisplayDetails($acceptHeader, $contentType, $startOfBody)
    {
        $error = new PhpError(true);

        $exception = new Exception('Oops', 1, new Exception('Opps before'));

        /** @var Response $res */
        $res = $error->__invoke($this->getRequest('GET', $acceptHeader), new Response(), $exception);

        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame($contentType, $res->getHeaderLine('Content-Type'));
        $this->assertSame(0, strpos((string)$res->getBody(), (string) $startOfBody));
    }

    /**
     * @requires PHP 7.0
     */
    public function testNotFoundContentType()
    {
        $errorMock = $this->getMockBuilder(PhpError::class)->getMock();
        $errorMock->method('determineContentType')
            ->willReturn('unknown/type');

        $req = $this->getMockBuilder(\Slim\Http\Request::class)->disableOriginalConstructor()->getMock();

        $this->setExpectedException('\UnexpectedValueException');
        $errorMock->__invoke($req, new Response(), new Exception());
    }

    /**
     * Test invalid method returns the correct code and content type
     *
     * @requires PHP 5.0
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('phpErrorProvider')]
    public function testPhpError5($acceptHeader, $contentType, $startOfBody)
    {
        $this->skipIfPhp70();
        $error = new PhpError();

        /** @var Throwable $throwable */
        $throwable = $this->getMock(
            '\Throwable',
            ['getCode', 'getMessage', 'getFile', 'getLine', 'getTraceAsString', 'getPrevious']
        );

        $res = $error->__invoke($this->getRequest('GET', $acceptHeader), new Response(), $throwable);

        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame($contentType, $res->getHeaderLine('Content-Type'));
        $this->assertSame(0, strpos((string)$res->getBody(), (string) $startOfBody));
    }

    /**
     * Test invalid method returns the correct code and content type
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('phpErrorProvider')]
    public function testPhpErrorDisplayDetails5($acceptHeader, $contentType, $startOfBody)
    {
        $this->skipIfPhp70();

        $error = new PhpError(true);

        /** @var Throwable $throwable */
        $throwable = $this->getMock(
            '\Throwable',
            ['getCode', 'getMessage', 'getFile', 'getLine', 'getTraceAsString', 'getPrevious']
        );

        $throwablePrev = clone $throwable;

        $throwable->method('getCode')->willReturn(1);
        $throwable->method('getMessage')->willReturn('Oops');
        $throwable->method('getFile')->willReturn('test.php');
        $throwable->method('getLine')->willReturn('1');
        $throwable->method('getTraceAsString')->willReturn('This is error');
        $throwable->method('getPrevious')->willReturn($throwablePrev);

        $res = $error->__invoke($this->getRequest('GET', $acceptHeader), new Response(), $throwable);

        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame($contentType, $res->getHeaderLine('Content-Type'));
        $this->assertSame(0, strpos((string)$res->getBody(), (string) $startOfBody));
    }

    /**
     * @requires PHP 5.0
     * @expectedException UnexpectedValueException
     */
    public function testNotFoundContentType5()
    {
        $this->skipIfPhp70();
        $errorMock = $this->getMock(PhpError::class, ['determineContentType']);
        $errorMock->method('determineContentType')
            ->willReturn('unknown/type');

        $throwable = $this->getMockBuilder('Throwable')->getMock();
        $req = $this->getMockBuilder(\Slim\Http\Request::class)->disableOriginalConstructor()->getMock();

        $errorMock->__invoke($req, new Response(), $throwable);
    }

    /**
     * @param string $method
     *
     * @return PHPUnit_Framework_MockObject_MockObject|Request
     */
    protected function getRequest($method, $acceptHeader)
    {
        $req = $this->getMockBuilder(\Slim\Http\Request::class)->disableOriginalConstructor()->getMock();
        $req->expects($this->once())->method('getHeaderLine')->willReturn($acceptHeader);

        return $req;
    }

    /**
     * @return mixed
     */
    protected function skipIfPhp70()
    {
        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            $this->markTestSkipped();
        }
    }
}
