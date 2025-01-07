<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests;

use BadMethodCallException;
use Error;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use ReflectionMethod;
use RuntimeException;
use Slim\App;
use Slim\Container;
use Slim\Exception\MethodNotAllowedException;
use Slim\Exception\NotFoundException;
use Slim\Exception\SlimException;
use Slim\Handlers\Strategies\RequestResponseArgs;
use Slim\Http\Body;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\RequestBody;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use Slim\Http\Uri;
use Slim\Router;
use Slim\Tests\Assets\HeaderStack;
use Slim\Tests\Mocks\MockAction;

/**
 * Emit a header, without creating actual output artifacts
 *
 * @param string $value
 */
function header($value, $replace = true): void
{
    \Slim\header($value, $replace);
}

class AppTest extends TestCase
{
    public function setUp(): void
    {
        HeaderStack::reset();
    }

    public function tearDown(): void
    {
        HeaderStack::reset();
    }

    public static function setupBeforeClass(): void
    {
        // ini_set('log_errors', 0);
        ini_set('error_log', tempnam(sys_get_temp_dir(), 'slim'));
    }

    public static function tearDownAfterClass(): void
    {
        // ini_set('log_errors', 1);
    }

    public function testContainerInterfaceException(): void
    {
        $this->expectException('InvalidArgumentException');
        $app = new App('');
    }

    public function testIssetInContainer(): void
    {
        $app = new App();
        $router = $app->getContainer()->get('router');
        $this->assertTrue(isset($router));
    }

    public function testGetRoute(): void
    {
        $path = '/foo';
        $callable = function ($req, $res): void {
            // Do something
        };
        $app = new App();
        $route = $app->get($path, $callable);

        $this->assertInstanceOf(\Slim\Route::class, $route);
        $this->assertAttributeContains('GET', 'methods', $route);
    }

    public function testPostRoute(): void
    {
        $path = '/foo';
        $callable = function ($req, $res): void {
            // Do something
        };
        $app = new App();
        $route = $app->post($path, $callable);

        $this->assertInstanceOf(\Slim\Route::class, $route);
        $this->assertAttributeContains('POST', 'methods', $route);
    }

    public function testPutRoute(): void
    {
        $path = '/foo';
        $callable = function ($req, $res): void {
            // Do something
        };
        $app = new App();
        $route = $app->put($path, $callable);

        $this->assertInstanceOf(\Slim\Route::class, $route);
        $this->assertAttributeContains('PUT', 'methods', $route);
    }

    public function testPatchRoute(): void
    {
        $path = '/foo';
        $callable = function ($req, $res): void {
            // Do something
        };
        $app = new App();
        $route = $app->patch($path, $callable);

        $this->assertInstanceOf(\Slim\Route::class, $route);
        $this->assertAttributeContains('PATCH', 'methods', $route);
    }

    public function testDeleteRoute(): void
    {
        $path = '/foo';
        $callable = function ($req, $res): void {
            // Do something
        };
        $app = new App();
        $route = $app->delete($path, $callable);

        $this->assertInstanceOf(\Slim\Route::class, $route);
        $this->assertAttributeContains('DELETE', 'methods', $route);
    }

    public function testOptionsRoute(): void
    {
        $path = '/foo';
        $callable = function ($req, $res): void {
            // Do something
        };
        $app = new App();
        $route = $app->options($path, $callable);

        $this->assertInstanceOf(\Slim\Route::class, $route);
        $this->assertAttributeContains('OPTIONS', 'methods', $route);
    }

    public function testAnyRoute(): void
    {
        $path = '/foo';
        $callable = function ($req, $res): void {
            // Do something
        };
        $app = new App();
        $route = $app->any($path, $callable);

        $this->assertInstanceOf(\Slim\Route::class, $route);
        $this->assertAttributeContains('GET', 'methods', $route);
        $this->assertAttributeContains('POST', 'methods', $route);
        $this->assertAttributeContains('PUT', 'methods', $route);
        $this->assertAttributeContains('PATCH', 'methods', $route);
        $this->assertAttributeContains('DELETE', 'methods', $route);
        $this->assertAttributeContains('OPTIONS', 'methods', $route);
    }

    public function testMapRoute(): void
    {
        $path = '/foo';
        $callable = function ($req, $res): void {
            // Do something
        };
        $app = new App();
        $route = $app->map(['GET', 'POST'], $path, $callable);

        $this->assertInstanceOf(\Slim\Route::class, $route);
        $this->assertAttributeContains('GET', 'methods', $route);
        $this->assertAttributeContains('POST', 'methods', $route);
    }

    public function testRedirectRoute(): void
    {
        $source = '/foo';
        $destination = '/bar';

        $app = new App();
        $request = $this->getMockBuilder(\Psr\Http\Message\ServerRequestInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $route = $app->redirect($source, $destination, 301);

        $this->assertInstanceOf(\Slim\Route::class, $route);
        $this->assertAttributeContains('GET', 'methods', $route);

        $response = $route->run($request, new Response());
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame($destination, $response->getHeaderLine('Location'));

        $routeWithDefaultStatus = $app->redirect($source, $destination);
        $response = $routeWithDefaultStatus->run($request, new Response());
        $this->assertSame(302, $response->getStatusCode());

        $uri = $this->getMockBuilder(UriInterface::class)->getMock();
        $uri->expects($this->once())->method('__toString')->willReturn($destination);

        $routeToUri = $app->redirect($source, $uri);
        $response = $routeToUri->run($request, new Response());
        $this->assertSame($destination, $response->getHeaderLine('Location'));
    }

    public function testSegmentRouteThatDoesNotEndInASlash(): void
    {
        $app = new App();
        $app->get('/foo', function ($req, $res): void {
            // Do something
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo', 'pattern', $router->lookupRoute('route0'));
    }

    public function testSegmentRouteThatEndsInASlash(): void
    {
        $app = new App();
        $app->get('/foo/', function ($req, $res): void {
            // Do something
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testSegmentRouteThatDoesNotStartWithASlash(): void
    {
        $app = new App();
        $app->get('foo', function ($req, $res): void {
            // Do something
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('foo', 'pattern', $router->lookupRoute('route0'));
    }

    public function testSingleSlashRoute(): void
    {
        $app = new App();
        $app->get('/', function ($req, $res): void {
            // Do something
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyRoute(): void
    {
        $app = new App();
        $app->get('', function ($req, $res): void {
            // Do something
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSegmentWithSegmentRouteThatDoesNotEndInASlash(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->get('/bar', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSegmentWithSegmentRouteThatEndsInASlash(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->get('/bar/', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/bar/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSegmentWithSingleSlashRoute(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->get('/', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSegmentWithEmptyRoute(): void
    {
        $app = new App();
        $app->group('/foo', function (): void {
            $this->get('', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo', 'pattern', $router->lookupRoute('route0'));
    }

    public function testTwoGroupSegmentsWithSingleSlashRoute(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/baz/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testTwoGroupSegmentsWithAnEmptyRoute(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/baz', 'pattern', $router->lookupRoute('route0'));
    }

    public function testTwoGroupSegmentsWithSegmentRoute(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/baz/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testTwoGroupSegmentsWithSegmentRouteThatHasATrailingSlash(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/bar/', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/baz/bar/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSegmentWithSingleSlashNestedGroupAndSegmentRoute(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->group('/', function ($app): void {
                $app->get('/bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo//bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSegmentWithSingleSlashGroupAndSegmentRouteWithoutLeadingSlash(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->group('/', function ($app): void {
                $app->get('bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSegmentWithEmptyNestedGroupAndSegmentRoute(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->group('', function ($app): void {
                $app->get('/bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foo/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSegmentWithEmptyNestedGroupAndSegmentRouteWithoutLeadingSlash(): void
    {
        $app = new App();
        $app->group('/foo', function ($app): void {
            $app->group('', function ($app): void {
                $app->get('bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/foobar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithSegmentRouteThatDoesNotEndInASlash(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->get('/bar', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithSegmentRouteThatEndsInASlash(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->get('/bar/', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//bar/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithSingleSlashRoute(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->get('/', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithEmptyRoute(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->get('', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithNestedGroupSegmentWithSingleSlashRoute(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//baz/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithNestedGroupSegmentWithAnEmptyRoute(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//baz', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithNestedGroupSegmentWithSegmentRoute(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//baz/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithNestedGroupSegmentWithSegmentRouteThatHasATrailingSlash(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/bar/', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//baz/bar/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithSingleSlashNestedGroupAndSegmentRoute(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->group('/', function ($app): void {
                $app->get('/bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('///bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithSingleSlashGroupAndSegmentRouteWithoutLeadingSlash(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->group('/', function ($app): void {
                $app->get('bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithEmptyNestedGroupAndSegmentRoute(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->group('', function ($app): void {
                $app->get('/bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testGroupSingleSlashWithEmptyNestedGroupAndSegmentRouteWithoutLeadingSlash(): void
    {
        $app = new App();
        $app->group('/', function ($app): void {
            $app->group('', function ($app): void {
                $app->get('bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithSegmentRouteThatDoesNotEndInASlash(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->get('/bar', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithSegmentRouteThatEndsInASlash(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->get('/bar/', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/bar/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithSingleSlashRoute(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->get('/', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithEmptyRoute(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->get('', function ($req, $res): void {
                // Do something
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithNestedGroupSegmentWithSingleSlashRoute(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/baz/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithNestedGroupSegmentWithAnEmptyRoute(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/baz', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithNestedGroupSegmentWithSegmentRoute(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/baz/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithNestedGroupSegmentWithSegmentRouteThatHasATrailingSlash(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/bar/', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/baz/bar/', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithSingleSlashNestedGroupAndSegmentRoute(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->group('/', function ($app): void {
                $app->get('/bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('//bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithSingleSlashGroupAndSegmentRouteWithoutLeadingSlash(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->group('/', function ($app): void {
                $app->get('bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithEmptyNestedGroupAndSegmentRoute(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->group('', function ($app): void {
                $app->get('/bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('/bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testEmptyGroupWithEmptyNestedGroupAndSegmentRouteWithoutLeadingSlash(): void
    {
        $app = new App();
        $app->group('', function ($app): void {
            $app->group('', function ($app): void {
                $app->get('bar', function ($req, $res): void {
                    // Do something
                });
            });
        });
        /** @var Router $router */
        $router = $app->getContainer()->get('router');
        $this->assertAttributeEquals('bar', 'pattern', $router->lookupRoute('route0'));
    }

    public function testBottomMiddlewareIsApp(): void
    {
        $app = new App();
        $bottom = null;
        $mw = function ($req, $res, $next) use (&$bottom) {
            $bottom = $next;
            return $res;
        };
        $app->add($mw);

        $app->callMiddlewareStack(
            $this->getMockBuilder(\Psr\Http\Message\ServerRequestInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(\Psr\Http\Message\ResponseInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );

        $this->assertEquals($app, $bottom);
    }

    public function testAddMiddleware(): void
    {
        $app = new App();
        $called = 0;

        $mw = function ($req, $res, $next) use (&$called) {
            $called++;
            return $res;
        };
        $app->add($mw);

        $app->callMiddlewareStack(
            $this->getMockBuilder(\Psr\Http\Message\ServerRequestInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(\Psr\Http\Message\ResponseInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );

        $this->assertSame(1, $called);
    }

    public function testAddMiddlewareOnRoute(): void
    {
        $app = new App();

        $app->get('/', fn($req, $res) => $res->write('Center'))->add(function ($req, $res, $next) {
            $res->write('In1');
            $res = $next($req, $res);
            $res->write('Out1');

            return $res;
        })->add(function ($req, $res, $next) {
            $res->write('In2');
            $res = $next($req, $res);
            $res->write('Out2');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $app($req, $res);

        $this->assertSame('In2In1CenterOut1Out2', (string)$res->getBody());
    }

    public function testAddMiddlewareOnRouteGroup(): void
    {
        $app = new App();

        $app->group('/foo', function ($app): void {
            $app->get('/', fn($req, $res) => $res->write('Center'));
        })->add(function ($req, $res, $next) {
            $res->write('In1');
            $res = $next($req, $res);
            $res->write('Out1');

            return $res;
        })->add(function ($req, $res, $next) {
            $res->write('In2');
            $res = $next($req, $res);
            $res->write('Out2');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $app($req, $res);

        $this->assertSame('In2In1CenterOut1Out2', (string)$res->getBody());
    }

    public function testAddMiddlewareOnTwoRouteGroup(): void
    {
        $app = new App();

        $app->group('/foo', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/', fn($req, $res) => $res->write('Center'));
            })->add(function ($req, $res, $next) {
                $res->write('In2');
                $res = $next($req, $res);
                $res->write('Out2');

                return $res;
            });
        })->add(function ($req, $res, $next) {
            $res->write('In1');
            $res = $next($req, $res);
            $res->write('Out1');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/baz/',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $app($req, $res);

        $this->assertSame('In1In2CenterOut2Out1', (string)$res->getBody());
    }

    public function testAddMiddlewareOnRouteAndOnTwoRouteGroup(): void
    {
        $app = new App();

        $app->group('/foo', function ($app): void {
            $app->group('/baz', function ($app): void {
                $app->get('/', fn($req, $res) => $res->write('Center'))->add(function ($req, $res, $next) {
                    $res->write('In3');
                    $res = $next($req, $res);
                    $res->write('Out3');

                    return $res;
                });
            })->add(function ($req, $res, $next) {
                $res->write('In2');
                $res = $next($req, $res);
                $res->write('Out2');

                return $res;
            });
        })->add(function ($req, $res, $next) {
            $res->write('In1');
            $res = $next($req, $res);
            $res->write('Out1');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/baz/',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $app($req, $res);

        $this->assertSame('In1In2In3CenterOut3Out2Out1', (string)$res->getBody());
    }

    public function testInvokeReturnMethodNotAllowed(): void
    {
        $app = new App();
        $app->get('/foo', function ($req, $res) {
            $res->write('Hello');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'POST',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('POST', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame(405, (string)$resOut->getStatusCode());
        $this->assertSame(['GET'], $resOut->getHeader('Allow'));
        $this->assertContains(
            '<p>Method not allowed. Must be one of: <strong>GET</strong></p>',
            (string)$resOut->getBody()
        );

        // now test that exception is raised if the handler isn't registered
        unset($app->getContainer()['notAllowedHandler']);
        $this->setExpectedException(\Slim\Exception\MethodNotAllowedException::class);
        $app($req, $res);
    }

    public function testInvokeWithMatchingRoute(): void
    {
        $app = new App();
        $app->get('/foo', function ($req, $res) {
            $res->write('Hello');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('Hello', (string)$res->getBody());
    }

    public function testInvokeWithMatchingRouteWithSetArgument(): void
    {
        $app = new App();
        $app->get('/foo/bar', fn($req, $res, $args) => $res->write("Hello {$args['attribute']}"))
            ->setArgument('attribute', 'world!');

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('Hello world!', (string)$res->getBody());
    }

    public function testInvokeWithMatchingRouteWithSetArguments(): void
    {
        $app = new App();
        $app->get('/foo/bar', fn($req, $res, $args) => $res->write("Hello {$args['attribute1']} {$args['attribute2']}"))
            ->setArguments(['attribute1' => 'there', 'attribute2' => 'world!']);

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('Hello there world!', (string)$res->getBody());
    }

    public function testInvokeWithMatchingRouteWithNamedParameter(): void
    {
        $app = new App();
        $app->get('/foo/{name}', fn($req, $res, $args) => $res->write("Hello {$args['name']}"));

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/test!',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('Hello test!', (string)$res->getBody());
    }

    public function testInvokeWithMatchingRouteWithNamedParameterRequestResponseArgStrategy(): void
    {
        $c = new Container();
        $c['foundHandler'] = fn($c) => new RequestResponseArgs();

        $app = new App($c);
        $app->get('/foo/{name}', fn($req, $res, $name) => $res->write("Hello {$name}"));

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/test!',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('Hello test!', (string)$res->getBody());
    }

    public function testInvokeWithMatchingRouteWithNamedParameterOverwritesSetArgument(): void
    {
        $app = new App();
        $app->get('/foo/{name}', fn($req, $res, $args) => $res->write("Hello {$args['extra']} {$args['name']}"))
            ->setArguments(['extra' => 'there', 'name' => 'world!']);

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/test!',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('Hello there test!', (string)$res->getBody());
    }

    public function testInvokeWithoutMatchingRoute(): void
    {
        $app = new App();
        $app->get('/bar', function ($req, $res) {
            $res->write('Hello');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertAttributeEquals(404, 'status', $resOut);

        // now test that exception is raised if the handler isn't registered
        unset($app->getContainer()['notFoundHandler']);
        $this->setExpectedException(\Slim\Exception\NotFoundException::class);
        $app($req, $res);
    }

    public function testInvokeWithPimpleCallable(): void
    {
        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        $mock = $this->getMockBuilder('StdClass')->getMock();

        $app = new App();
        $container = $app->getContainer();
        $container['foo'] = function () use ($mock, $res) {
            $mock->method('bar')
                ->willReturn(
                    $res->write('Hello')
                );
            return $mock;
        };

        $app->get('/foo', 'foo:bar');

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('Hello', (string)$res->getBody());
    }

    public function testInvokeWithPimpleUndefinedCallable(): void
    {
        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        $mock = $this->getMockBuilder('StdClass')->getMock();

        $app = new App();
        $container = $app->getContainer();
        $container['foo'] = fn() => $mock;

        $app->get('/foo', 'foo:bar');

        $this->setExpectedException('\RuntimeException');

        // Invoke app
        $app($req, $res);
    }

    public function testInvokeWithPimpleCallableViaMagicMethod(): void
    {
        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        $mock = new MockAction();

        $app = new App();
        $container = $app->getContainer();
        $container['foo'] = fn() => $mock;

        $app->get('/foo', 'foo:bar');

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertEquals(json_encode(['name'=>'bar', 'arguments' => []]), (string)$res->getBody());
    }

    public function testInvokeFunctionName(): void
    {
        $app = new App();

        // @codingStandardsIgnoreStart
        function handle($req, $res)
        {
            $res->write('foo');

            return $res;
        }
        // @codingStandardsIgnoreEnd

        $app->get('/foo', __NAMESPACE__ . '\handle');

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $app($req, $res);

        $this->assertSame('foo', (string)$res->getBody());
    }

    public function testCurrentRequestAttributesAreNotLostWhenAddingRouteArguments(): void
    {
        $app = new App();
        $app->get('/foo/{name}', fn($req, $res, $args) => $res->write($req->getAttribute('one') . $args['name']));

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/rob',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $req = $req->withAttribute("one", 1);
        $res = new Response();


        // Invoke app
        $resOut = $app($req, $res);
        $this->assertSame('1rob', (string)$resOut->getBody());
    }

    public function testCurrentRequestAttributesAreNotLostWhenAddingRouteArgumentsRequestResponseArg(): void
    {
        $c = new Container();
        $c['foundHandler'] = fn() => new RequestResponseArgs();

        $app = new App($c);
        $app->get('/foo/{name}', fn($req, $res, $name) => $res->write($req->getAttribute('one') . $name));

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/rob',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $req = $req->withAttribute("one", 1);
        $res = new Response();


        // Invoke app
        $resOut = $app($req, $res);
        $this->assertSame('1rob', (string)$resOut->getBody());
    }

    public function testInvokeSubRequest(): void
    {
        $app = new App();
        $app->get('/foo', function ($req, $res) {
            $res->write('foo');

            return $res;
        });

        $newResponse = $subReq = $app->subRequest('GET', '/foo');

        $this->assertSame('foo', (string)$subReq->getBody());
        $this->assertSame(200, $newResponse->getStatusCode());
    }

    public function testInvokeSubRequestWithQuery(): void
    {
        $app = new App();
        $app->get('/foo', function ($req, $res) {
            $res->write("foo {$req->getParam('bar')}");

            return $res;
        });

        $subReq = $app->subRequest('GET', '/foo', 'bar=bar');

        $this->assertSame('foo bar', (string)$subReq->getBody());
    }

    public function testInvokeSubRequestUsesResponseObject(): void
    {
        $app = new App();
        $app->get('/foo', function ($req, $res) {
            $res->write("foo {$req->getParam('bar')}");

            return $res;
        });

        $resp = new Response(201);
        $newResponse = $subReq = $app->subRequest('GET', '/foo', 'bar=bar', [], [], '', $resp);

        $this->assertSame('foo bar', (string)$subReq->getBody());
        $this->assertSame(201, $newResponse->getStatusCode());
    }

    // TODO: Test finalize()

    // TODO: Test run()
    public function testRun(): void
    {
        $app = $this->getAppForTestingRunMethod();

        ob_start();
        $app->run();
        $resOut = ob_get_clean();

        $this->assertSame('bar', (string)$resOut);
    }

    public function testRunReturnsEmptyResponseBodyWithHeadRequestMethod(): void
    {
        $app = $this->getAppForTestingRunMethod('HEAD');

        ob_start();
        $app->run();
        $resOut = ob_get_clean();

        $this->assertSame('', (string)$resOut);
    }

    public function testRunReturnsEmptyResponseBodyWithGetRequestMethodInSilentMode(): void
    {
        $app = $this->getAppForTestingRunMethod();
        $response = $app->run(true);

        $this->assertSame('bar', $response->getBody()->__toString());
    }

    public function testRunReturnsEmptyResponseBodyWithHeadRequestMethodInSilentMode(): void
    {
        $app = $this->getAppForTestingRunMethod('HEAD');
        $response = $app->run(true);

        $this->assertSame('', $response->getBody()->__toString());
    }

    private function getAppForTestingRunMethod($method = 'GET')
    {
        $app = new App();

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => $method,
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $req = new Request($method, $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response(StatusCode::HTTP_OK, null, $body);
        $app->getContainer()['request'] = $req;
        $app->getContainer()['response'] = $res;

        $app->get('/foo', function ($req, $res) {
            $res->getBody()->write('bar');

            return $res;
        });

        return $app;
    }

    public function testRespond(): void
    {
        $app = new App();
        $app->get('/foo', function ($req, $res) {
            $res->write('Hello');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $app->respond($resOut);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->expectOutputString('Hello');
    }

    public function testRespondWithHeaderNotSent(): void
    {
        $app = new App();
        $app->get('/foo', function ($req, $res) {
            $res->write('Hello');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $app->respond($resOut);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->expectOutputString('Hello');
    }

    public function testRespondNoContent(): void
    {
        $app = new App();
        $app->get('/foo', function ($req, $res) {
            $res = $res->withStatus(204);
            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);

        $app->respond($resOut);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame([], $resOut->getHeader('Content-Type'));
        $this->assertSame([], $resOut->getHeader('Content-Length'));
        $this->expectOutputString('');
    }

    public function testRespondWithPaddedStreamFilterOutput(): void
    {
        $availableFilter = stream_get_filters();

        if (version_compare(phpversion(), '7.0.0', '>=')) {
            $filterName           = 'string.rot13';
            $unfilterName         = 'string.rot13';
            $specificFilterName   = 'string.rot13';
            $specificUnfilterName = 'string.rot13';
        } else {
            $filterName           = 'mcrypt.*';
            $unfilterName         = 'mdecrypt.*';
            $specificFilterName   = 'mcrypt.rijndael-128';
            $specificUnfilterName = 'mdecrypt.rijndael-128';
        }

        if (in_array($filterName, $availableFilter) && in_array($unfilterName, $availableFilter)) {
            $app = new App();
            $app->get('/foo', function ($req, $res) use ($specificFilterName, $specificUnfilterName) {
                $key = base64_decode('xxxxxxxxxxxxxxxx');
                $iv = base64_decode('Z6wNDk9LogWI4HYlRu0mng==');

                $data = 'Hello';
                $length = strlen($data);

                $stream = fopen('php://temp', 'r+');

                $filter = stream_filter_append($stream, $specificFilterName, STREAM_FILTER_WRITE, [
                    'key' => $key,
                    'iv' => $iv
                ]);

                fwrite($stream, $data);
                rewind($stream);
                stream_filter_remove($filter);

                stream_filter_append($stream, $specificUnfilterName, STREAM_FILTER_READ, [
                    'key' => $key,
                    'iv' => $iv
                ]);

                return $res->withHeader('Content-Length', $length)->withBody(new Body($stream));
            });

            // Prepare request and response objects
            $env = Environment::mock([
                'SCRIPT_NAME' => '/index.php',
                'REQUEST_URI' => '/foo',
                'REQUEST_METHOD' => 'GET',
            ]);
            $uri = Uri::createFromEnvironment($env);
            $headers = Headers::createFromEnvironment($env);
            $cookies = [];
            $serverParams = $env->all();
            $body = new RequestBody();
            $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
            $res = new Response();

            // Invoke app
            $resOut = $app($req, $res);
            $app->respond($resOut);

            $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
            $this->expectOutputString('Hello');
        } else {
            $this->assertTrue(true);
        }
    }

    public function testRespondIndeterminateLength(): void
    {
        $app = new App();
        $body_stream = fopen('php://temp', 'r+');
        $response = new Response();
        $body = $this->getMockBuilder(\Slim\Http\Body::class)
            ->setConstructorArgs([$body_stream])
            ->getMock();
        fwrite($body_stream, "Hello");
        rewind($body_stream);
        $body->method("getSize")->willReturn(null);
        $response = $response->withBody($body);
        $app->respond($response);
        $this->expectOutputString("Hello");
    }

    public function testResponseWithStreamReadYieldingLessBytesThanAsked(): void
    {
        $app = new App([
            'settings' => ['responseChunkSize' => Mocks\SmallChunksStream::CHUNK_SIZE * 2]
        ]);
        $app->get('/foo', function ($req, $res) {
            $res->write('Hello');

            return $res;
        });

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new Mocks\SmallChunksStream();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = (new Response())->withBody($body);

        // Invoke app
        $resOut = $app($req, $res);

        $app->respond($resOut);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->expectOutputString(str_repeat('.', Mocks\SmallChunksStream::SIZE));
    }

    public function testResponseReplacesPreviouslySetHeaders(): void
    {
        $app = new App();
        $app->get('/foo', fn($req, $res) => $res
            ->withHeader('X-Foo', 'baz1')
            ->withAddedHeader('X-Foo', 'baz2'));

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);
        $app->respond($resOut);

        $expectedStack = [
            ['header' => 'X-Foo: baz1', 'replace' => true, 'status_code' => null],
            ['header' => 'X-Foo: baz2', 'replace' => false, 'status_code' => null],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];

        $this->assertSame($expectedStack, HeaderStack::stack());
    }

    public function testResponseDoesNotReplacePreviouslySetSetCookieHeaders(): void
    {
        $app = new App();
        $app->get('/foo', fn($req, $res) => $res
            ->withHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz'));

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke app
        $resOut = $app($req, $res);
        $app->respond($resOut);

        $expectedStack = [
            ['header' => 'Set-Cookie: foo=bar', 'replace' => false, 'status_code' => null],
            ['header' => 'Set-Cookie: bar=baz', 'replace' => false, 'status_code' => null],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];

        $this->assertSame($expectedStack, HeaderStack::stack());
    }

    public function testExceptionErrorHandlerDoesNotDisplayErrorDetails(): void
    {
        $app = new App();

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();
        $app->getContainer()['request'] = $req;
        $app->getContainer()['response'] = $res;

        $mw = function ($req, $res, $next): void {
            throw new Exception('middleware exception');
        };

        $app->add($mw);

        $app->get('/foo', fn($req, $res) => $res);

        $resOut = $app->run(true);

        $this->assertSame(500, $resOut->getStatusCode());
        $this->assertDoesNotMatchRegularExpression('/.*middleware exception.*/', (string)$resOut);
    }

    /**
     * @requires PHP 7.0
     */
    public function testExceptionPhpErrorHandlerDoesNotDisplayErrorDetails(): void
    {
        $app = new App();

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();
        $app->getContainer()['request'] = $req;
        $app->getContainer()['response'] = $res;

        $mw = function ($req, $res, $next): void {
            dumpFonction();
        };

        $app->add($mw);

        $app->get('/foo', fn($req, $res) => $res);

        $resOut = $app->run(true);

        $this->assertSame(500, $resOut->getStatusCode());
        $this->assertDoesNotMatchRegularExpression('/.*middleware exception.*/', (string)$resOut);
    }

    /**
     * @return App
     */
    public function appFactory(): App
    {
        $app = new App();

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();
        $app->getContainer()['request'] = $req;
        $app->getContainer()['response'] = $res;

        return $app;
    }

    /**
     * @expectedException Exception
     */
    public function testRunExceptionNoHandler(): void
    {
        $this->expectException(Exception::class);

        $app = $this->appFactory();

        $container = $app->getContainer();
        unset($container['errorHandler']);

        $app->get('/foo', fn($req, $res, $args) => $res);
        $app->add(function ($req, $res, $args): void {
            throw new Exception();
        });
        $res = $app->run(true);
    }

    public function testRunSlimException(): void
    {
        $app = $this->appFactory();
        $app->get('/foo', fn($req, $res, $args) => $res);
        $app->add(function ($req, $res, $args): void {
            $res->write("Failed");
            throw new SlimException($req, $res);
        });
        $res = $app->run(true);

        $res->getBody()->rewind();
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame("Failed", $res->getBody()->getContents());
    }

    /**
     * @requires PHP 7.0
     */
    public function testRunThrowable(): void
    {
        $app = $this->appFactory();
        $app->get('/foo', fn($req, $res, $args) => $res);
        $app->add(function ($req, $res, $args): void {
            throw new Error('Failed');
        });

        $res = $app->run(true);

        $res->getBody()->rewind();

        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame('text/html', $res->getHeaderLine('Content-Type'));
        $this->assertSame(0, strpos((string)$res->getBody(), '<html>'));
    }

    public function testRunNotFound(): void
    {
        $app = $this->appFactory();
        $app->get('/foo', fn($req, $res, $args) => $res);
        $app->add(function ($req, $res, $args): void {
            throw new NotFoundException($req, $res);
        });
        $res = $app->run(true);

        $this->assertSame(404, $res->getStatusCode());
    }

    /**
     * @expectedException \Slim\Exception\NotFoundException
     */
    public function testRunNotFoundWithoutHandler(): void
    {
        $app = $this->appFactory();
        $container = $app->getContainer();
        unset($container['notFoundHandler']);

        $app->get('/foo', fn($req, $res, $args) => $res);
        $app->add(function ($req, $res, $args): void {
            throw new NotFoundException($req, $res);
        });
        $res = $app->run(true);
    }


    public function testRunNotAllowed(): void
    {
        $app = $this->appFactory();
        $app->get('/foo', fn($req, $res, $args) => $res);
        $app->add(function ($req, $res, $args): void {
            throw new MethodNotAllowedException($req, $res, ['POST']);
        });
        $res = $app->run(true);

        $this->assertSame(405, $res->getStatusCode());
    }

    /**
     * @expectedException \Slim\Exception\MethodNotAllowedException
     */
    public function testRunNotAllowedWithoutHandler(): void
    {
        $app = $this->appFactory();
        $container = $app->getContainer();
        unset($container['notAllowedHandler']);

        $app->get('/foo', fn($req, $res, $args) => $res);
        $app->add(function ($req, $res, $args): void {
            throw new MethodNotAllowedException($req, $res, ['POST']);
        });
        $res = $app->run(true);
    }

    public function testAppRunWithDetermineRouteBeforeAppMiddleware(): void
    {
        $app = $this->appFactory();

        $app->get('/foo', fn($req, $res) => $res->write("Test"));

        $app->getContainer()['settings']['determineRouteBeforeAppMiddleware'] = true;

        $resOut = $app->run(true);
        $resOut->getBody()->rewind();
        $this->assertSame("Test", $resOut->getBody()->getContents());
    }

    public function testExceptionErrorHandlerDisplaysErrorDetails(): void
    {
        $app = new App([
            'settings' => [
                'displayErrorDetails' => true
            ],
        ]);

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new Body(fopen('php://temp', 'r+'));
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();
        $app->getContainer()['request'] = $req;
        $app->getContainer()['response'] = $res;

        $mw = function ($req, $res, $next): void {
            throw new RuntimeException('middleware exception');
        };

        $app->add($mw);

        $app->get('/foo', fn($req, $res) => $res);

        $resOut = $app->run(true);

        $this->assertSame(500, $resOut->getStatusCode());
        $this->assertMatchesRegularExpression('/.*middleware exception.*/', (string)$resOut);
    }

    public function testFinalize(): void
    {
        $method = new ReflectionMethod(\Slim\App::class, 'finalize');
        $method->setAccessible(true);

        $response = new Response();
        $response->getBody()->write('foo');

        $response = $method->invoke(new App(), $response);

        $this->assertTrue($response->hasHeader('Content-Length'));
        $this->assertSame('3', $response->getHeaderLine('Content-Length'));
    }

    public function testFinalizeWithoutBody(): void
    {
        $method = new ReflectionMethod(\Slim\App::class, 'finalize');
        $method->setAccessible(true);

        $response = $method->invoke(new App(), new Response(304));

        $this->assertFalse($response->hasHeader('Content-Length'));
        $this->assertFalse($response->hasHeader('Content-Type'));
    }

    public function testCallingAContainerCallable(): void
    {
        $settings = [
            'foo' => fn($c) => fn($a) => $a
        ];
        $app = new App($settings);

        $result = $app->foo('bar');
        $this->assertSame('bar', $result);

        $headers = new Headers();
        $body = new Body(fopen('php://temp', 'r+'));
        $request = new Request('GET', Uri::createFromString(''), $headers, [], [], $body);
        $response = new Response();

        $response = $app->notFoundHandler($request, $response);

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testCallingFromContainerNotCallable(): void
    {
        $this->expectException(BadMethodCallException::class);

        $settings = [
            'foo' => fn($c) => null
        ];
        $app = new App($settings);
        $app->foo('bar');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testCallingAnUnknownContainerCallableThrows(): void
    {
        $this->expectException(BadMethodCallException::class);

        $app = new App();
        $app->foo('bar');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testCallingAnUncallableContainerKeyThrows(): void
    {
        $this->expectException(BadMethodCallException::class);

        $app = new App();
        $app->getContainer()['bar'] = 'foo';
        $app->foo('bar');
    }

    public function testOmittingContentLength(): void
    {
        $method = new ReflectionMethod(\Slim\App::class, 'finalize');
        $method->setAccessible(true);

        $response = new Response();
        $response->getBody()->write('foo');

        $app = new App();
        $container = $app->getContainer();
        $container['settings']['addContentLengthHeader'] = false;
        $response = $method->invoke($app, $response);

        $this->assertFalse($response->hasHeader('Content-Length'));
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Unexpected data in output buffer
     */
    public function testForUnexpectedDataInOutputBuffer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected data in output buffer.');

        $this->expectOutputString('test'); // needed to avoid risky test warning
        echo "test";
        $method = new ReflectionMethod(\Slim\App::class, 'finalize');
        $method->setAccessible(true);

        $response = new Response();
        $response->getBody()->write('foo');

        $app = new App();
        $container = $app->getContainer();
        $container['settings']['addContentLengthHeader'] = true;
        $response = $method->invoke($app, $response);
    }

    public function testUnsupportedMethodWithoutRoute(): void
    {
        $app = new App();
        $c = $app->getContainer();
        $c['environment'] = Environment::mock(['REQUEST_URI' => '/', 'REQUEST_METHOD' => 'BADMTHD']);

        $resOut = $app->run(true);

        $this->assertInstanceOf(ResponseInterface::class, $resOut);
        $this->assertSame(404, $resOut->getStatusCode());
    }

    public function testUnsupportedMethodWithRoute(): void
    {
        $app = new App();
        $app->get('/', function (): void {
            // stubbed action to give us a route at /
        });
        $c = $app->getContainer();
        $c['environment'] = Environment::mock(['REQUEST_URI' => '/', 'REQUEST_METHOD' => 'BADMTHD']);

        $resOut = $app->run(true);

        $this->assertInstanceOf(ResponseInterface::class, $resOut);
        $this->assertSame(405, $resOut->getStatusCode());
    }

    public function testContainerSetToRoute(): void
    {
        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        $mock = new MockAction();

        $app = new App();
        $container = $app->getContainer();
        $container['foo'] = fn() => $mock;

        /** @var $router Router */
        $router = $container['router'];

        $router->map(['get'], '/foo', 'foo:bar');

        // Invoke app
        $resOut = $app($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertEquals(json_encode(['name'=>'bar', 'arguments' => []]), (string)$res->getBody());
    }

    public function testIsEmptyResponseWithEmptyMethod(): void
    {
        $method = new ReflectionMethod(\Slim\App::class, 'isEmptyResponse');
        $method->setAccessible(true);

        $response = new Response();
        $response = $response->withStatus(204);

        $result = $method->invoke(new App(), $response);
        $this->assertTrue($result);
    }

    public function testIsEmptyResponseWithoutEmptyMethod(): void
    {
        $method = new ReflectionMethod(\Slim\App::class, 'isEmptyResponse');
        $method->setAccessible(true);

        /** @var Response $response */
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $response->method('getStatusCode')
            ->willReturn(204);

        $result = $method->invoke(new App(), $response);
        $this->assertTrue($result);
    }

    public function testIsHeadRequestWithGetRequest(): void
    {
        $method = new ReflectionMethod(\Slim\App::class, 'isHeadRequest');
        $method->setAccessible(true);

        /** @var Request $request */
        $request = $this->getMockBuilder(RequestInterface::class)->getMock();
        $request->method('getMethod')
            ->willReturn('get');

        $result = $method->invoke(new App(), $request);
        $this->assertFalse($result);
    }

    public function testIsHeadRequestWithHeadRequest(): void
    {
        $method = new ReflectionMethod(\Slim\App::class, 'isHeadRequest');
        $method->setAccessible(true);

        /** @var Request $request */
        $request = $this->getMockBuilder(RequestInterface::class)->getMock();
        $request->method('getMethod')
            ->willReturn('head');

        $result = $method->invoke(new App(), $request);
        $this->assertTrue($result);
    }

    public function testHandlePhpError(): void
    {
        $this->skipIfPhp70();
        $method = new ReflectionMethod(\Slim\App::class, 'handlePhpError');
        $method->setAccessible(true);

        $throwable = $this->getMock(
            '\Throwable',
            ['getCode', 'getMessage', 'getFile', 'getLine', 'getTraceAsString', 'getPrevious']
        );
        $req = $this->getMockBuilder(\Slim\Http\Request::class)->disableOriginalConstructor()->getMock();
        $res = new Response();

        $res = $method->invoke(new App(), $throwable, $req, $res);

        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame('text/html', $res->getHeaderLine('Content-Type'));
        $this->assertSame(0, strpos((string)$res->getBody(), '<html>'));
    }

    public function testExceptionOutputBufferingOff(): void
    {
        $app = $this->appFactory();
        $app->getContainer()['settings']['outputBuffering'] = false;

        $app->get("/foo", function ($request, $response, $args): void {
            $test = [1,2,3];
            var_dump($test);
            throw new Exception("oops");
        });

        $unExpectedOutput = <<<end
array(3) {
  [0] =>
  int(1)
  [1] =>
  int(2)
  [2] =>
  int(3)
}
end;

        $resOut = $app->run(true);
        $output = (string)$resOut->getBody();
        $strPos = strpos($output, $unExpectedOutput);
        $this->assertStringNotContainsString($strPos);
    }

    public function testExceptionOutputBufferingAppend(): void
    {
        // If we are testing in HHVM skip this test due to a bug in HHVM
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('https://github.com/facebook/hhvm/issues/7803');
        }

        $app = $this->appFactory();
        $app->getContainer()['settings']['outputBuffering'] = 'append';
        $app->get("/foo", function ($request, $response, $args): void {
            echo 'output buffer test';
            throw new Exception("oops");
        });

        $resOut = $app->run(true);
        $output = (string)$resOut->getBody();
        $this->assertStringEndsWith('output buffer test', $output);
    }

    public function testExceptionOutputBufferingPrepend(): void
    {
        // If we are testing in HHVM skip this test due to a bug in HHVM
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('https://github.com/facebook/hhvm/issues/7803');
        }

        $app = $this->appFactory();
        $app->getContainer()['settings']['outputBuffering'] = 'prepend';
        $app->get("/foo", function ($request, $response, $args): void {
            echo 'output buffer test';
            throw new Exception("oops");
        });

        $resOut = $app->run(true);
        $output = (string)$resOut->getBody();
        $this->assertStringStartsWith('output buffer test', $output);
    }

    public function testInvokeSequentialProccessToAPathWithOptionalArgsAndWithoutOptionalArgs(): void
    {
        $app = new App();
        $app->get('/foo[/{bar}]', fn($req, $res, $args) => $res->write(count($args)));

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke process with optional arg
        $resOut = $app->process($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('1', (string)$resOut->getBody());

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke process without optional arg
        $resOut2 = $app->process($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut2);
        $this->assertSame('0', (string)$resOut2->getBody());
    }

    public function testInvokeSequentialProccessToAPathWithOptionalArgsAndWithoutOptionalArgsAndKeepSetedArgs(): void
    {
        $app = new App();
        $app->get('/foo[/{bar}]', fn($req, $res, $args) => $res->write(count($args)))->setArgument('baz', 'quux');

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke process without optional arg
        $resOut = $app->process($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('2', (string)$resOut->getBody());

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke process with optional arg
        $resOut2 = $app->process($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut2);
        $this->assertSame('1', (string)$resOut2->getBody());
    }

    public function testInvokeSequentialProccessAfterAddingAnotherRouteArgument(): void
    {
        $app = new App();
        $route = $app->get('/foo[/{bar}]', fn($req, $res, $args) => $res->write(count($args)))
            ->setArgument('baz', 'quux');

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // Invoke process with optional arg
        $resOut = $app->process($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut);
        $this->assertSame('2', (string)$resOut->getBody());

        // Prepare request and response objects
        $env = Environment::mock([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/foo/bar',
            'REQUEST_METHOD' => 'GET',
        ]);
        $uri = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);
        $cookies = [];
        $serverParams = $env->all();
        $body = new RequestBody();
        $req = new Request('GET', $uri, $headers, $cookies, $serverParams, $body);
        $res = new Response();

        // add another argument
        $route->setArgument('one', '1');

        // Invoke process with optional arg
        $resOut2 = $app->process($req, $res);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $resOut2);
        $this->assertSame('3', (string)$resOut2->getBody());
    }

    protected function skipIfPhp70(): void
    {
        if (version_compare(PHP_VERSION, '7.0', '>=')) {
            $this->markTestSkipped();
        }
    }
}
