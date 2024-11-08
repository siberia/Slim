<?php declare(strict_types=1);
/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Slim\Collection;

class CollectionTest extends TestCase
{
    /**
     * @var Collection
     */
    protected $bag;

    /**
     * @var ReflectionProperty
     */
    protected $property;

    public function setUp(): void
    {
        $this->bag = new Collection();
        $this->property = new ReflectionProperty($this->bag, 'data');
        $this->property->setAccessible(true);
    }

    public function testInitializeWithData(): void
    {
        $bag = new Collection(['foo' => 'bar']);
        $bagProperty = new ReflectionProperty($bag, 'data');
        $bagProperty->setAccessible(true);

        $this->assertSame(['foo' => 'bar'], $bagProperty->getValue($bag));
    }

    public function testSet()
    {
        $this->bag->set('foo', 'bar');
        $this->assertArrayHasKey('foo', $this->property->getValue($this->bag));
        $bag =  $this->property->getValue($this->bag);
        $this->assertSame('bar', $bag['foo']);
    }

    public function testOffsetSet()
    {
        $this->bag['foo'] = 'bar';
        $this->assertArrayHasKey('foo', $this->property->getValue($this->bag));
        $bag = $this->property->getValue($this->bag);
        $this->assertSame('bar', $bag['foo']);
    }

    public function testGet()
    {
        $this->property->setValue($this->bag, ['foo' => 'bar']);
        $this->assertSame('bar', $this->bag->get('foo'));
    }

    public function testGetWithDefault()
    {
        $this->property->setValue($this->bag, ['foo' => 'bar']);
        $this->assertSame('default', $this->bag->get('abc', 'default'));
    }

    public function testReplace()
    {
        $this->bag->replace([
            'abc' => '123',
            'foo' => 'bar',
        ]);
        $this->assertArrayHasKey('abc', $this->property->getValue($this->bag));
        $this->assertArrayHasKey('foo', $this->property->getValue($this->bag));
        $bag = $this->property->getValue($this->bag);
        $this->assertSame('123', $bag['abc']);
        $this->assertSame('bar', $bag['foo']);
    }

    public function testAll()
    {
        $data = [
            'abc' => '123',
            'foo' => 'bar',
        ];
        $this->property->setValue($this->bag, $data);
        $this->assertSame($data, $this->bag->all());
    }

    public function testKeys()
    {
        $data = [
            'abc' => '123',
            'foo' => 'bar',
        ];
        $this->property->setValue($this->bag, $data);
        $this->assertSame(['abc', 'foo'], $this->bag->keys());
    }

    public function testHas()
    {
        $this->property->setValue($this->bag, ['foo' => 'bar']);
        $this->assertTrue($this->bag->has('foo'));
        $this->assertFalse($this->bag->has('abc'));
    }

    public function testOffsetExists()
    {
        $this->property->setValue($this->bag, ['foo' => 'bar']);
        $this->assertArrayHasKey('foo', $this->bag);
    }

    public function testRemove()
    {
        $data = [
            'abc' => '123',
            'foo' => 'bar',
        ];
        $this->property->setValue($this->bag, $data);
        $this->bag->remove('foo');
        $this->assertSame(['abc' => '123'], $this->property->getValue($this->bag));
    }

    public function testOffsetUnset()
    {
        $data = [
            'abc' => '123',
            'foo' => 'bar',
        ];
        $this->property->setValue($this->bag, $data);

        unset($this->bag['foo']);
        $this->assertSame(['abc' => '123'], $this->property->getValue($this->bag));
    }

    public function testClear()
    {
        $data = [
            'abc' => '123',
            'foo' => 'bar',
        ];
        $this->property->setValue($this->bag, $data);
        $this->bag->clear();
        $this->assertSame([], $this->property->getValue($this->bag));
    }

    public function testCount()
    {
        $this->property->setValue($this->bag, ['foo' => 'bar', 'abc' => '123']);
        $this->assertCount(2, $this->bag);
    }
}
