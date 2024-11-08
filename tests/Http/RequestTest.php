<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\MethodProphecy;
use Psr\Http\Message\UriInterface;
use ReflectionProperty;
use RuntimeException;
use Slim\Collection;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\RequestBody;
use Slim\Http\UploadedFile;
use Slim\Http\Uri;

class RequestTest extends TestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;
    public function requestFactory($envData = [])
    {
        $env = Environment::mock($envData);

        $uri = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = Headers::createFromEnvironment($env);
        $cookies = [
            'user' => 'john',
            'id' => '123',
        ];
        $serverParams = $env->all();
        $body = new RequestBody();
        $uploadedFiles = UploadedFile::createFromEnvironment($env);
        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body, $uploadedFiles);

        return $request;
    }

    public function testDisableSetter()
    {
        $request = $this->requestFactory();
        $request->foo = 'bar';

        $this->assertObjectNotHasProperty('foo', $request);
    }

    public function testDoesNotAddHostHeaderFromUriIfAlreadySet()
    {
        $request = $this->requestFactory();

        $this->assertSame('localhost', $request->getHeaderLine('Host'));
    }

    public function testAddsHostHeaderFromUriIfNotSet()
    {
        $env = Environment::mock();

        $uri = Uri::createFromString('https://example.com/foo/bar?abc=123');

        $headers = Headers::createFromEnvironment($env);
        $headers->remove('Host');

        $cookies = [
            'user' => 'john',
            'id' => '123',
        ];

        $serverParams = $env->all();
        $body = new RequestBody();
        $uploadedFiles = UploadedFile::createFromEnvironment($env);

        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body, $uploadedFiles);

        $this->assertSame('example.com', $request->getHeaderLine('Host'));
    }

    public function testAddsPortToHostHeaderIfSetWhenHostHeaderIsMissingFromRequest()
    {
        $env = Environment::mock();

        $uri = Uri::createFromString('https://example.com:8443/foo/bar?abc=123');

        $headers = Headers::createFromEnvironment($env);
        $headers->remove('Host');

        $cookies = [
            'user' => 'john',
            'id' => '123',
        ];

        $serverParams = $env->all();
        $body = new RequestBody();
        $uploadedFiles = UploadedFile::createFromEnvironment($env);

        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body, $uploadedFiles);

        $this->assertSame('example.com:8443', $request->getHeaderLine('Host'));
    }

    public function testDoesntAddHostHeaderFromUriIfNeitherAreSet()
    {
        $env = Environment::mock();

        $uri = Uri::createFromString('https://example.com/foo/bar?abc=123')
            ->withHost('');

        $headers = Headers::createFromEnvironment($env);
        $headers->remove('Host');

        $cookies = [
            'user' => 'john',
            'id' => '123',
        ];

        $serverParams = $env->all();
        $body = new RequestBody();
        $uploadedFiles = UploadedFile::createFromEnvironment($env);

        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body, $uploadedFiles);

        $this->assertSame('', $request->getHeaderLine('Host'));
    }

    public function testGetMethod()
    {
        $this->assertSame('GET', $this->requestFactory()->getMethod());
    }

    public function testGetOriginalMethod()
    {
        $this->assertSame('GET', $this->requestFactory()->getOriginalMethod());
    }

    public function testWithMethod()
    {
        $request = $this->requestFactory()->withMethod('PUT');

        $this->assertAttributeEquals('PUT', 'method', $request);
        $this->assertAttributeEquals('PUT', 'originalMethod', $request);
    }

    public function testWithAllAllowedCharactersMethod()
    {
        $request = $this->requestFactory()->withMethod("!#$%&'*+.^_`|~09AZ-");

        $this->assertAttributeEquals("!#$%&'*+.^_`|~09AZ-", 'method', $request);
        $this->assertAttributeEquals("!#$%&'*+.^_`|~09AZ-", 'originalMethod', $request);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithMethodInvalid()
    {
        $this->requestFactory()->withMethod('B@R');
    }

    public function testWithMethodNull()
    {
        $request = $this->requestFactory()->withMethod(null);

        $this->assertAttributeEquals(null, 'originalMethod', $request);
    }

    public function testCreateFromEnvironment()
    {
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'POST',
        ]);

        $request = Request::createFromEnvironment($env);
        $this->assertSame('POST', $request->getMethod());
        $this->assertEquals($env->all(), $request->getServerParams());
    }

    public function testCreateFromEnvironmentWithMultipart()
    {
        $_POST['foo'] = 'bar';

        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'POST',
            'HTTP_CONTENT_TYPE' => 'multipart/form-data; boundary=---foo'
        ]);

        $request = Request::createFromEnvironment($env);
        unset($_POST);

        $this->assertSame(['foo' => 'bar'], $request->getParsedBody());
    }

    public function testCreateFromEnvironmentWithMultipartAndQueryParams()
    {
        $_POST['123'] = 'bar';

        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'POST',
            'QUERY_STRING' => '123=zar',
            'HTTP_CONTENT_TYPE' => 'multipart/form-data; boundary=---foo'
        ]);

        $request = Request::createFromEnvironment($env);
        unset($_POST);

        # Fixes the bug that would make it previously return [ 0 => 'bar' ]
        $this->assertSame(['123' => 'bar'], $request->getParams());
        $this->assertSame(['123' => 'zar'], $request->getQueryParams());
    }

    public function testCreateFromEnvironmentWithMultipartMethodOverride()
    {
        $_POST['_METHOD'] = 'PUT';

        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'POST',
            'HTTP_CONTENT_TYPE' => 'multipart/form-data; boundary=---foo'
        ]);

        $request = Request::createFromEnvironment($env);
        unset($_POST);

        $this->assertSame('POST', $request->getOriginalMethod());
        $this->assertSame('PUT', $request->getMethod());
    }

    public function testGetMethodWithOverrideHeader()
    {
        $uri = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new Headers([
            'HTTP_X_HTTP_METHOD_OVERRIDE' => 'PUT',
        ]);
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $request = new Request('POST', $uri, $headers, $cookies, $serverParams, $body);

        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('POST', $request->getOriginalMethod());
    }

    public function testGetMethodWithOverrideParameterFromBodyObject()
    {
        $uri = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new Headers([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('_METHOD=PUT');
        $body->rewind();
        $request = new Request('POST', $uri, $headers, $cookies, $serverParams, $body);

        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('POST', $request->getOriginalMethod());
    }

    public function testGetMethodOverrideParameterFromBodyArray()
    {
        $uri = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new Headers([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('_METHOD=PUT');
        $body->rewind();
        $request = new Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request->registerMediaTypeParser('application/x-www-form-urlencoded', function ($input) {
            parse_str($input, $body);
            return $body; // <-- Array
        });

        $this->assertSame('PUT', $request->getMethod());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateRequestWithInvalidMethodString()
    {
        $uri = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new Headers();
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $request = new Request('B@R', $uri, $headers, $cookies, $serverParams, $body);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateRequestWithInvalidMethodOther()
    {
        $uri = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new Headers();
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $request = new Request(10, $uri, $headers, $cookies, $serverParams, $body);
    }

    public function testIsGet()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'originalMethod');
        $prop->setAccessible(true);
        $prop->setValue($request, 'GET');

        $this->assertTrue($request->isGet());
    }

    public function testIsPost()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'originalMethod');
        $prop->setAccessible(true);
        $prop->setValue($request, 'POST');

        $this->assertTrue($request->isPost());
    }

    public function testIsPut()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'originalMethod');
        $prop->setAccessible(true);
        $prop->setValue($request, 'PUT');

        $this->assertTrue($request->isPut());
    }

    public function testIsPatch()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'originalMethod');
        $prop->setAccessible(true);
        $prop->setValue($request, 'PATCH');

        $this->assertTrue($request->isPatch());
    }

    public function testIsDelete()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'originalMethod');
        $prop->setAccessible(true);
        $prop->setValue($request, 'DELETE');

        $this->assertTrue($request->isDelete());
    }

    public function testIsHead()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'originalMethod');
        $prop->setAccessible(true);
        $prop->setValue($request, 'HEAD');

        $this->assertTrue($request->isHead());
    }

    public function testIsOptions()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'originalMethod');
        $prop->setAccessible(true);
        $prop->setValue($request, 'OPTIONS');

        $this->assertTrue($request->isOptions());
    }

    public function testIsXhr()
    {
        $uri = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new Headers([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);

        $this->assertTrue($request->isXhr());
    }

    public function testGetRequestTarget()
    {
        $this->assertSame('/foo/bar?abc=123', $this->requestFactory()->getRequestTarget());
    }

    public function testGetRequestTargetAlreadySet()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'requestTarget');
        $prop->setAccessible(true);
        $prop->setValue($request, '/foo/bar?abc=123');

        $this->assertSame('/foo/bar?abc=123', $request->getRequestTarget());
    }

    public function testGetRequestTargetIfNoUri()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'uri');
        $prop->setAccessible(true);
        $prop->setValue($request, null);

        $this->assertSame('/', $request->getRequestTarget());
    }

    public function testGetRequestTargetWithSlimPsr7Uri()
    {
        $basePath = '/base/path';
        $path = 'foo';
        $query = 'bar=1';

        $uriProphecy = $this->prophesize(Uri::class);
        $uriGetBasePathProphecy = new MethodProphecy($uriProphecy, 'getBasePath', [Argument::any()]);
        $uriGetBasePathProphecy->willReturn($basePath)->shouldBeCalledOnce();
        $uriGetPathProphecy = new MethodProphecy($uriProphecy, 'getPath', [Argument::any()]);
        $uriGetPathProphecy->willReturn($path);
        $uriGetQueryProphecy = new MethodProphecy($uriProphecy, 'getQuery', [Argument::any()]);
        $uriGetQueryProphecy->willReturn($query);

        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'uri');
        $prop->setAccessible(true);
        $prop->setValue($request, $uriProphecy->reveal());

        $this->assertSame($basePath . '/' . $path . '?' . $query, $request->getRequestTarget());
    }

    public function testGetRequestTargetWithNonSlimPsr7Uri()
    {
        // We still pass in a UriInterface, which isn't an instance of Slim URI
        $uriProphecy = $this->prophesize(UriInterface::class);

        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'uri');
        $prop->setAccessible(true);
        $prop->setValue($request, $uriProphecy->reveal());

        $this->assertSame('/', $request->getRequestTarget());
    }

    public function testWithRequestTarget()
    {
        $clone = $this->requestFactory()->withRequestTarget('/test?user=1');

        $this->assertAttributeEquals('/test?user=1', 'requestTarget', $clone);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithRequestTargetThatHasSpaces()
    {
        $this->requestFactory()->withRequestTarget('/test/m ore/stuff?user=1');
    }

    public function testGetUri()
    {
        $uri = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new Headers();
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $request = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);

        $this->assertSame($uri, $request->getUri());
    }

    public function testWithUri()
    {
        // Uris
        $uri1 = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $uri2 = Uri::createFromString('https://example2.com:443/test?xyz=123');

        // Request
        $headers = new Headers();
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $request = new Request('GET', $uri1, $headers, $cookies, $serverParams, $body);
        $clone = $request->withUri($uri2);

        $this->assertAttributeSame($uri2, 'uri', $clone);
    }

    public function testWithUriPreservesHost()
    {
        // When `$preserveHost` is set to `true`, this method interacts with
        // the Host header in the following ways:

        // - If the the Host header is missing or empty, and the new URI contains
        //   a host component, this method MUST update the Host header in the returned
        //   request.
        $uri1 = Uri::createFromString('');
        $uri2 = Uri::createFromString('http://example2.com/test');

        // Request
        $headers = new Headers();
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $request = new Request('GET', $uri1, $headers, $cookies, $serverParams, $body);

        $clone = $request->withUri($uri2, true);
        $this->assertSame('example2.com', $clone->getHeaderLine('Host'));

        // - If the Host header is missing or empty, and the new URI does not contain a
        //   host component, this method MUST NOT update the Host header in the returned
        //   request.
        $uri3 = Uri::createFromString('');

        $clone = $request->withUri($uri3, true);
        $this->assertSame('', $clone->getHeaderLine('Host'));

        // - If a Host header is present and non-empty, this method MUST NOT update
        //   the Host header in the returned request.
        $request = $request->withHeader('Host', 'example.com');
        $clone = $request->withUri($uri2, true);
        $this->assertSame('example.com', $clone->getHeaderLine('Host'));
    }

    public function testGetContentType()
    {
        $headers = new Headers([
            'Content-Type' => ['application/json;charset=utf8'],
        ]);
        $request = $this->requestFactory();
        $headersProp = new ReflectionProperty($request, 'headers');
        $headersProp->setAccessible(true);
        $headersProp->setValue($request, $headers);

        $this->assertSame('application/json;charset=utf8', $request->getContentType());
    }

    public function testGetContentTypeEmpty()
    {
        $request = $this->requestFactory();

        $this->assertNull($request->getContentType());
    }

    public function testGetMediaType()
    {
        $headers = new Headers([
            'Content-Type' => ['application/json;charset=utf8'],
        ]);
        $request = $this->requestFactory();
        $headersProp = new ReflectionProperty($request, 'headers');
        $headersProp->setAccessible(true);
        $headersProp->setValue($request, $headers);

        $this->assertSame('application/json', $request->getMediaType());
    }

    public function testGetMediaTypeEmpty()
    {
        $request = $this->requestFactory();

        $this->assertNull($request->getMediaType());
    }

    public function testGetMediaTypeParams()
    {
        $headers = new Headers([
            'Content-Type' => ['application/json;charset=utf8;foo=bar'],
        ]);
        $request = $this->requestFactory();
        $headersProp = new ReflectionProperty($request, 'headers');
        $headersProp->setAccessible(true);
        $headersProp->setValue($request, $headers);

        $this->assertSame(['charset' => 'utf8', 'foo' => 'bar'], $request->getMediaTypeParams());
    }

    public function testGetMediaTypeParamsEmpty()
    {
        $headers = new Headers([
            'Content-Type' => ['application/json'],
        ]);
        $request = $this->requestFactory();
        $headersProp = new ReflectionProperty($request, 'headers');
        $headersProp->setAccessible(true);
        $headersProp->setValue($request, $headers);

        $this->assertSame([], $request->getMediaTypeParams());
    }

    public function testGetMediaTypeParamsWithoutHeader()
    {
        $request = $this->requestFactory();

        $this->assertSame([], $request->getMediaTypeParams());
    }

    public function testGetContentCharset()
    {
        $headers = new Headers([
            'Content-Type' => ['application/json;charset=utf8'],
        ]);
        $request = $this->requestFactory();
        $headersProp = new ReflectionProperty($request, 'headers');
        $headersProp->setAccessible(true);
        $headersProp->setValue($request, $headers);

        $this->assertSame('utf8', $request->getContentCharset());
    }

    public function testGetContentCharsetEmpty()
    {
        $headers = new Headers([
            'Content-Type' => ['application/json'],
        ]);
        $request = $this->requestFactory();
        $headersProp = new ReflectionProperty($request, 'headers');
        $headersProp->setAccessible(true);
        $headersProp->setValue($request, $headers);

        $this->assertNull($request->getContentCharset());
    }

    public function testGetContentCharsetWithoutHeader()
    {
        $request = $this->requestFactory();

        $this->assertNull($request->getContentCharset());
    }

    public function testGetContentLength()
    {
        $headers = new Headers([
            'Content-Length' => '150', // <-- Note we define as a string
        ]);
        $request = $this->requestFactory();
        $headersProp = new ReflectionProperty($request, 'headers');
        $headersProp->setAccessible(true);
        $headersProp->setValue($request, $headers);

        $this->assertSame(150, $request->getContentLength());
    }

    public function testGetContentLengthWithoutHeader()
    {
        $request = $this->requestFactory();

        $this->assertNull($request->getContentLength());
    }

    public function testGetCookieParam()
    {
        $shouldBe = 'john';

        $this->assertSame($shouldBe, $this->requestFactory()->getCookieParam('user'));
    }

    public function testGetCookieParamWithDefault()
    {
        $shouldBe = 'bar';

        $this->assertSame($shouldBe, $this->requestFactory()->getCookieParam('foo', 'bar'));
    }

    public function testGetCookieParams()
    {
        $shouldBe = [
            'user' => 'john',
            'id' => '123',
        ];

        $this->assertSame($shouldBe, $this->requestFactory()->getCookieParams());
    }

    public function testWithCookieParams()
    {
        $request = $this->requestFactory();
        $clone = $request->withCookieParams(['type' => 'framework']);

        $this->assertSame(['type' => 'framework'], $clone->getCookieParams());
    }

    public function testGetQueryParams()
    {
        $this->assertSame(['abc' => '123'], $this->requestFactory()->getQueryParams());
    }

    public function testGetQueryParamsAlreadySet()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'queryParams');
        $prop->setAccessible(true);
        $prop->setValue($request, ['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $request->getQueryParams());
    }

    public function testWithQueryParams()
    {
        $request = $this->requestFactory();
        $clone = $request->withQueryParams(['foo' => 'bar']);
        $cloneUri = $clone->getUri();

        $this->assertSame('abc=123', $cloneUri->getQuery()); // <-- Unchanged
        $this->assertSame(['foo' => 'bar'], $clone->getQueryParams()); // <-- Changed
    }

    public function testWithQueryParamsEmptyArray()
    {
        $request = $this->requestFactory();
        $clone = $request->withQueryParams([]);
        $cloneUri = $clone->getUri();

        $this->assertSame('abc=123', $cloneUri->getQuery()); // <-- Unchanged
        $this->assertSame([], $clone->getQueryParams()); // <-- Changed
    }

    public function testGetQueryParamsWithoutUri()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'uri');
        $prop->setAccessible(true);
        $prop->setValue($request, null);

        $this->assertSame([], $request->getQueryParams());
    }

    public function testWithUploadedFiles()
    {
        $files = [new UploadedFile('foo.txt'), new UploadedFile('bar.txt')];

        $request = $this->requestFactory();
        $clone = $request->withUploadedFiles($files);

        $this->assertSame([], $request->getUploadedFiles());
        $this->assertEquals($files, $clone->getUploadedFiles());
    }

    public function testGetServerParams()
    {
        $mockEnv = Environment::mock(["HTTP_AUTHORIZATION" => "test"]);
        $request = $this->requestFactory(["HTTP_AUTHORIZATION" => "test"]);

        $serverParams = $request->getServerParams();
        foreach ($serverParams as $key => $value) {
            if ($key == 'REQUEST_TIME' || $key == 'REQUEST_TIME_FLOAT') {
                $this->assertGreaterThanOrEqual(
                    $mockEnv[$key],
                    $value,
                    sprintf("%s value of %s was less than expected value of %s", $key, $value, $mockEnv[$key])
                );
            } else {
                $this->assertEquals(
                    $mockEnv[$key],
                    $value,
                    sprintf("%s value of %s did not equal expected value of %s", $key, $value, $mockEnv[$key])
                );
            }
        }
    }

    public function testGetServerParam()
    {
        $shouldBe = 'HTTP/1.1';
        $request = $this->requestFactory(['SERVER_PROTOCOL' => 'HTTP/1.1']);

        $this->assertSame($shouldBe, $this->requestFactory()->getServerParam('SERVER_PROTOCOL'));
    }

    public function testGetServerParamWithDefault()
    {
        $shouldBe = 'bar';

        $this->assertSame($shouldBe, $this->requestFactory()->getServerParam('HTTP_NOT_EXIST', 'bar'));
    }

    public function testGetAttributes()
    {
        $request = $this->requestFactory();
        $attrProp = new ReflectionProperty($request, 'attributes');
        $attrProp->setAccessible(true);
        $attrProp->setValue($request, new Collection(['foo' => 'bar']));

        $this->assertSame(['foo' => 'bar'], $request->getAttributes());
    }

    public function testGetAttribute()
    {
        $request = $this->requestFactory();
        $attrProp = new ReflectionProperty($request, 'attributes');
        $attrProp->setAccessible(true);
        $attrProp->setValue($request, new Collection(['foo' => 'bar']));

        $this->assertSame('bar', $request->getAttribute('foo'));
        $this->assertNull($request->getAttribute('bar'));
        $this->assertSame(2, $request->getAttribute('bar', 2));
    }

    public function testWithAttribute()
    {
        $request = $this->requestFactory();
        $attrProp = new ReflectionProperty($request, 'attributes');
        $attrProp->setAccessible(true);
        $attrProp->setValue($request, new Collection(['foo' => 'bar']));
        $clone = $request->withAttribute('test', '123');

        $this->assertSame('123', $clone->getAttribute('test'));
    }

    public function testWithAttributes()
    {
        $request = $this->requestFactory();
        $attrProp = new ReflectionProperty($request, 'attributes');
        $attrProp->setAccessible(true);
        $attrProp->setValue($request, new Collection(['foo' => 'bar']));
        $clone = $request->withAttributes(['test' => '123']);

        $this->assertNull($clone->getAttribute('foo'));
        $this->assertSame('123', $clone->getAttribute('test'));
    }

    public function testWithoutAttribute()
    {
        $request = $this->requestFactory();
        $attrProp = new ReflectionProperty($request, 'attributes');
        $attrProp->setAccessible(true);
        $attrProp->setValue($request, new Collection(['foo' => 'bar']));
        $clone = $request->withoutAttribute('foo');

        $this->assertNull($clone->getAttribute('foo'));
    }

    public function testGetParsedBodyForm()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/x-www-form-urlencoded;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('foo=bar');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);
        $this->assertSame(['foo' => 'bar'], $request->getParsedBody());
    }

    public function testGetParsedBodyJson()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/json;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('{"foo":"bar"}');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $this->assertSame(['foo' => 'bar'], $request->getParsedBody());
    }

    public function testGetParsedBodyInvalidJson()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/json;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('{foo}bar');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $this->assertNull($request->getParsedBody());
    }

    public function testGetParsedBodySemiValidJson()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/json;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('"foo bar"');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $this->assertNull($request->getParsedBody());
    }

    public function testGetParsedBodyWithJsonStructuredSuffix()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/vnd.api+json;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('{"foo":"bar"}');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $this->assertSame(['foo' => 'bar'], $request->getParsedBody());
    }

    public function testGetParsedBodyWithJsonStructuredSuffixAndRegisteredParser()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/vnd.api+json;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('{"foo":"bar"}');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $request->registerMediaTypeParser('application/vnd.api+json', fn($input) => ['data' => $input]);

        $this->assertSame(['data' => '{"foo":"bar"}'], $request->getParsedBody());
    }

    public function testGetParsedBodyXml()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/xml;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('<person><name>Josh</name></person>');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $this->assertSame('Josh', $request->getParsedBody()->name);
    }

    public function testGetParsedBodyWithXmlStructuredSuffix()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/hal+xml;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('<person><name>Josh</name></person>');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $this->assertSame('Josh', $request->getParsedBody()->name);
    }

    public function testGetParsedBodyXmlWithTextXMLMediaType()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'text/xml');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('<person><name>Josh</name></person>');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $this->assertSame('Josh', $request->getParsedBody()->name);
    }

    /**
     * Will fail if a simple_xml warning is created
     */
    public function testInvalidXmlIsQuietForTextXml()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'text/xml');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('<person><name>Josh</name></invalid]>');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $this->assertEquals(null, $request->getParsedBody());
    }

    /**
     * Will fail if a simple_xml warning is created
     */
    public function testInvalidXmlIsQuietForApplicationXml()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/xml');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('<person><name>Josh</name></invalid]>');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);

        $this->assertEquals(null, $request->getParsedBody());
    }

    public function testGetParsedBodyWhenAlreadyParsed()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'bodyParsed');
        $prop->setAccessible(true);
        $prop->setValue($request, ['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $request->getParsedBody());
    }

    public function testGetParsedBodyWhenBodyDoesNotExist()
    {
        $request = $this->requestFactory();
        $prop = new ReflectionProperty($request, 'body');
        $prop->setAccessible(true);
        $prop->setValue($request, null);

        $this->assertNull($request->getParsedBody());
    }

    public function testGetParsedBodyAfterCallReparseBody()
    {
        $uri = Uri::createFromString('https://example.com:443/?one=1');
        $headers = new Headers([
            'Content-Type' => 'application/x-www-form-urlencoded;charset=utf8',
        ]);
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('foo=bar');
        $body->rewind();
        $request = new Request('POST', $uri, $headers, $cookies, $serverParams, $body);

        $this->assertSame(['foo' => 'bar'], $request->getParsedBody());

        $newBody = new RequestBody();
        $newBody->write('abc=123');
        $newBody->rewind();
        $request = $request->withBody($newBody);
        $request->reparseBody();

        $this->assertSame(['abc' => '123'], $request->getParsedBody());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testGetParsedBodyAsArray()
    {
        $uri = Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new Headers([
            'Content-Type' => 'application/json;charset=utf8',
        ]);
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('{"foo": "bar"}');
        $body->rewind();
        $request = new Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $request->registerMediaTypeParser('application/json', function ($input) {
            return 10; // <-- Return invalid body value
        });
        $request->getParsedBody(); // <-- Triggers exception
    }

    public function testWithParsedBody()
    {
        $clone = $this->requestFactory()->withParsedBody(['xyz' => '123']);

        $this->assertSame(['xyz' => '123'], $clone->getParsedBody());
    }

    public function testWithParsedBodyEmptyArray()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/x-www-form-urlencoded;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('foo=bar');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);


        $clone = $request->withParsedBody([]);

        $this->assertSame([], $clone->getParsedBody());
    }

    public function testWithParsedBodyNull()
    {
        $method = 'GET';
        $uri = new Uri('https', 'example.com', 443, '/foo/bar', 'abc=123', '', '');
        $headers = new Headers();
        $headers->set('Content-Type', 'application/x-www-form-urlencoded;charset=utf8');
        $cookies = [];
        $serverParams = [];
        $body = new RequestBody();
        $body->write('foo=bar');
        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);


        $clone = $request->withParsedBody(null);

        $this->assertNull($clone->getParsedBody());
    }

    public function testGetParsedBodyReturnsNullWhenThereIsNoBodyData()
    {
        $request = $this->requestFactory(['REQUEST_METHOD' => 'POST']);

        $this->assertNull($request->getParsedBody());
    }

    public function testGetParsedBodyReturnsNullWhenThereIsNoMediaTypeParserRegistered()
    {
        $request = $this->requestFactory([
            'REQUEST_METHOD' => 'POST',
            'CONTENT_TYPE' => 'text/csv',
        ]);
        $request->getBody()->write('foo,bar,baz');

        $this->assertNull($request->getParsedBody());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithParsedBodyInvalid()
    {
        $this->requestFactory()->withParsedBody(2);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithParsedBodyInvalidFalseValue()
    {
        $this->requestFactory()->withParsedBody(false);
    }

    public function testGetParameterFromBody()
    {
        $body = new RequestBody();
        $body->write('foo=bar');
        $body->rewind();
        $request = $this->requestFactory()
                   ->withBody($body)
                   ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame('bar', $request->getParam('foo'));
    }

    public function testGetParameterFromBodyWithBodyParemeterHelper()
    {
        $body = new RequestBody();
        $body->write('foo=bar');
        $body->rewind();
        $request = $this->requestFactory()
            ->withBody($body)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame('bar', $request->getParsedBodyParam('foo'));
    }

    public function testGetParameterFromQuery()
    {
        $request = $this->requestFactory()->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame('123', $request->getParam('abc'));
    }

    public function testGetParameterFromQueryWithQueryParemeterHelper()
    {
        $request = $this->requestFactory()->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame('123', $request->getQueryParam('abc'));
    }

    public function testGetParameterFromBodyOverQuery()
    {
        $body = new RequestBody();
        $body->write('abc=xyz');
        $body->rewind();
        $request = $this->requestFactory()
                   ->withBody($body)
                   ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->assertSame('xyz', $request->getParam('abc'));
    }

    public function testGetParameterWithDefaultFromBodyOverQuery()
    {
        $body = new RequestBody();
        $body->write('abc=xyz');
        $body->rewind();
        $request = $this->requestFactory()
                   ->withBody($body)
                   ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $this->assertSame('xyz', $request->getParam('abc'));
        $this->assertSame('bar', $request->getParam('foo', 'bar'));
    }

    public function testGetParameters()
    {
        $body = new RequestBody();
        $body->write('foo=bar');
        $body->rewind();
        $request = $this->requestFactory()
                   ->withBody($body)
                   ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame(['abc' => '123', 'foo' => 'bar'], $request->getParams());
    }

    public function testGetParametersWithBodyPriority()
    {
        $body = new RequestBody();
        $body->write('foo=bar&abc=xyz');
        $body->rewind();
        $request = $this->requestFactory()
                   ->withBody($body)
                   ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame(['abc' => 'xyz', 'foo' => 'bar'], $request->getParams());
    }

    public function testGetParametersWithSpecificKeys()
    {
        $body = new RequestBody();
        $body->write('foo=bar&abc=xyz');
        $body->rewind();
        $request = $this->requestFactory()
            ->withBody($body)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame(['abc' => 'xyz'], $request->getParams(['abc']));
    }

    public function testGetParametersWithSpecificKeysAreMissingIfTheyDontExit()
    {
        $body = new RequestBody();
        $body->write('foo=bar');
        $body->rewind();
        $request = $this->requestFactory()
            ->withBody($body)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->assertSame(['foo' => 'bar'], $request->getParams(['foo', 'bar']));
    }

    public function testGetProtocolVersion()
    {
        $env = Environment::mock(['SERVER_PROTOCOL' => 'HTTP/1.0']);
        $request = Request::createFromEnvironment($env);

        $this->assertSame('1.0', $request->getProtocolVersion());
    }
}
