<?php

declare(strict_types=1);

namespace Karnoweb\Shop\Tests\Architecture;

use Karnoweb\Shop\Tests\Support\SourceScanner;
use Karnoweb\Shop\Tests\TestCase;

/**
 * Guards the DB layer against the same boundary rules {@see NoHostDependencyTest}
 * enforces for PHP source: migrations must not hard-couple this package's schema to
 * host tables (users, categories, user_groups) or to another domain package's
 * tables (commerce owns discounts/orders/order_items/invoices/payments/wallets).
 * Cross-boundary references must stay soft (unsignedBigInteger + index, never
 * ->constrained()/->foreign()), and this package must never alter another
 * package's/the host's tables via Schema::table()/Schema::create().
 */
final class NoHardHostForeignKeyMigrationTest extends TestCase
{
    /** @var list<string> Host or cross-domain tables this package must never hard-FK. */
    private const FORBIDDEN_FOREIGN_KEY_TABLES = [
        'users',
        'categories',
        'user_groups',
        'discounts',
        'orders',
        'order_items',
        'invoices',
        'payments',
        'wallets',
    ];

    /** @var list<string> Tables owned by the host or another domain package (never altered here). */
    private const FORBIDDEN_SCHEMA_TARGETS = [
        'users',
        'categories',
        'orders',
        'order_items',
        'payments',
        'invoices',
        'wallets',
        'wallet_transactions',
        'discounts',
    ];

    public function test_migrations_do_not_hard_foreign_key_host_or_cross_domain_tables(): void
    {
        $migrations = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

        foreach (SourceScanner::phpFiles($migrations) as $file) {
            $contents = (string) file_get_contents($file);

            foreach (self::FORBIDDEN_FOREIGN_KEY_TABLES as $table) {
                $quoted = preg_quote($table, '/');

                $this->assertDoesNotMatchRegularExpression(
                    "/->constrained\\(\\s*['\"]{$quoted}['\"]/i",
                    $contents,
                    "Migration {$file} declares ->constrained('{$table}'), a hard FK to a host/cross-domain table."
                );

                $this->assertDoesNotMatchRegularExpression(
                    "/->on\\(\\s*['\"]{$quoted}['\"]/i",
                    $contents,
                    "Migration {$file} declares ->on('{$table}'), a hard FK to a host/cross-domain table."
                );
            }

            foreach (self::FORBIDDEN_SCHEMA_TARGETS as $table) {
                $quoted = preg_quote($table, '/');

                $this->assertDoesNotMatchRegularExpression(
                    "/Schema::(table|create)\\(\\s*['\"]{$quoted}['\"]/i",
                    $contents,
                    "Migration {$file} alters host/cross-domain table [{$table}]; each package owns only its own schema."
                );
            }

            $this->assertDoesNotMatchRegularExpression(
                '/^use\s+App\\\\/m',
                $contents,
                "Migration {$file} imports a host App\\ namespace. Migrations must be installable standalone."
            );
        }
    }
}
