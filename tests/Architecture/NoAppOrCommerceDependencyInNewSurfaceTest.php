<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Architecture;

use Karnoweb\Shop\Tests\Support\SourceScanner;
use Karnoweb\Shop\Tests\TestCase;

/**
 * Guards the new Accounting-like builder/DTO/quote surface: none of it may
 * import a host `App\*` class or the `karnoweb/commerce` package. {@see
 * NoHostDependencyTest} already covers the whole `src/` tree for a broader
 * forbidden list, but does not include `Karnoweb\Commerce` — this test closes
 * that gap specifically for the surface added in this pass.
 */
final class NoAppOrCommerceDependencyInNewSurfaceTest extends TestCase
{
    /** @var list<string> */
    private const SCANNED_DIRECTORIES = ['Builders', 'DTOs', 'Services'];

    /** @var list<string> */
    private const FORBIDDEN = [
        'App\\',
        'Karnoweb\\Commerce',
    ];

    public function test_new_surface_does_not_import_app_or_commerce_namespaces(): void
    {
        $srcRoot = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'src';

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $path = $srcRoot.DIRECTORY_SEPARATOR.$directory;

            $files = SourceScanner::phpFiles($path);
            $this->assertNotSame([], $files, "Expected at least one PHP file under src/{$directory}.");

            foreach ($files as $file) {
                $contents = (string) file_get_contents($file);
                $names = SourceScanner::importedAndQualifiedNames($contents);

                foreach ($names as $name) {
                    foreach (self::FORBIDDEN as $forbidden) {
                        $this->assertFalse(
                            str_starts_with($name, $forbidden),
                            "Forbidden namespace [{$forbidden}] referenced as [{$name}] in {$file}"
                        );
                    }
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/use\s+App\\\\/',
                    $contents,
                    "Host App\\ reference found in {$file}"
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/Karnoweb\\\\Commerce/',
                    $contents,
                    "Karnoweb\\Commerce reference found in {$file}"
                );
            }
        }
    }
}
