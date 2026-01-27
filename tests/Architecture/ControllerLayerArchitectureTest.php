<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use ReflectionClass;
use ReflectionNamedType;

/**
 * Architecture validation for Controller layer.
 *
 * Ensures that Controllers:
 * - Only inject OrchestratorChain or OrchestratorChainHandler
 * - Don't inject HTTP clients directly
 * - Don't inject Application services directly (should go through Orchestrator)
 *
 * @group architecture
 * @group architecture-controller
 */
final class ControllerLayerArchitectureTest extends AbstractArchitectureTest
{
    /**
     * Allowed dependencies for Controllers.
     *
     * @var list<string>
     */
    private const ALLOWED_DEPENDENCIES = [
        'App\\Orchestrator\\OrchestratorChain',
        'App\\Orchestrator\\OrchestratorChainHandler',
        'Psr\\Log\\LoggerInterface',
        'Symfony\\Component\\Serializer\\SerializerInterface',
    ];

    protected function getForbiddenPatterns(): array
    {
        return array_merge(
            self::HTTP_CLIENT_PATTERNS,
            [
                'Fetcher',      // Application fetchers
                'Transformer',  // Data transformers
                'Aggregator',   // Response aggregators
            ]
        );
    }

    protected function getViolationMessage(): string
    {
        return <<<'MSG'
Controllers should ONLY inject OrchestratorChain or OrchestratorChainHandler.
All business logic should be delegated to Orchestrators.

Allowed dependencies:
- OrchestratorChain / OrchestratorChainHandler
- LoggerInterface
- SerializerInterface

To fix:
1. Remove direct dependencies on Fetchers, Transformers, Aggregators
2. Use OrchestratorChain to handle all request processing
MSG;
    }

    /**
     * @dataProvider controllerClassesProvider
     */
    public function test_controllers_only_inject_orchestrator(string $className): void
    {
        if (!class_exists($className)) {
            $this->addToAssertionCount(1);
            return;
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

            // Check if it's an allowed dependency
            $isAllowed = false;
            foreach (self::ALLOWED_DEPENDENCIES as $allowed) {
                if ($typeName === $allowed || str_ends_with($typeName, '\\' . basename(str_replace('\\', '/', $allowed)))) {
                    $isAllowed = true;
                    break;
                }
            }

            if ($isAllowed) {
                continue;
            }

            // Check if it's a forbidden pattern
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
                "Controller %s violates architecture rules:\n- %s\n\n%s",
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
    public static function controllerClassesProvider(): iterable
    {
        $controllerDir = self::getSrcDir() . '/Controller';

        foreach (self::findPhpClasses($controllerDir) as $className) {
            // Skip non-controller classes (like Schemas)
            if (!str_contains($className, 'Controller')) {
                continue;
            }

            yield $className => [$className];
        }
    }
}
