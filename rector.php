<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnNewRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedPropertyRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/app/Providers',
        __DIR__.'/database/factories',
        NullToStrictStringFuncCallArgRector::class,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withPhpSets(php85: true)
    ->withSets([
        LaravelSetList::LARAVEL_120,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
    ])
    ->withRules([
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessVarTagRector::class,
        RemoveNonExistingVarAnnotationRector::class,
        AddVoidReturnTypeWhereNoReturnRector::class,
        ReturnTypeFromReturnNewRector::class,
        ReturnTypeFromStrictNativeCallRector::class,
        ReturnTypeFromStrictTypedPropertyRector::class,
        TypedPropertyFromAssignsRector::class,
    ]);
