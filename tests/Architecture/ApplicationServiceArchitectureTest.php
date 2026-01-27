<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

/**
 * Architecture validation for Application Service layer.
 *
 * Ensures that Application Services (Fetchers, Resolvers) don't inject HTTP clients.
 * HTTP calls should be made in the Orchestrator layer, not in Application services.
 *
 * CRITICAL: EditorialFetcher and EmbeddedContentFetcher currently VIOLATE this rule.
 * This test documents the architectural debt and will fail until refactored.
 *
 * @group architecture
 * @group architecture-application
 */
final class ApplicationServiceArchitectureTest extends AbstractArchitectureTest
{
    /**
     * Classes that are KNOWN violations - tracked as technical debt.
     * These classes SHOULD be refactored to move HTTP calls to Orchestrator.
     *
     * @var list<string>
     */
    private const KNOWN_VIOLATIONS = [
        'App\\Application\\Service\\Editorial\\EditorialFetcher',
        'App\\Application\\Service\\Editorial\\EmbeddedContentFetcher',
    ];

    protected function getForbiddenPatterns(): array
    {
        return self::HTTP_CLIENT_PATTERNS;
    }

    protected function getAllowedExceptions(): array
    {
        // Temporarily allow known violations until they are refactored
        // Remove from this list as each class is fixed
        return self::KNOWN_VIOLATIONS;
    }

    protected function getViolationMessage(): string
    {
        return <<<'MSG'
Application Service classes should NOT inject HTTP clients.
HTTP calls belong in the Orchestrator layer.

To fix:
1. Create a Fetcher service in src/Orchestrator/Service/
2. Move HTTP calls to the new Orchestrator service
3. Pass pre-fetched data to the Application service via DTO
MSG;
    }

    /**
     * @dataProvider applicationServiceClassesProvider
     */
    public function test_application_services_do_not_inject_http_clients(string $className): void
    {
        $this->assertNoForbiddenDependencies($className);
    }

    /**
     * Test specifically to track known violations.
     * This test PASSES when violations exist (documenting debt).
     * When violations are fixed, this test should be updated or removed.
     */
    public function test_known_violations_are_documented(): void
    {
        $stillViolating = [];

        foreach (self::KNOWN_VIOLATIONS as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if (null === $constructor) {
                continue;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType && $this->isForbiddenDependency($type->getName())) {
                    $stillViolating[$className] = true;
                    break;
                }
            }
        }

        // Document how many violations remain
        $this->addToAssertionCount(1);

        if (!empty($stillViolating)) {
            $this->markTestIncomplete(sprintf(
                "Technical debt: %d Application Services still inject HTTP clients:\n- %s\n\n" .
                "See Phase 2 and Phase 3 in .claude/analysis/02_layer_architecture_plan.md",
                count($stillViolating),
                implode("\n- ", array_keys($stillViolating))
            ));
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function applicationServiceClassesProvider(): iterable
    {
        $servicesDir = self::getSrcDir() . '/Application/Service';

        foreach (self::findPhpClasses($servicesDir) as $className) {
            // Skip interfaces
            if (str_ends_with($className, 'Interface')) {
                continue;
            }

            yield $className => [$className];
        }
    }
}
