<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use ReflectionClass;

/**
 * Architecture validation for Exception layer.
 *
 * Ensures that Exception classes:
 * - Have no service dependencies injected
 * - Only receive primitive values (strings, ints) or other exceptions
 *
 * @group architecture
 * @group architecture-exception
 */
final class ExceptionArchitectureTest extends AbstractArchitectureTest
{
    /**
     * Allowed constructor parameter types for Exceptions.
     *
     * @var list<string>
     */
    private const ALLOWED_TYPES = [
        'string',
        'int',
        'float',
        'bool',
        'array',
        'Throwable',
        '?Throwable',
        'Exception',
        '?Exception',
    ];

    protected function getForbiddenPatterns(): array
    {
        // Exceptions should not inject ANY services
        return array_merge(
            self::HTTP_CLIENT_PATTERNS,
            [
                'Interface',  // No service interfaces
                'Service',    // No services
                'Repository', // No repositories
                'Logger',     // No loggers
            ]
        );
    }

    protected function getViolationMessage(): string
    {
        return <<<'MSG'
Exception classes should NOT have service dependencies.
Exceptions should only receive primitive values or other exceptions.

To fix:
1. Remove all service dependencies from the constructor
2. Only pass strings, ints, or other exceptions to constructors
3. Use factory methods if complex construction is needed
MSG;
    }

    /**
     * @dataProvider exceptionClassesProvider
     */
    public function test_exceptions_have_no_service_dependencies(string $className): void
    {
        if (!class_exists($className)) {
            $this->addToAssertionCount(1);
            return;
        }

        $reflection = new ReflectionClass($className);

        // Skip interfaces
        if ($reflection->isInterface()) {
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

            if (null === $type) {
                continue;
            }

            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;

            // Check if it's an allowed type
            if (in_array($typeName, self::ALLOWED_TYPES, true)) {
                continue;
            }

            // Check if it's a Throwable subclass
            if (class_exists($typeName) && is_subclass_of($typeName, \Throwable::class)) {
                continue;
            }

            // Any other type is forbidden
            $violations[] = sprintf(
                'Parameter $%s has type %s (only primitives and Throwables allowed)',
                $parameter->getName(),
                $typeName
            );
        }

        if (!empty($violations)) {
            $this->fail(sprintf(
                "Exception %s violates architecture rules:\n- %s\n\n%s",
                $className,
                implode("\n- ", $violations),
                $this->getViolationMessage()
            ));
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function exceptionClassesProvider(): iterable
    {
        $exceptionDir = self::getSrcDir() . '/Exception';

        foreach (self::findPhpClasses($exceptionDir) as $className) {
            yield $className => [$className];
        }

        // Also check Orchestrator exceptions
        $orchestratorExceptionDir = self::getSrcDir() . '/Orchestrator/Exceptions';

        foreach (self::findPhpClasses($orchestratorExceptionDir) as $className) {
            yield $className => [$className];
        }
    }
}
