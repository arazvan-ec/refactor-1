<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Finder\Finder;

/**
 * Architectural validation for transformation layer classes.
 *
 * Ensures that DataTransformers and ResponseAggregators don't make HTTP calls.
 * These classes should only transform data, not fetch it.
 *
 * @group architecture
 */
final class TransformationLayerArchitectureTest extends TestCase
{
    /**
     * HTTP client class patterns that should NOT be injected in transformation layer.
     *
     * @var list<string>
     */
    private const FORBIDDEN_CLIENT_PATTERNS = [
        'Client',           // Any class ending in Client
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
     * Namespaces that define transformation layer classes.
     *
     * @var list<string>
     */
    private const TRANSFORMATION_LAYER_NAMESPACES = [
        'App\\Application\\DataTransformer',
        'App\\Application\\Service\\Editorial\\ResponseAggregator',
    ];

    /**
     * Classes explicitly allowed to have clients (exceptions to the rule).
     *
     * @var list<string>
     */
    private const ALLOWED_EXCEPTIONS = [
        // Add classes here that are explicitly allowed to break the rule
        // Example: 'App\\Application\\Service\\SomeSpecialService',
    ];

    /**
     * @dataProvider transformationLayerClassesProvider
     */
    public function test_transformation_layer_does_not_inject_http_clients(string $className): void
    {
        if (in_array($className, self::ALLOWED_EXCEPTIONS, true)) {
            $this->markTestSkipped("Class {$className} is explicitly allowed to have clients.");
        }

        $reflection = new ReflectionClass($className);
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

            if ($this->isForbiddenClient($typeName)) {
                $violations[] = sprintf(
                    'Parameter $%s has forbidden type %s',
                    $parameter->getName(),
                    $typeName
                );
            }
        }

        if (!empty($violations)) {
            $this->fail(sprintf(
                "Class %s violates transformation layer architecture:\n- %s\n\n" .
                "Transformation layer classes should NOT inject HTTP clients.\n" .
                "Move the HTTP calls to the Orchestrator layer and pass the fetched data as parameters.",
                $className,
                implode("\n- ", $violations)
            ));
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function transformationLayerClassesProvider(): iterable
    {
        $srcDir = dirname(__DIR__, 2) . '/src';

        // DataTransformers
        $dataTransformerDir = $srcDir . '/Application/DataTransformer';
        if (is_dir($dataTransformerDir)) {
            foreach (self::findPhpClasses($dataTransformerDir) as $className) {
                yield $className => [$className];
            }
        }

        // ResponseAggregator and related services
        $servicesDir = $srcDir . '/Application/Service/Editorial';
        if (is_dir($servicesDir)) {
            foreach (self::findPhpClasses($servicesDir) as $className) {
                // Only check aggregator classes, not all services
                if (str_contains($className, 'Aggregator')) {
                    yield $className => [$className];
                }
            }
        }
    }

    /**
     * Find all PHP classes in a directory.
     *
     * @return iterable<string>
     */
    private static function findPhpClasses(string $directory): iterable
    {
        $finder = new Finder();
        $finder->files()->in($directory)->name('*.php');

        foreach ($finder as $file) {
            $className = self::getClassNameFromFile($file->getRealPath());
            if (null !== $className && class_exists($className)) {
                yield $className;
            }
        }
    }

    /**
     * Extract fully qualified class name from a PHP file.
     */
    private static function getClassNameFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if (false === $contents) {
            return null;
        }

        $namespace = null;
        $class = null;

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        // Extract class/interface/trait name
        if (preg_match('/(?:class|interface|trait)\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if (null === $namespace || null === $class) {
            return null;
        }

        return $namespace . '\\' . $class;
    }

    /**
     * Check if a type name matches a forbidden client pattern.
     */
    private function isForbiddenClient(string $typeName): bool
    {
        foreach (self::FORBIDDEN_CLIENT_PATTERNS as $pattern) {
            // Check if type name ends with the pattern
            if (str_ends_with($typeName, $pattern)) {
                return true;
            }

            // Check if type name contains the pattern as a full word
            if (str_contains($typeName, '\\' . $pattern)) {
                return true;
            }
        }

        return false;
    }
}
