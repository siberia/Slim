<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests\Handlers;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Handlers\NotAllowed;
use Slim\Http\Request;
use Slim\Http\Response;

class NotAllowedTest extends TestCase
{
    public static function invalidMethodProvider(): \Iterator
    {
        yield ['application/json', 'application/json', '{'];
        yield ['application/vnd.api+json', 'application/json', '{'];
        yield ['application/xml', 'application/xml', '<root>'];
        yield ['application/hal+xml', 'application/xml', '<root>'];
        yield ['text/xml', 'text/xml', '<root>'];
        yield ['text/html', 'text/html', '<html>'];
    }

    /**
     * Test invalid method returns the correct code and content type
     *
     * @dataProvider invalidMethodProvider
     */
    public function testInvalidMethod($acceptHeader, $contentType, $startOfBody)
    {
        $notAllowed = new NotAllowed();

        /** @var Response $res */
        $res = $notAllowed->__invoke($this->getRequest('GET', $acceptHeader), new Response(), ['POST', 'PUT']);

        $this->assertSame(405, $res->getStatusCode());
        $this->assertTrue($res->hasHeader('Allow'));
        $this->assertSame($contentType, $res->getHeaderLine('Content-Type'));
        $this->assertSame('POST, PUT', $res->getHeaderLine('Allow'));
        $this->assertSame(0, strpos((string)$res->getBody(), (string) $startOfBody));
    }

    public function testOptions()
    {
        $notAllowed = new NotAllowed();

        /** @var Response $res */
        $res = $notAllowed->__invoke($this->getRequest('OPTIONS'), new Response(), ['POST', 'PUT']);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($res->hasHeader('Allow'));
        $this->assertSame('POST, PUT', $res->getHeaderLine('Allow'));
    }

    public function testNotFoundContentType()
    {
        $errorMock = $this->getMockBuilder(NotAllowed::class)->getMock();
        $errorMock->method('determineContentType')
            ->willReturn('unknown/type');

        $this->setExpectedException('\UnexpectedValueException');
        $errorMock->__invoke($this->getRequest('GET', 'unknown/type'), new Response(), ['POST']);
    }

    /**
     * @param string $method
     *
     * @return PHPUnit_Framework_MockObject_MockObject|Request
     */
    protected function getRequest($method, $contentType = 'text/html')
    {
        $req = $this->getMockBuilder(\Slim\Http\Request::class)->disableOriginalConstructor()->getMock();
        $req->expects($this->once())->method('getMethod')->willReturn($method);
        $req->method('getHeaderLine')->willReturn($contentType);

        return $req;
    }
}
