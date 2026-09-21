<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Lightweight static dependency scanner for domain module boundaries.
 */
final class DomainDependencyScanner
{
    /**
     * @return list<string>
     */
    public static function businessModules(): array
    {
        return [
            'Identity',
            'Catalog',
            'Inventory',
            'Pricing',
            'Cart',
            'Checkout',
            'Orders',
            'Payments',
            'Shipping',
            'Hunting',
            'Geography',
            'Recommendations',
            'Content',
            'Notifications',
            'Operations',
        ];
    }

    /**
     * @return list<array{file: string, import: string}>
     */
    public static function findImports(string $absoluteDirectory, string $importPattern): array
    {
        if (! is_dir($absoluteDirectory)) {
            return [];
        }

        $violations = [];
        $iterator = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absoluteDirectory)),
            '/^.+\.php$/i',
            \RegexIterator::GET_MATCH,
        );

        /** @var array{0: string} $match */
        foreach ($iterator as $match) {
            $file = $match[0];
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            if (preg_match_all('/^use\s+([^;]+);/m', $contents, $uses) === false) {
                continue;
            }

            foreach ($uses[1] as $import) {
                $import = trim($import);

                if (str_contains($import, ' as ')) {
                    $import = trim(explode(' as ', $import, 2)[0]);
                }

                if (preg_match($importPattern, $import) === 1) {
                    $violations[] = [
                        'file' => $file,
                        'import' => $import,
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<array{file: string, import: string}>
     */
    public static function findForbiddenSharedDependencies(string $domainsRoot): array
    {
        $patternParts = array_map(
            static fn (string $module): string => preg_quote($module, '/'),
            self::businessModules(),
        );

        $pattern = '/^App\\\\Domains\\\\('.implode('|', $patternParts).')(\\\\|$)/';

        return self::findImports($domainsRoot.'/Shared', $pattern);
    }

    /**
     * @return list<array{file: string, import: string}>
     */
    public static function findDomainHttpDependencies(string $domainsRoot): array
    {
        return self::findImports($domainsRoot, '/^App\\\\Http(\\\\|$)/');
    }

    /**
     * @return list<array{file: string, import: string}>
     */
    public static function findProductionTestDependencies(string $appRoot): array
    {
        return self::findImports($appRoot, '/^Tests(\\\\|$)/');
    }

    /**
     * @return list<array{file: string, import: string, from: string, to: string}>
     */
    public static function findInternalCrossModuleImports(string $domainsRoot): array
    {
        $publicSegments = ['Contracts', 'Actions', 'Queries', 'DTOs', 'Events', 'Enums', 'Models'];
        $violations = [];
        $allModules = array_merge(['Shared'], self::businessModules());

        foreach ($allModules as $fromModule) {
            $modulePath = $domainsRoot.'/'.$fromModule;

            if (! is_dir($modulePath)) {
                continue;
            }

            $imports = self::findImports($modulePath, '/^App\\\\Domains\\\\[A-Za-z]+\\\\/');

            foreach ($imports as $hit) {
                if (! preg_match('/^App\\\\Domains\\\\([A-Za-z]+)\\\\([A-Za-z]+)(?:\\\\|$)/', $hit['import'], $m)) {
                    continue;
                }

                $toModule = $m[1];
                $segment = $m[2];

                if ($toModule === $fromModule) {
                    continue;
                }

                // Shared is an approved dependency for every business module.
                if ($toModule === 'Shared') {
                    continue;
                }

                if (in_array($segment, $publicSegments, true)) {
                    continue;
                }

                $violations[] = [
                    'file' => $hit['file'],
                    'import' => $hit['import'],
                    'from' => $fromModule,
                    'to' => $toModule,
                ];
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public static function phpFiles(string $absoluteDirectory): array
    {
        if (! is_dir($absoluteDirectory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absoluteDirectory));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
