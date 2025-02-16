<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Slim\Http\Body;
use Slim\Http\Headers;
use Slim\Http\Response;

class ResponseTest extends TestCase
{
    public function testConstructorWithDefaultArgs()
    {
        $response = new Response();

        $this->assertAttributeEquals(200, 'status', $response);
        $this->assertAttributeInstanceOf(\Slim\Http\Headers::class, 'headers', $response);
        $this->assertAttributeInstanceOf(\Psr\Http\Message\StreamInterface::class, 'body', $response);
    }

    public function testConstructorWithCustomArgs()
    {
        $headers = new Headers();
        $body = new Body(fopen('php://temp', 'r+'));
        $response = new Response(404, $headers, $body);

        $this->assertAttributeEquals(404, 'status', $response);
        $this->assertAttributeSame($headers, 'headers', $response);
        $this->assertAttributeSame($body, 'body', $response);
    }

    public function testDeepCopyClone()
    {
        $headers = new Headers();
        $body = new Body(fopen('php://temp', 'r+'));
        $response = new Response(404, $headers, $body);
        $clone = clone $response;

        $this->assertAttributeEquals('1.1', 'protocolVersion', $clone);
        $this->assertAttributeEquals(404, 'status', $clone);
        $this->assertAttributeNotSame($headers, 'headers', $clone);
        $this->assertAttributeSame($body, 'body', $clone);
    }

    public function testDisableSetter()
    {
        $response = new Response();
        $response->foo = 'bar';

        $this->assertObjectNotHasProperty('foo', $response);
    }

    public function testGetStatusCode()
    {
        $response = new Response();
        $responseStatus = new ReflectionProperty($response, 'status');
        $responseStatus->setAccessible(true);
        $responseStatus->setValue($response, '404');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testWithStatus()
    {
        $response = new Response();
        $clone = $response->withStatus(302);

        $this->assertAttributeEquals(302, 'status', $clone);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithStatusInvalidStatusCodeThrowsException()
    {
        $response = new Response();
        $response->withStatus(800);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ReasonPhrase must be a string
     */
    public function testWithStatusInvalidReasonPhraseThrowsException()
    {
        $response = new Response();
        $response->withStatus(200, null);
    }

    public function testWithStatusEmptyReasonPhrase()
    {
        $responseWithNoMessage = new Response(310);

        $this->assertSame('', $responseWithNoMessage->getReasonPhrase());
    }

    public function testGetReasonPhrase()
    {
        $response = new Response(404);

        $this->assertSame('Not Found', $response->getReasonPhrase());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ReasonPhrase must be supplied for this code
     */
    public function testMustSetReasonPhraseForUnrecognisedCode()
    {
        $response = new Response();
        $response = $response->withStatus(199);
    }

    public function testSetReasonPhraseForUnrecognisedCode()
    {
        $response = new Response();
        $response = $response->withStatus(199, 'Random Message');

        $this->assertSame('Random Message', $response->getReasonPhrase());
    }

    public function testGetCustomReasonPhrase()
    {
        $response = new Response();
        $clone = $response->withStatus(200, 'Custom Phrase');

        $this->assertSame('Custom Phrase', $clone->getReasonPhrase());
    }

    public function testWithRedirect()
    {
        $response = new Response(200);
        $clone = $response->withRedirect('/foo', 301);
        $cloneWithDefaultStatus = $response->withRedirect('/foo');
        $cloneWithStatusMethod = $response->withStatus(301)->withRedirect('/foo');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Location'));

        $this->assertSame(301, $clone->getStatusCode());
        $this->assertTrue($clone->hasHeader('Location'));
        $this->assertSame('/foo', $clone->getHeaderLine('Location'));

        $this->assertSame(302, $cloneWithDefaultStatus->getStatusCode());
        $this->assertTrue($cloneWithDefaultStatus->hasHeader('Location'));
        $this->assertSame('/foo', $cloneWithDefaultStatus->getHeaderLine('Location'));

        $this->assertSame(301, $cloneWithStatusMethod->getStatusCode());
        $this->assertTrue($cloneWithStatusMethod->hasHeader('Location'));
        $this->assertSame('/foo', $cloneWithStatusMethod->getHeaderLine('Location'));
    }

    public function testIsEmpty()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 204);

        $this->assertTrue($response->isEmpty());
    }

    public function testIsInformational()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 100);

        $this->assertTrue($response->isInformational());
    }

    public function testIsOk()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 200);

        $this->assertTrue($response->isOk());
    }

    public function testIsSuccessful()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 201);

        $this->assertTrue($response->isSuccessful());
    }

    public function testIsRedirect()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 302);

        $this->assertTrue($response->isRedirect());
    }

    public function testIsRedirection()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 308);

        $this->assertTrue($response->isRedirection());
    }

    public function testIsForbidden()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 403);

        $this->assertTrue($response->isForbidden());
    }

    public function testIsNotFound()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 404);

        $this->assertTrue($response->isNotFound());
    }

    public function testIsBadRequest()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 400);

        $this->assertTrue($response->isBadRequest());
    }

    public function testIsClientError()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 400);

        $this->assertTrue($response->isClientError());
    }

    public function testIsServerError()
    {
        $response = new Response();
        $prop = new ReflectionProperty($response, 'status');
        $prop->setAccessible(true);
        $prop->setValue($response, 503);

        $this->assertTrue($response->isServerError());
    }

    public function testToString()
    {
        $output = 'HTTP/1.1 404 Not Found' . Response::EOL .
                  'X-Foo: Bar' . Response::EOL . Response::EOL .
                  'Where am I?';
        $this->expectOutputString($output);
        $response = new Response();
        $response = $response->withStatus(404)->withHeader('X-Foo', 'Bar')->write('Where am I?');

        echo $response;
    }

    public function testWithJson()
    {
        $data = ['foo' => 'bar1&bar2'];

        $originalResponse = new Response();
        $response = $originalResponse->withJson($data, 201);

        $this->assertNotSame($response->getStatusCode(), $originalResponse->getStatusCode());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = $response->getBody();
        $body->rewind();
        $dataJson = $body->getContents(); //json_decode($body->getContents(), true);

        $originalBody = $originalResponse->getBody();
        $originalBody->rewind();
        $originalContents = $originalBody->getContents();

        // test the original body hasn't be replaced
        $this->assertNotSame($dataJson, $originalContents);

        $this->assertSame('{"foo":"bar1&bar2"}', $dataJson);
        $this->assertSame($data['foo'], json_decode($dataJson, true)['foo']);

        // Test encoding option
        $response = $response->withJson($data, 200, JSON_HEX_AMP);

        $body = $response->getBody();
        $body->rewind();
        $dataJson = $body->getContents();

        $this->assertSame('{"foo":"bar1\u0026bar2"}', $dataJson);
        $this->assertSame($data['foo'], json_decode($dataJson, true)['foo']);

        $response = $response->withStatus(201)->withJson([]);
        $this->assertSame(201, $response->getStatusCode());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testWithInvalidJsonThrowsException()
    {
        $data = ['foo' => 'bar'.chr(233)];
        $this->assertSame('bar'.chr(233), $data['foo']);

        $response = new Response();
        $response->withJson($data, 200);

        // Safety net: this assertion should not occur, since the RuntimeException
        // must have been caught earlier by the test framework
        $this->assertFalse(true);
    }

    public function testStatusIsSetTo302IfLocationIsSetWhenStatusis200()
    {
        $response = new Response();
        $response = $response->withHeader('Location', '/foo');

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testStatusIsNotSetTo302IfLocationIsSetWhenStatusisNot200()
    {
        $response = new Response();
        $response = $response->withStatus(201)->withHeader('Location', '/foo');

        $this->assertSame(201, $response->getStatusCode());
    }
}
