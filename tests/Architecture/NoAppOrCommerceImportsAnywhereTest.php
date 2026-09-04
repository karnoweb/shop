<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Architecture;

use Karnoweb\Shop\Tests\Support\SourceScanner;
use Karnoweb\Shop\Tests\TestCase;

/**
 * Package-wide guard across `src/`, `database/migrations_squashed/`,
 * `database/migrations_legacy/`, and `tests/`: nothing may import a host
 * `App\*` class or the `karnoweb/commerce` package.
 *
 * Complements the narrower, more targeted checks already in
 * {@see NoHostDependencyTest} (src only, broader forbidden list incl.
 * Accounting/Crm/Hr/Payment/...) and
 * {@see NoHardHostForeignKeyMigrationTest} (migrations only, FK-focused) by
 * specifically closing the `Karnoweb\Commerce` gap and extending coverage to
 * the test suite itself.
 */
final class NoAppOrCommerceImportsAnywhereTest extends TestCase
{
    /** @var list<string> */
    private const SCANNED_DIRECTORIES = [
        'src',
        'database/migrations_squashed',
        'database/migrations_legacy',
        'tests',
    ];

    /** @var list<string> */
    private const FORBIDDEN = [
        'App\\',
        'Karnoweb\\Commerce',
    ];

    public function test_package_does_not_import_app_or_commerce_namespaces(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

            $files = SourceScanner::phpFiles($path);
            $this->assertNotSame([], $files, "Expected at least one PHP file under {$directory}.");

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
            }
        }
    }
}
