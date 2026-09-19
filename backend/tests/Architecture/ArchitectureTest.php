<?php

declare(strict_types=1);

use Tests\Support\DomainDependencyScanner;

arch('domain modules do not depend on the HTTP layer')
    ->expect('App\Domains')
    ->not->toUse('App\Http');

arch('shared domain support does not depend on HTTP')
    ->expect('App\Domains\Shared')
    ->not->toUse('App\Http');

arch('production app code does not depend on tests')
    ->expect('App')
    ->not->toUse('Tests');

arch('API controllers are final')
    ->expect('App\Http\Controllers\Api')
    ->classes()
    ->toBeFinal();

it('prevents Shared from depending on business modules', function (): void {
    $violations = DomainDependencyScanner::findForbiddenSharedDependencies(app_path('Domains'));

    expect($violations)->toBeEmpty(
        'Shared must not import business modules: '.json_encode($violations),
    );
});

it('prevents domains from importing App\\Http via use statements', function (): void {
    $violations = DomainDependencyScanner::findDomainHttpDependencies(app_path('Domains'));

    expect($violations)->toBeEmpty(
        'Domain code must not import App\\Http: '.json_encode($violations),
    );
});

it('prevents production code from importing Tests namespaces', function (): void {
    $violations = DomainDependencyScanner::findProductionTestDependencies(app_path());

    expect($violations)->toBeEmpty(
        'Production code must not import Tests\\: '.json_encode($violations),
    );
});

it('forbids importing another module internal implementation', function (): void {
    $violations = DomainDependencyScanner::findInternalCrossModuleImports(app_path('Domains'));

    expect($violations)->toBeEmpty(
        'Cross-module internal imports are forbidden: '.json_encode($violations),
    );
});

it('forbids env() outside config files', function (): void {
    $roots = [
        app_path(),
        base_path('routes'),
        base_path('database'),
        base_path('bootstrap'),
    ];

    $violations = [];

    foreach ($roots as $root) {
        foreach (DomainDependencyScanner::phpFiles($root) as $file) {
            if (str_contains($file, DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            if (preg_match('/\benv\s*\(/', $contents) === 1) {
                $violations[] = $file;
            }
        }
    }

    expect($violations)->toBeEmpty('env() is only allowed in config files: '.implode(', ', $violations));
});

it('forbids debugging helpers in production code', function (): void {
    $violations = [];

    foreach (DomainDependencyScanner::phpFiles(app_path()) as $file) {
        $contents = file_get_contents($file);

        if ($contents === false) {
            continue;
        }

        if (preg_match('/\b(dd|dump|ray|var_dump|print_r)\s*\(/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Debugging helpers found: '.implode(', ', $violations));
});

it('forbids DB facade and raw SQL helpers in controllers', function (): void {
    $violations = [];

    foreach (DomainDependencyScanner::phpFiles(app_path('Http/Controllers')) as $file) {
        $contents = file_get_contents($file);

        if ($contents === false) {
            continue;
        }

        if (preg_match('/\bDB::/', $contents) === 1) {
            $violations[] = $file.' (DB::)';
        }

        if (preg_match('/\b(DB::select|DB::statement|selectRaw|whereRaw|orderByRaw)\s*\(/', $contents) === 1) {
            $violations[] = $file.' (raw SQL)';
        }

        if (preg_match('/\benv\s*\(/', $contents) === 1) {
            $violations[] = $file.' (env)';
        }
    }

    expect($violations)->toBeEmpty('Controller rule violations: '.implode(', ', $violations));
});

it('uses Data suffix for DTO class names when DTOs exist', function (): void {
    foreach (DomainDependencyScanner::businessModules() as $module) {
        $dtoPath = app_path('Domains/'.$module.'/DTOs');

        foreach (DomainDependencyScanner::phpFiles($dtoPath) as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            expect($base)->toEndWith('Data');
        }
    }

    expect(true)->toBeTrue();
});

it('uses Action suffix for Action class names when Actions exist', function (): void {
    foreach (array_merge(['Shared'], DomainDependencyScanner::businessModules()) as $module) {
        foreach (DomainDependencyScanner::phpFiles(app_path('Domains/'.$module.'/Actions')) as $file) {
            expect(pathinfo($file, PATHINFO_FILENAME))->toEndWith('Action');
        }
    }

    expect(true)->toBeTrue();
});

it('uses Query suffix for Query class names when Queries exist', function (): void {
    foreach (array_merge(['Shared'], DomainDependencyScanner::businessModules()) as $module) {
        foreach (DomainDependencyScanner::phpFiles(app_path('Domains/'.$module.'/Queries')) as $file) {
            expect(pathinfo($file, PATHINFO_FILENAME))->toEndWith('Query');
        }
    }

    expect(true)->toBeTrue();
});

it('uses Policy and Resource naming when those classes exist', function (): void {
    foreach (DomainDependencyScanner::phpFiles(app_path('Http/Resources')) as $file) {
        expect(pathinfo($file, PATHINFO_FILENAME))->toEndWith('Resource');
    }

    foreach (array_merge(['Shared'], DomainDependencyScanner::businessModules()) as $module) {
        foreach (DomainDependencyScanner::phpFiles(app_path('Domains/'.$module.'/Policies')) as $file) {
            expect(pathinfo($file, PATHINFO_FILENAME))->toEndWith('Policy');
        }
    }

    expect(true)->toBeTrue();
});
