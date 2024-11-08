<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests\Http;

use PHPUnit\Framework\TestCase;
use Slim\Http\NonBufferedBody;
use Slim\Http\Response;
use Slim\Tests\Assets\HeaderStack;

class NonBufferedBodyTest extends TestCase
{
    protected function setUp(): void
    {
        HeaderStack::reset();
    }

    protected function tearDown(): void
    {
        HeaderStack::reset();
    }

    public function testTheStreamContract(): void
    {
        $body = new NonBufferedBody();
        $body->close();
        $body->seek(0);
        $body->rewind();

        $this->assertSame('', (string) $body, 'Casting to string returns no data, since the class does not store any');
        $this->assertNull($body->detach(), 'Returns null since there is no such underlying stream');
        $this->assertNull($body->getSize(), 'Current size is undefined');
        $this->assertSame(0, $body->tell(), 'Pointer is considered to be at position 0 to conform');
        $this->assertTrue($body->eof(), 'Always considered to be at EOF');
        $this->assertFalse($body->isSeekable(), 'Cannot seek');
        $this->assertTrue($body->isWritable(), 'Body is writable');
        $this->assertFalse($body->isReadable(), 'Body is not readable');
        $this->assertSame('', $body->read(10), 'Data cannot be retrieved once written');
        $this->assertSame('', $body->getContents(), 'Data cannot be retrieved once written');
        $this->assertNull($body->getMetadata(), 'Metadata mechanism is not implemented');
    }

    public function testWrite()
    {
        $ob_initial_level = ob_get_level();

        // Start output buffering.
        ob_start();

        // Start output buffering again to test the while-loop in the `write()`
        // method that calls `ob_get_clean()` as long as the ob level is bigger
        // than 0.
        ob_start();
        echo 'buffer content: ';

        // Set the ob level shift that should be applied in the `ob_get_level()`
        // function override. That way, the `write()` method would only flush
        // the second ob, not the first one. We will add the initial ob level
        // because phpunit may have started ob too.
        $GLOBALS['ob_get_level_shift'] = -($ob_initial_level + 1);

        $body    = new NonBufferedBody();
        $length0 = $body->write('hello ');
        $length1 = $body->write('world');

        unset($GLOBALS['ob_get_level_shift']);
        $contents = ob_get_clean();

        $this->assertSame(strlen('buffer content: ') + strlen('hello '), $length0);
        $this->assertSame(strlen('world'), $length1);
        $this->assertSame('buffer content: hello world', $contents);
    }

    public function testWithHeader()
    {
        (new Response())
            ->withBody(new NonBufferedBody())
            ->withHeader('Foo', 'Bar');

        $this->assertSame([
            [
                'header' => 'Foo: Bar',
                'replace' => true,
                'status_code' => null
            ]
        ], HeaderStack::stack());
    }

    public function testWithAddedHeader()
    {
        (new Response())
            ->withBody(new NonBufferedBody())
            ->withHeader('Foo', 'Bar')
            ->withAddedHeader('Foo', 'Baz');

        $this->assertSame([
            [
                'header' => 'Foo: Bar',
                'replace' => true,
                'status_code' => null
            ],
            [
                'header' => 'Foo: Bar,Baz',
                'replace' => true,
                'status_code' => null
            ]
        ], HeaderStack::stack());
    }


    public function testWithoutHeader()
    {
        (new Response())
            ->withBody(new NonBufferedBody())
            ->withHeader('Foo', 'Bar')
            ->withoutHeader('Foo');

        $this->assertSame([], HeaderStack::stack());
    }
}
