<?php
declare(strict_types=1);

namespace Coresh\CustomerAttribute\Test\Unit\Model\Uuid;

use Coresh\CustomerAttribute\Model\Uuid\Generator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UUID generation and validation.
  *
 *
 * @author: Dmitrii Dmitriev
 * @link: https://www.upwork.com/freelancers/dmitriid15
 */
class GeneratorTest extends TestCase
{
    /**
     * Verify generated UUIDs use canonical lowercase UUID v4 syntax.
     *
     * @return void
     */
    public function testGenerateReturnsCanonicalUuid(): void
    {
        $generator = new Generator();
        $uuid = $generator->generate();

        self::assertTrue($generator->isValid($uuid));
        self::assertSame(strtolower($uuid), $uuid);
    }

    /**
     * Verify multiple generated UUIDs are unique in a small sample.
     *
     * @return void
     */
    public function testGenerateReturnsDifferentValues(): void
    {
        $generator = new Generator();
        $values = [];

        for ($i = 0; $i < 100; $i++) {
            $values[] = $generator->generate();
        }

        self::assertCount(100, array_unique($values));
    }
}
