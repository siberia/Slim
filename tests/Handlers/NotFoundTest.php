<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests\Handlers;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Handlers\NotFound;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Uri;

class NotFoundTest extends TestCase
{
    public static function notFoundProvider(): \Iterator
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
     * @dataProvider notFoundProvider
     */
    public function testNotFound($acceptHeader, $contentType, $startOfBody)
    {
        $notAllowed = new NotFound();

        /** @var Response $res */
        $res = $notAllowed->__invoke($this->getRequest('GET', $acceptHeader), new Response());

        $this->assertSame(404, $res->getStatusCode());
        $this->assertSame($contentType, $res->getHeaderLine('Content-Type'));
        $this->assertSame(0, strpos((string)$res->getBody(), (string) $startOfBody));
    }

    public function testNotFoundContentType()
    {
        $errorMock = $this->getMockBuilder(NotFound::class)->getMock();
        $errorMock->method('determineContentType')
            ->willReturn('unknown/type');

        $req = $this->getMockBuilder(\Slim\Http\Request::class)->disableOriginalConstructor()->getMock();

        $this->setExpectedException('\UnexpectedValueException');
        $errorMock->__invoke($req, new Response(), ['POST']);
    }

    /**
     * @param string $method
     *
     * @return PHPUnit_Framework_MockObject_MockObject|Request
     */
    protected function getRequest($method, $contentType = 'text/html')
    {
        $uri = new Uri('http', 'example.com', 80, '/notfound');

        $req = $this->getMockBuilder(\Slim\Http\Request::class)->disableOriginalConstructor()->getMock();
        $req->expects($this->once())->method('getHeaderLine')->willReturn($contentType);
        $req->method('getUri')->willReturn($uri);

        return $req;
    }
}
