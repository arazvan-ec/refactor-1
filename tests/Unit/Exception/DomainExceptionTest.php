<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\AbstractDomainException;
use App\Exception\DomainExceptionInterface;
use App\Exception\Editorial\EditorialNotFoundException;
use App\Exception\Editorial\EditorialNotPublishedException;
use App\Exception\ExternalServiceException;
use App\Exception\Multimedia\MultimediaNotFoundException;
use App\Exception\Multimedia\UnsupportedMultimediaTypeException;
use App\Exception\Section\SectionNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(AbstractDomainException::class)]
#[CoversClass(EditorialNotFoundException::class)]
#[CoversClass(EditorialNotPublishedException::class)]
#[CoversClass(SectionNotFoundException::class)]
#[CoversClass(MultimediaNotFoundException::class)]
#[CoversClass(UnsupportedMultimediaTypeException::class)]
#[CoversClass(ExternalServiceException::class)]
class DomainExceptionTest extends TestCase
{
    #[Test]
    public function editorial_not_found_exception_has_correct_properties(): void
    {
        $exception = new EditorialNotFoundException('edit-123');

        self::assertInstanceOf(DomainExceptionInterface::class, $exception);
        self::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        self::assertSame('EDITORIAL_NOT_FOUND', $exception->getErrorCode());
        self::assertSame('The requested editorial was not found', $exception->getUserMessage());
        self::assertStringContainsString('edit-123', $exception->getMessage());
    }

    #[Test]
    public function editorial_not_published_exception_with_id(): void
    {
        $exception = new EditorialNotPublishedException('edit-456');

        self::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        self::assertSame('EDITORIAL_NOT_PUBLISHED', $exception->getErrorCode());
        self::assertStringContainsString('edit-456', $exception->getMessage());
    }

    #[Test]
    public function editorial_not_published_exception_without_id(): void
    {
        $exception = new EditorialNotPublishedException();

        self::assertSame('Editorial not published', $exception->getMessage());
    }

    #[Test]
    public function section_not_found_exception(): void
    {
        $exception = new SectionNotFoundException('sec-789');

        self::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        self::assertSame('SECTION_NOT_FOUND', $exception->getErrorCode());
        self::assertStringContainsString('sec-789', $exception->getMessage());
    }

    #[Test]
    public function multimedia_not_found_exception(): void
    {
        $exception = new MultimediaNotFoundException('media-123');

        self::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        self::assertSame('MULTIMEDIA_NOT_FOUND', $exception->getErrorCode());
        self::assertStringContainsString('media-123', $exception->getMessage());
    }

    #[Test]
    public function unsupported_multimedia_type_exception(): void
    {
        $exception = new UnsupportedMultimediaTypeException('unsupported_type');

        self::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        self::assertSame('UNSUPPORTED_MULTIMEDIA_TYPE', $exception->getErrorCode());
        self::assertStringContainsString('unsupported_type', $exception->getMessage());
    }

    #[Test]
    public function external_service_exception_with_reason(): void
    {
        $exception = new ExternalServiceException('EditorialService', 'Connection timeout');

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $exception->getStatusCode());
        self::assertSame('EXTERNAL_SERVICE_ERROR', $exception->getErrorCode());
        self::assertStringContainsString('EditorialService', $exception->getMessage());
        self::assertStringContainsString('Connection timeout', $exception->getMessage());
    }

    #[Test]
    public function external_service_exception_without_reason(): void
    {
        $exception = new ExternalServiceException('MultimediaService');

        self::assertStringContainsString('MultimediaService', $exception->getMessage());
        self::assertStringContainsString('unavailable', $exception->getMessage());
    }

    #[Test]
    public function exceptions_can_wrap_previous_exception(): void
    {
        $previous = new \RuntimeException('Original error');
        $exception = new EditorialNotFoundException('edit-123', $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    /**
     * @return array<string, array{class-string<DomainExceptionInterface>, int}>
     */
    public static function exceptionStatusCodeProvider(): array
    {
        return [
            'editorial not found' => [EditorialNotFoundException::class, Response::HTTP_NOT_FOUND],
            'editorial not published' => [EditorialNotPublishedException::class, Response::HTTP_NOT_FOUND],
            'section not found' => [SectionNotFoundException::class, Response::HTTP_NOT_FOUND],
            'multimedia not found' => [MultimediaNotFoundException::class, Response::HTTP_NOT_FOUND],
            'unsupported multimedia' => [UnsupportedMultimediaTypeException::class, Response::HTTP_BAD_REQUEST],
            'external service' => [ExternalServiceException::class, Response::HTTP_SERVICE_UNAVAILABLE],
        ];
    }

    /**
     * @param class-string<DomainExceptionInterface> $exceptionClass
     */
    #[Test]
    #[DataProvider('exceptionStatusCodeProvider')]
    public function exceptions_have_correct_status_codes(string $exceptionClass, int $expectedStatusCode): void
    {
        $exception = match ($exceptionClass) {
            EditorialNotFoundException::class => new EditorialNotFoundException('id'),
            EditorialNotPublishedException::class => new EditorialNotPublishedException('id'),
            SectionNotFoundException::class => new SectionNotFoundException('id'),
            MultimediaNotFoundException::class => new MultimediaNotFoundException('id'),
            UnsupportedMultimediaTypeException::class => new UnsupportedMultimediaTypeException('type'),
            ExternalServiceException::class => new ExternalServiceException('service'),
        };

        self::assertSame($expectedStatusCode, $exception->getStatusCode());
    }
}
