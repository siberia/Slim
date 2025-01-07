<?php
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests\Handlers;

use Exception;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Slim\Handlers\Error;
use Slim\Http\Request;
use Slim\Http\Response;
use UnexpectedValueException;

class ErrorTest extends TestCase
{
    public static function errorProvider(): \Iterator
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
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('errorProvider')]
    public function testError($acceptHeader, $contentType, $startOfBody)
    {
        $error = new Error();
        $e = new Exception("Oops", 1, new Exception('Previous oops'));

        /** @var Response $res */
        $res = $error->__invoke($this->getRequest('GET', $acceptHeader), new Response(), $e);

        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame($contentType, $res->getHeaderLine('Content-Type'));
        $this->assertSame(0, strpos((string)$res->getBody(), (string) $startOfBody));
    }

    /**
     * Test invalid method returns the correct code and content type with details
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('errorProvider')]
    public function testErrorDisplayDetails($acceptHeader, $contentType, $startOfBody)
    {
        $error = new Error(true);
        $e = new Exception('Oops', 1, new Exception('Opps before'));

        /** @var Response $res */
        $res = $error->__invoke($this->getRequest('GET', $acceptHeader), new Response(), $e);

        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame($contentType, $res->getHeaderLine('Content-Type'));
        $this->assertSame(0, strpos((string)$res->getBody(), (string) $startOfBody));
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testNotFoundContentType()
    {
        $errorMock = $this->getMockBuilder(Error::class)->getMock();
        $errorMock->method('determineContentType')
            ->willReturn('unknown/type');

        $e = new Exception("Oops");

        $req = $this->getMockBuilder(\Slim\Http\Request::class)->disableOriginalConstructor()->getMock();

        $errorMock->__invoke($req, new Response(), $e);
    }

    /**
     * Test that an exception with a previous exception provides correct output
     * to the error log
     */
    public function testPreviousException()
    {
        $error = $this->getMockBuilder(\Slim\Handlers\Error::class)->getMock();
        $error->expects($this->once())->method('logError')->with(
            $this->logicalAnd(
                $this->stringContains("Type: Exception" . PHP_EOL . "Message: Second Oops"),
                $this->stringContains("Previous Error:" . PHP_EOL . "Type: Exception" . PHP_EOL . "Message: First Oops")
            )
        );

        $first = new Exception("First Oops");
        $second = new Exception("Second Oops", 0, $first);

        $error->__invoke($this->getRequest('GET', 'application/json'), new Response(), $second);
    }

    /**
     * If someone extends the Error handler and calls renderHtmlExceptionOrError with
     * a parameter that isn't an Exception or Error, then we thrown an Exception.
     */
    public function testRenderHtmlExceptionOrErrorTypeChecksParameter()
    {
        $class = new ReflectionClass(Error::class);
        $renderHtmlExceptionorError = $class->getMethod('renderHtmlExceptionOrError');
        $renderHtmlExceptionorError->setAccessible(true);

        $this->setExpectedException(RuntimeException::class);

        $error = new Error();
        $renderHtmlExceptionorError->invokeArgs($error, ['foo']);
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
}
