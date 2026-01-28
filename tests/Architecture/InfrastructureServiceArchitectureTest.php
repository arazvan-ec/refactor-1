<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

/**
 * Architecture validation for Infrastructure Service layer.
 *
 * Ensures that Infrastructure Services (Thumbor, PictureShots) don't inject HTTP clients.
 * The Infrastructure/Client directory is EXCLUDED as those ARE the HTTP client implementations.
 *
 * @group architecture
 * @group architecture-infrastructure
 */
final class InfrastructureServiceArchitectureTest extends AbstractArchitectureTest
{
    protected function getForbiddenPatterns(): array
    {
        return self::HTTP_CLIENT_PATTERNS;
    }

    protected function getViolationMessage(): string
    {
        return <<<'MSG'
Infrastructure Service classes should NOT inject HTTP clients.
Infrastructure Services provide utilities (URL building, config, etc.), not HTTP calls.

To fix:
1. If HTTP calls are needed, create a Client in Infrastructure/Client/
2. Or move the HTTP calls to an Orchestrator service
MSG;
    }

    /**
     * @dataProvider infrastructureServiceClassesProvider
     */
    public function test_infrastructure_services_do_not_inject_http_clients(string $className): void
    {
        $this->assertNoForbiddenDependencies($className);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function infrastructureServiceClassesProvider(): iterable
    {
        $serviceDir = self::getSrcDir() . '/Infrastructure/Service';

        foreach (self::findPhpClasses($serviceDir) as $className) {
            yield $className => [$className];
        }

        // Also check Config classes (should have no dependencies at all)
        $configDir = self::getSrcDir() . '/Infrastructure/Config';

        foreach (self::findPhpClasses($configDir) as $className) {
            yield $className => [$className];
        }
    }

    /**
     * Infrastructure Config classes should have no constructor dependencies.
     *
     * @dataProvider infrastructureConfigClassesProvider
     */
    public function test_infrastructure_config_has_no_dependencies(string $className): void
    {
        if (!class_exists($className)) {
            $this->addToAssertionCount(1);
            return;
        }

        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            $this->addToAssertionCount(1);
            return;
        }

        $params = $constructor->getParameters();

        if (count($params) > 0) {
            $paramNames = array_map(fn($p) => '$' . $p->getName(), $params);
            $this->fail(sprintf(
                "Config class %s should have no constructor dependencies, but has: %s\n\n" .
                "Config classes should only contain constants and static methods.",
                $className,
                implode(', ', $paramNames)
            ));
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function infrastructureConfigClassesProvider(): iterable
    {
        $configDir = self::getSrcDir() . '/Infrastructure/Config';

        foreach (self::findPhpClasses($configDir) as $className) {
            yield $className => [$className];
        }
    }
}
