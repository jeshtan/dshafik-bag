<?php

declare(strict_types=1);

namespace Bag\TypeScript;

use Bag\Bag;
use Bag\Pipelines\Pipes\ProcessParameters;
use Bag\Pipelines\Values\BagInput;
use Bag\Values\Optional as BagOptional;
use Exception;
use Illuminate\Support\Collection;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Spatie\TypeScriptTransformer\Data\TransformationContext;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\PhpNodes\PhpNamedTypeNode;
use Spatie\TypeScriptTransformer\PhpNodes\PhpPropertyNode;
use Spatie\TypeScriptTransformer\PhpNodes\PhpTypeNode;
use Spatie\TypeScriptTransformer\PhpNodes\PhpUnionTypeNode;
use Spatie\TypeScriptTransformer\Transformers\ClassTransformer;
use Spatie\TypeScriptTransformer\TypeResolvers\Data\ParsedClass;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNode;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptProperty;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptUnion;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptUnknown;

// @phpstan-ignore class.notFound
if (class_exists(ClassTransformer::class)) {
    class BagTransformer extends ClassTransformer
    {
        protected function shouldTransform(PhpClassNode $phpClassNode): bool
        {
            return $phpClassNode->getReflection(Bag::class);
        }

        protected function isPropertyOptional(
            PhpPropertyNode $phpPropertyNode,
            PhpClassNode $phpClassNode,
            TypeScriptNode $type,
            TransformationContext $context,
        ): bool {
            if ($this->propertyHasBagOptional($phpPropertyNode)) {
                return true;
            }

            return parent::isPropertyOptional($phpPropertyNode, $phpClassNode, $type, $context);
        }

        protected function isPropertyReadonly(
            PhpPropertyNode $phpPropertyNode,
            PhpClassNode $phpClassNode,
            TypeScriptNode $type,
            TransformationContext $context,
        ): bool {
            return false;
        }

        protected function resolveTypeForProperty(
            PhpClassNode $phpClassNode,
            PhpPropertyNode $phpPropertyNode,
            ?TypeNode $annotation,
            ?ParsedClass $parsedClass = null,
        ): TypeScriptNode {
            if (! $phpPropertyNode->hasType()) {
                return parent::resolveTypeForProperty($phpClassNode, $phpPropertyNode, $annotation, $parsedClass);
            }

            $phpType = $phpPropertyNode->getType();
            if (! $phpType instanceof PhpUnionTypeNode) {
                return parent::resolveTypeForProperty($phpClassNode, $phpPropertyNode, $annotation, $parsedClass);
            }

            $filteredTypes = array_values(array_filter(
                $phpType->getTypes(),
                fn (PhpTypeNode $type) => ! ($type instanceof PhpNamedTypeNode && $type->getName() === BagOptional::class)
            ));

            if (count($filteredTypes) === 0) {
                return new TypeScriptUnknown();
            }

            if (count($filteredTypes) === 1) {
                return $this->transpilePhpTypeNodeToTypeScriptTypeAction->execute($filteredTypes[0], $phpClassNode);
            }

            return new TypeScriptUnion(array_map(
                fn (PhpTypeNode $type) => $this->transpilePhpTypeNodeToTypeScriptTypeAction->execute($type, $phpClassNode),
                $filteredTypes
            ));
        }

        protected function createProperty(
            PhpClassNode $phpClassNode,
            PhpPropertyNode $phpPropertyNode,
            ?TypeNode $annotation,
            TransformationContext $context,
            ?ParsedClass $parsedClass = null,
        ): ?TypeScriptProperty {
            $property = parent::createProperty($phpClassNode, $phpPropertyNode, $annotation, $context, $parsedClass);

            if ($property === null) {
                return null;
            }

            $aliasedName = $this->getOutputAlias($phpClassNode->getName(), $phpPropertyNode->getName());
            if ($aliasedName === $phpPropertyNode->getName()) {
                return $property;
            }

            return new TypeScriptProperty(
                $aliasedName,
                $property->type,
                $property->isOptional,
                $property->isReadonly,
            );
        }

        private function propertyHasBagOptional(PhpPropertyNode $phpPropertyNode): bool
        {
            if (! $phpPropertyNode->hasType()) {
                return false;
            }

            $phpType = $phpPropertyNode->getType();
            if (! $phpType instanceof PhpUnionTypeNode) {
                return false;
            }

            foreach ($phpType->getTypes() as $subType) {
                if ($subType instanceof PhpNamedTypeNode && $subType->getName() === BagOptional::class) {
                    return true;
                }
            }

            return false;
        }

        private function getOutputAlias(string $bagClass, string $propertyName): string
        {
            $pipe = new ProcessParameters();
            /** @var class-string<Bag> $bagClass */
            $aliases = $pipe(new BagInput($bagClass, Collection::empty()))->params->aliases();

            return $aliases['output'][$propertyName] ?? $propertyName;
        }
    }
} else {
    class BagTransformer
    {
        public function __construct()
        {
            throw new Exception('You must install the spatie/typescript-transformer or spatie/laravel-typescript-transformer package to use this class');
        }
    }
}
