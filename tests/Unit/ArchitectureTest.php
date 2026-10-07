<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The planner must stay extractable as a package: code in app/Planner may
 * only reach outside itself for the platform contract.
 */
class ArchitectureTest extends TestCase
{
    private const ALLOWED_PREFIXES = [
        'App\\Planner\\',
        'App\\Models\\Club',
        'App\\Models\\User',
        'App\\Support\\Tenancy\\',
    ];

    public function test_planner_code_only_depends_on_the_platform_contract(): void
    {
        $violations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/Planner'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/^use\s+(App\\\\[^;\s]+)/m', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $import) {
                if (!$this->isAllowed($import)) {
                    $violations[] = $file->getFilename().': '.$import;
                }
            }
        }

        $this->assertSame([], $violations);
    }

    private function isAllowed(string $import): bool
    {
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($import === rtrim($prefix, '\\') || str_starts_with($import, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
