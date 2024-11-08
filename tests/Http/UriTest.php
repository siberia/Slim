<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests\Http;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Slim\Http\Environment;
use Slim\Http\Uri;

class UriTest extends TestCase
{
    protected $uri;

    public function uriFactory()
    {
        $scheme = 'https';
        $host = 'example.com';
        $port = 443;
        $path = '/foo/bar';
        $query = 'abc=123';
        $fragment = 'section3';
        $user = 'josh';
        $password = 'sekrit';

        return new Uri($scheme, $host, $port, $path, $query, $fragment, $user, $password);
    }

    public function testGetScheme()
    {
        $this->assertSame('https', $this->uriFactory()->getScheme());
    }

    public function testWithScheme()
    {
        $uri = $this->uriFactory()->withScheme('http');

        $this->assertAttributeEquals('http', 'scheme', $uri);
    }

    public function testWithSchemeRemovesSuffix()
    {
        $uri = $this->uriFactory()->withScheme('http://');

        $this->assertAttributeEquals('http', 'scheme', $uri);
    }

    public function testWithSchemeEmpty()
    {
        $uri = $this->uriFactory()->withScheme('');

        $this->assertAttributeEquals('', 'scheme', $uri);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Uri scheme must be one of: "", "https", "http"
     */
    public function testWithSchemeInvalid()
    {
        $this->uriFactory()->withScheme('ftp');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Uri scheme must be a string
     */
    public function testWithSchemeInvalidType()
    {
        $this->uriFactory()->withScheme([]);
    }

    public function testGetAuthorityWithUsernameAndPassword()
    {
        $this->assertSame('josh:sekrit@example.com', $this->uriFactory()->getAuthority());
    }

    public function testGetAuthorityWithUsername()
    {
        $scheme = 'https';
        $user = 'josh';
        $password = '';
        $host = 'example.com';
        $path = '/foo/bar';
        $port = 443;
        $query = 'abc=123';
        $fragment = 'section3';
        $uri = new Uri($scheme, $host, $port, $path, $query, $fragment, $user, $password);

        $this->assertSame('josh@example.com', $uri->getAuthority());
    }

    public function testGetAuthority()
    {
        $scheme = 'https';
        $user = '';
        $password = '';
        $host = 'example.com';
        $path = '/foo/bar';
        $port = 443;
        $query = 'abc=123';
        $fragment = 'section3';
        $uri = new Uri($scheme, $host, $port, $path, $query, $fragment, $user, $password);

        $this->assertSame('example.com', $uri->getAuthority());
    }

    public function testGetAuthorityWithNonStandardPort()
    {
        $scheme = 'https';
        $user = '';
        $password = '';
        $host = 'example.com';
        $path = '/foo/bar';
        $port = 400;
        $query = 'abc=123';
        $fragment = 'section3';
        $uri = new Uri($scheme, $host, $port, $path, $query, $fragment, $user, $password);

        $this->assertSame('example.com:400', $uri->getAuthority());
    }

    public function testGetUserInfoWithUsernameAndPassword()
    {
        $scheme = 'https';
        $user = 'josh';
        $password = 'sekrit';
        $host = 'example.com';
        $path = '/foo/bar';
        $port = 443;
        $query = 'abc=123';
        $fragment = 'section3';
        $uri = new Uri($scheme, $host, $port, $path, $query, $fragment, $user, $password);

        $this->assertSame('josh:sekrit', $uri->getUserInfo());
    }

    public function testGetUserInfoWithUsername()
    {
        $scheme = 'https';
        $user = 'josh';
        $password = '';
        $host = 'example.com';
        $path = '/foo/bar';
        $port = 443;
        $query = 'abc=123';
        $fragment = 'section3';
        $uri = new Uri($scheme, $host, $port, $path, $query, $fragment, $user, $password);

        $this->assertSame('josh', $uri->getUserInfo());
    }

    public function testGetUserInfoNone()
    {
        $scheme = 'https';
        $user = '';
        $password = '';
        $host = 'example.com';
        $path = '/foo/bar';
        $port = 443;
        $query = 'abc=123';
        $fragment = 'section3';
        $uri = new Uri($scheme, $host, $port, $path, $query, $fragment, $user, $password);

        $this->assertSame('', $uri->getUserInfo());
    }

    public function testGetUserInfoWithUsernameAndPasswordEncodesCorrectly()
    {
        $uri = Uri::createFromString('https://bob%40example.com:pass%3Aword@example.com:443/foo/bar?abc=123#section3');

        $this->assertSame('bob%40example.com:pass%3Aword', $uri->getUserInfo());
    }

    public function testWithUserInfo()
    {
        $uri = $this->uriFactory()->withUserInfo('bob', 'pass');

        $this->assertAttributeEquals('bob', 'user', $uri);
        $this->assertAttributeEquals('pass', 'password', $uri);
    }

    public function testWithUserInfoEncodesCorrectly()
    {
        $uri = $this->uriFactory()->withUserInfo('bob@example.com', 'pass:word');

        $this->assertAttributeEquals('bob%40example.com', 'user', $uri);
        $this->assertAttributeEquals('pass%3Aword', 'password', $uri);
    }

    public function testWithUserInfoRemovesPassword()
    {
        $uri = $this->uriFactory()->withUserInfo('bob');

        $this->assertAttributeEquals('bob', 'user', $uri);
        $this->assertAttributeEquals('', 'password', $uri);
    }

    public function testWithUserInfoRemovesInfo()
    {
        $uri = $this->uriFactory()->withUserInfo('bob', 'password');

        $uri = $uri->withUserInfo('');
        $this->assertAttributeEquals('', 'user', $uri);
        $this->assertAttributeEquals('', 'password', $uri);
    }


    public function testGetHost()
    {
        $this->assertSame('example.com', $this->uriFactory()->getHost());
    }

    public function testWithHost()
    {
        $uri = $this->uriFactory()->withHost('slimframework.com');

        $this->assertAttributeEquals('slimframework.com', 'host', $uri);
    }

    public function testGetPortWithSchemeAndNonDefaultPort()
    {
        $uri = new Uri('https', 'www.example.com', 4000);

        $this->assertSame(4000, $uri->getPort());
    }

    public function testGetPortWithSchemeAndDefaultPort()
    {
        $uriHttp = new Uri('http', 'www.example.com', 80);
        $uriHttps = new Uri('https', 'www.example.com', 443);

        $this->assertNull($uriHttp->getPort());
        $this->assertNull($uriHttps->getPort());
    }

    public function testGetPortWithoutSchemeAndPort()
    {
        $uri = new Uri('', 'www.example.com');

        $this->assertNull($uri->getPort());
    }

    public function testGetPortWithSchemeWithoutPort()
    {
        $uri = new Uri('http', 'www.example.com');

        $this->assertNull($uri->getPort());
    }

    public function testWithPort()
    {
        $uri = $this->uriFactory()->withPort(8000);

        $this->assertAttributeEquals(8000, 'port', $uri);
    }

    public function testWithPortNull()
    {
        $uri = $this->uriFactory()->withPort(null);

        $this->assertAttributeEquals(null, 'port', $uri);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithPortInvalidInt()
    {
        $this->uriFactory()->withPort(70000);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithPortInvalidString()
    {
        $this->uriFactory()->withPort('Foo');
    }

    public function testGetBasePathNone()
    {
        $this->assertSame('', $this->uriFactory()->getBasePath());
    }

    public function testWithBasePath()
    {
        $uri = $this->uriFactory()->withBasePath('/base');

        $this->assertAttributeEquals('/base', 'basePath', $uri);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Uri path must be a string
     */
    public function testWithBasePathInvalidType()
    {
        $this->uriFactory()->withBasePath(['foo']);
    }

    public function testWithBasePathAddsPrefix()
    {
        $uri = $this->uriFactory()->withBasePath('base');

        $this->assertAttributeEquals('/base', 'basePath', $uri);
    }

    public function testWithBasePathIgnoresSlash()
    {
        $uri = $this->uriFactory()->withBasePath('/');

        $this->assertAttributeEquals('', 'basePath', $uri);
    }

    public function testGetPath()
    {
        $this->assertSame('/foo/bar', $this->uriFactory()->getPath());
    }

    public function testWithPath()
    {
        $uri = $this->uriFactory()->withPath('/new');

        $this->assertAttributeEquals('/new', 'path', $uri);
    }

    public function testWithPathWithoutPrefix()
    {
        $uri = $this->uriFactory()->withPath('new');

        $this->assertAttributeEquals('new', 'path', $uri);
    }

    public function testWithPathEmptyValue()
    {
        $uri = $this->uriFactory()->withPath('');

        $this->assertAttributeEquals('', 'path', $uri);
    }

    public function testWithPathUrlEncodesInput()
    {
        $uri = $this->uriFactory()->withPath('/includes?/new');

        $this->assertAttributeEquals('/includes%3F/new', 'path', $uri);
    }

    public function testWithPathDoesNotDoubleEncodeInput()
    {
        $uri = $this->uriFactory()->withPath('/include%25s/new');

        $this->assertAttributeEquals('/include%25s/new', 'path', $uri);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Uri path must be a string
     */
    public function testWithPathInvalidType()
    {
        $this->uriFactory()->withPath(['foo']);
    }

    public function testGetQuery()
    {
        $this->assertSame('abc=123', $this->uriFactory()->getQuery());
    }

    public function testWithQuery()
    {
        $uri = $this->uriFactory()->withQuery('xyz=123');

        $this->assertAttributeEquals('xyz=123', 'query', $uri);
    }

    public function testWithQueryRemovesPrefix()
    {
        $uri = $this->uriFactory()->withQuery('?xyz=123');

        $this->assertAttributeEquals('xyz=123', 'query', $uri);
    }

    public function testWithQueryEmpty()
    {
        $uri = $this->uriFactory()->withQuery('');

        $this->assertAttributeEquals('', 'query', $uri);
    }

    public function testFilterQuery()
    {
        $uri = $this->uriFactory()->withQuery('?foobar=%match');

        $this->assertAttributeEquals('foobar=%25match', 'query', $uri);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Uri query must be a string
     */
    public function testWithQueryInvalidType()
    {
        $this->uriFactory()->withQuery(['foo']);
    }

    public function testGetFragment()
    {
        $this->assertSame('section3', $this->uriFactory()->getFragment());
    }

    public function testWithFragment()
    {
        $uri = $this->uriFactory()->withFragment('other-fragment');

        $this->assertAttributeEquals('other-fragment', 'fragment', $uri);
    }

    public function testWithFragmentRemovesPrefix()
    {
        $uri = $this->uriFactory()->withFragment('#other-fragment');

        $this->assertAttributeEquals('other-fragment', 'fragment', $uri);
    }

    public function testWithFragmentEmpty()
    {
        $uri = $this->uriFactory()->withFragment('');

        $this->assertAttributeEquals('', 'fragment', $uri);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Uri fragment must be a string
     */
    public function testWithFragmentInvalidType()
    {
        $this->uriFactory()->withFragment(['foo']);
    }

    public function testToString()
    {
        $uri = $this->uriFactory();

        $this->assertSame('https://josh:sekrit@example.com/foo/bar?abc=123#section3', (string) $uri);

        $uri = $uri->withPath('bar');
        $this->assertSame('https://josh:sekrit@example.com/bar?abc=123#section3', (string) $uri);

        $uri = $uri->withBasePath('foo/');
        $this->assertSame('https://josh:sekrit@example.com/foo/bar?abc=123#section3', (string) $uri);

        $uri = $uri->withPath('/bar');
        $this->assertSame('https://josh:sekrit@example.com/bar?abc=123#section3', (string) $uri);

        // ensure that a Uri with just a base path correctly converts to a string
        // (This occurs via createFromEnvironment when index.php is in a subdirectory)
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/foo/index.php',
            'REQUEST_URI' => '/foo/',
            'HTTP_HOST' => 'example.com',
        ]);
        $uri = Uri::createFromEnvironment($environment);
        $this->assertSame('http://example.com/foo/', (string) $uri);
    }

    public function testCreateFromString()
    {
        $uri = Uri::createFromString('https://example.com:8080/foo/bar?abc=123');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('8080', $uri->getPort());
        $this->assertSame('/foo/bar', $uri->getPath());
        $this->assertSame('abc=123', $uri->getQuery());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Uri must be a string
     */
    public function testCreateFromStringWithInvalidType()
    {
        Uri::createFromString(['https://example.com:8080/foo/bar?abc=123']);
    }

    public function testCreateEnvironment()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'PHP_AUTH_USER' => 'josh',
            'PHP_AUTH_PW' => 'sekrit',
            'QUERY_STRING' => 'abc=123',
            'HTTP_HOST' => 'example.com:8080',
            'SERVER_PORT' => 8080,
        ]);

        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('josh:sekrit', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('8080', $uri->getPort());
        $this->assertSame('/foo/bar', $uri->getPath());
        $this->assertSame('abc=123', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
    }

    public function testCreateFromEnvironmentSetsDefaultPortWhenHostHeaderDoesntHaveAPort()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'abc=123',
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
        ]);

        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('example.com', $uri->getHost());
        $this->assertEquals(null, $uri->getPort());
        $this->assertSame('/foo/bar', $uri->getPath());
        $this->assertSame('abc=123', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());

        $this->assertSame('https://example.com/foo/bar?abc=123', (string)$uri);
    }

    public function testCreateEnvironmentWithIPv6HostNoPort()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'PHP_AUTH_USER' => 'josh',
            'PHP_AUTH_PW' => 'sekrit',
            'QUERY_STRING' => 'abc=123',
            'HTTP_HOST' => '[2001:db8::1]',
            'REMOTE_ADDR' => '2001:db8::1',
        ]);

        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('josh:sekrit', $uri->getUserInfo());
        $this->assertSame('[2001:db8::1]', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertSame('/foo/bar', $uri->getPath());
        $this->assertSame('abc=123', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
    }

    public function testCreateEnvironmentWithIPv6HostWithPort()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'PHP_AUTH_USER' => 'josh',
            'PHP_AUTH_PW' => 'sekrit',
            'QUERY_STRING' => 'abc=123',
            'HTTP_HOST' => '[2001:db8::1]:8080',
            'REMOTE_ADDR' => '2001:db8::1',
        ]);

        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('josh:sekrit', $uri->getUserInfo());
        $this->assertSame('[2001:db8::1]', $uri->getHost());
        $this->assertSame('8080', $uri->getPort());
        $this->assertSame('/foo/bar', $uri->getPath());
        $this->assertSame('abc=123', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
    }

    #[\PHPUnit\Framework\Attributes\Group('one')]
    public function testCreateEnvironmentWithNoHostHeader()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'PHP_AUTH_USER' => 'josh',
            'PHP_AUTH_PW' => 'sekrit',
            'QUERY_STRING' => 'abc=123',
            'REMOTE_ADDR' => '2001:db8::1',
            'SERVER_NAME' => '[2001:db8::1]',
            'SERVER_PORT' => '8080',
        ]);
        $environment->remove('HTTP_HOST');

        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('josh:sekrit', $uri->getUserInfo());
        $this->assertSame('[2001:db8::1]', $uri->getHost());
        $this->assertSame('8080', $uri->getPort());
        $this->assertSame('/foo/bar', $uri->getPath());
        $this->assertSame('abc=123', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
    }

    public function testCreateEnvironmentWithBasePath()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/foo/index.php',
            'REQUEST_URI' => '/foo/bar',
        ]);
        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('/foo', $uri->getBasePath());
        $this->assertSame('bar', $uri->getPath());

        $this->assertSame('http://localhost/foo/bar', (string) $uri);
    }

    public function testGetBaseUrl()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/foo/index.php',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'abc=123',
            'HTTP_HOST' => 'example.com:80',
            'SERVER_PORT' => 80
        ]);
        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('http://example.com/foo', $uri->getBaseUrl());
    }

    public function testGetBaseUrlWithNoBasePath()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'abc=123',
            'HTTP_HOST' => 'example.com:80',
            'SERVER_PORT' => 80
        ]);
        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('http://example.com', $uri->getBaseUrl());
    }

    public function testGetBaseUrlWithAuthority()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/foo/index.php',
            'REQUEST_URI' => '/foo/bar',
            'PHP_AUTH_USER' => 'josh',
            'PHP_AUTH_PW' => 'sekrit',
            'QUERY_STRING' => 'abc=123',
            'HTTP_HOST' => 'example.com:8080',
            'SERVER_PORT' => 8080
        ]);
        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('http://josh:sekrit@example.com:8080/foo', $uri->getBaseUrl());
    }

    public function testWithPathWhenBaseRootIsEmpty()
    {
        $environment = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/bar',
        ]);
        $uri = Uri::createFromEnvironment($environment);

        $this->assertSame('http://localhost/test', (string) $uri->withPath('test'));
    }

    public function testRequestURIContainsIndexDotPhp()
    {
        $uri = Uri::createFromEnvironment(
            Environment::mock(
                [
                    'SCRIPT_NAME' => '/foo/index.php',
                    'REQUEST_URI' => '/foo/index.php/bar/baz',
                ]
            )
        );
        $this->assertSame('/foo/index.php', $uri->getBasePath());
    }

    public function testRequestURICanContainParams()
    {
        $uri = Uri::createFromEnvironment(
            Environment::mock(
                [
                    'REQUEST_URI' => '/foo?abc=123',
                ]
            )
        );
        $this->assertSame('abc=123', $uri->getQuery());
    }

    public function testUriDistinguishZeroFromEmptyString()
    {
        $expected = 'https://0:0@0:1/0?0#0';
        $this->assertSame($expected, (string) Uri::createFromString($expected));
    }

    public function testGetBaseUrlDistinguishZeroFromEmptyString()
    {
        $expected = 'https://0:0@0:1/0?0#0';
        $this->assertSame('https://0:0@0:1', (string) Uri::createFromString($expected)->getBaseUrl());
    }

    public function testConstructorWithEmptyPath()
    {
        $uri = new Uri('https', 'example.com', null, '');
        $this->assertSame('/', $uri->getPath());
    }

    public function testConstructorWithZeroAsPath()
    {
        $uri = new Uri('https', 'example.com', null, '0');
        $this->assertSame('0', (string) $uri->getPath());
    }
}
