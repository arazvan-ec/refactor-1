<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Finder\Finder;

/**
 * Base class for architecture validation tests.
 *
 * Provides common functionality for detecting forbidden dependencies
 * across different layers of the application.
 *
 * @group architecture
 */
abstract class AbstractArchitectureTest extends TestCase
{
    /**
     * HTTP client class patterns that should NOT be injected in most layers.
     *
     * @var list<string>
     */
    protected const HTTP_CLIENT_PATTERNS = [
        'Client',
        'HttpClient',
        'GuzzleHttp',
        'QueryEditorialClient',
        'QuerySectionClient',
        'QueryMultimediaClient',
        'QueryJournalistClient',
        'QueryTagClient',
        'QueryMembershipClient',
        'QueryWidgetClient',
        'QueryLegacyClient',
    ];

    /**
     * Get the forbidden dependency patterns for this test.
     *
     * @return list<string>
     */
    abstract protected function getForbiddenPatterns(): array;

    /**
     * Get the allowed exception classes for this test.
     *
     * @return list<string>
     */
    protected function getAllowedExceptions(): array
    {
        return [];
    }

    /**
     * Get the error message for violations.
     */
    abstract protected function getViolationMessage(): string;

    /**
     * Assert that a class does not inject forbidden dependencies.
     */
    protected function assertNoForbiddenDependencies(string $className): void
    {
        if (in_array($className, $this->getAllowedExceptions(), true)) {
            $this->markTestSkipped("Class {$className} is explicitly allowed.");
        }

        if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
            $this->addToAssertionCount(1);
            return;
        }

        $reflection = new ReflectionClass($className);

        // Skip interfaces and traits
        if ($reflection->isInterface() || $reflection->isTrait()) {
            $this->addToAssertionCount(1);
            return;
        }

        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            $this->addToAssertionCount(1);
            return;
        }

        $violations = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();

            if ($this->isForbiddenDependency($typeName)) {
                $violations[] = sprintf(
                    'Parameter $%s has forbidden type %s',
                    $parameter->getName(),
                    $typeName
                );
            }
        }

        if (!empty($violations)) {
            $this->fail(sprintf(
                "Class %s violates architecture rules:\n- %s\n\n%s",
                $className,
                implode("\n- ", $violations),
                $this->getViolationMessage()
            ));
        }

        $this->addToAssertionCount(1);
    }

    /**
     * Check if a type name matches a forbidden pattern.
     */
    protected function isForbiddenDependency(string $typeName): bool
    {
        foreach ($this->getForbiddenPatterns() as $pattern) {
            if (str_ends_with($typeName, $pattern)) {
                return true;
            }

            if (str_contains($typeName, '\\' . $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find all PHP classes in a directory.
     *
     * @return iterable<string>
     */
    protected static function findPhpClasses(string $directory): iterable
    {
        if (!is_dir($directory)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name('*.php');

        foreach ($finder as $file) {
            $className = self::getClassNameFromFile($file->getRealPath());
            if (null !== $className) {
                yield $className;
            }
        }
    }

    /**
     * Extract fully qualified class name from a PHP file.
     */
    protected static function getClassNameFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if (false === $contents) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/(?:class|interface|trait)\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if (null === $namespace || null === $class) {
            return null;
        }

        return $namespace . '\\' . $class;
    }

    /**
     * Get the source directory path.
     */
    protected static function getSrcDir(): string
    {
        return dirname(__DIR__, 2) . '/src';
    }
}
