<?php
/**
 * @copyright
 */

namespace App\Tests\Exception;

use App\Exception\EditorialNotPublishedYetException;
use PHPUnit\Framework\TestCase;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 *
 * @covers \App\Exception\EditorialNotPublishedYetException
 */
class EditorialNotPublishedYetExceptionTest extends TestCase
{
    private const MESSAGE = 'Editorial not published';
    private const CODE = 404;

    /**
     * @test
     */
    public function exceptionMessageShouldBeExpectedOne(): void
    {
        $exception = new EditorialNotPublishedYetException();
        $this->assertEquals(self::MESSAGE, $exception->getMessage());
    }

    /**
     * @test
     */
    public function exceptionCodeShouldBeExpectedOne(): void
    {
        $exception = new EditorialNotPublishedYetException();
        $this->assertEquals(self::CODE, $exception->getCode());
    }
}
