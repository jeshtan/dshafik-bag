<?php

declare(strict_types=1);

use Bag\TypeScript\BagTransformer;
use Bag\TypeScript\Reflection\BagReflectionProperty;
use Bag\TypeScript\Reflection\BagReflectionUnionType;
use Spatie\TypeScriptTransformer\Data\TransformationContext;
use Spatie\TypeScriptTransformer\Data\WritingContext;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\Transformed\Transformed;
use Spatie\TypeScriptTransformer\Transformers\ClassTransformer;
use Tests\Fixtures\Values\TypeScriptBag;

beforeEach()->skip(!class_exists(ClassTransformer::class));

if (class_exists(ClassTransformer::class)) {
    covers(BagTransformer::class);
}
covers(BagReflectionProperty::class, BagReflectionUnionType::class);

test('it transforms bags to typescript', function () {
    $transformer = new BagTransformer();
    $phpClassNode = PhpClassNode::fromClassString(TypeScriptBag::class);
    $context = TransformationContext::createFromPhpClass($phpClassNode);

    $result = $transformer->transform($phpClassNode, $context);

    expect($result)->toBeInstanceOf(Transformed::class);

    $writingContext = new WritingContext([]);
    $output = $result->getNode()->write($writingContext);

    expect($output)->toBe(
        "type TypeScriptBag = {\n" .
        "name: string,\n" .
        "age?: number,\n" .
        "email_address?: string | null,\n" .
        "};"
    );
});
