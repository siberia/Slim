<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests\Http;

use PHPUnit\Framework\TestCase;
use Slim\Http\Environment;

class EnvironmentTest extends TestCase
{
    /**
     * Server settings for the default HTTP request
     * used by this script's tests.
     */
    public function setUp(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = '/var/www';
        $_SERVER['SCRIPT_NAME'] = '/foo/index.php';
        $_SERVER['REQUEST_URI'] = '/foo/index.php/bar/xyz';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SERVER_NAME'] = 'slim';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = 'one=1&two=2&three=3';
        $_SERVER['HTTPS'] = '';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    /**
     * Test environment from globals
     */
    public function testEnvironmentFromGlobals()
    {
        $env = new Environment($_SERVER);

        $this->assertEquals($_SERVER, $env->all());
    }

    /**
     * Test environment from mock data
     */
    public function testMock()
    {
        $env = Environment::mock([
            'SCRIPT_NAME' => '/foo/bar/index.php',
            'REQUEST_URI' => '/foo/bar?abc=123',
        ]);

        $this->assertInstanceOf(\Slim\Interfaces\CollectionInterface::class, $env);
        $this->assertSame('/foo/bar/index.php', $env->get('SCRIPT_NAME'));
        $this->assertSame('/foo/bar?abc=123', $env->get('REQUEST_URI'));
        $this->assertSame('localhost', $env->get('HTTP_HOST'));
    }

    /**
     * Test environment from mock data with HTTPS
     */
    public function testMockHttps()
    {
        $env = Environment::mock([
            'HTTPS' => 'on'
        ]);

        $this->assertInstanceOf(\Slim\Interfaces\CollectionInterface::class, $env);
        $this->assertSame('on', $env->get('HTTPS'));
        $this->assertSame(443, $env->get('SERVER_PORT'));
    }

    /**
     * Test environment from mock data with REQUEST_SCHEME
     */
    public function testMockRequestScheme()
    {
        $env = Environment::mock([
            'REQUEST_SCHEME' => 'https'
        ]);

        $this->assertInstanceOf(\Slim\Interfaces\CollectionInterface::class, $env);
        $this->assertSame('https', $env->get('REQUEST_SCHEME'));
        $this->assertSame(443, $env->get('SERVER_PORT'));
    }
}
