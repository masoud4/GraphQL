<?php
// src/Executor/Executor.php
namespace masoud4\GraphQL\Executor;

use masoud4\GraphQL\Schema;
use masoud4\GraphQL\Type;
use masoud4\GraphQL\Error\GraphQLError;

/**
 * A VERY basic GraphQL query executor.
 * This executor supports:
 * - Resolving fields from the root Query or Mutation type.
 * - Basic scalar type resolution.
 * - Basic nested object resolution with field selection.
 * - No arguments, fragments, directives, or complex type coercion.
 *
 * This is still a simplified executor.
 */
class Executor
{
    protected Schema $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Executes a parsed GraphQL operation against the schema.
     * @param array $parsedQuery The output from QueryParser (e.g., ['operationType' => 'query', 'fields' => ['field1' => [], 'user' => ['id' => [], 'name' => []]]]).
     * @param array $rootValue The root value for the execution (e.g., a data source or context).
     * @return array The result of the execution.
     * @throws GraphQLError
     */
    public function execute(array $parsedQuery, array $rootValue = []): array
    {
        $operationType = $parsedQuery['operationType'];
        $selectionSet = $parsedQuery['fields']; // This is now a structured array of fields

        $rootType = null;
        if ($operationType === 'query') {
            $rootType = $this->schema->getQueryType();
        } elseif ($operationType === 'mutation') {
            $rootType = $this->schema->getMutationType();
            if (!$rootType) {
                throw new GraphQLError("Schema does not define a Mutation type.");
            }
        } else {
            throw new GraphQLError("Unsupported operation type: {$operationType}");
        }

        return $this->resolveSelections($selectionSet, $rootType, $rootValue);
    }

    /**
     * Recursively resolves a selection set on a given object type.
     * @param array $selectionSet The structured array of fields to select (e.g., ['id' => [], 'user' => ['name' => []]]).
     * @param Type $currentType The current ObjectType being resolved against.
     * @param mixed $source The source data for the current type (e.g., an array or object returned by a parent resolver).
     * @return array The resolved data for the selection set.
     * @throws GraphQLError
     */
    protected function resolveSelections(array $selectionSet, Type $currentType, mixed $source): array
    {
        if ($currentType->getKind() !== Type::KIND_OBJECT) {
            // This should ideally not be reached if schema is well-formed and parser is correct.
            // Or it implies an attempt to select fields on a non-object type (e.g. `String { field }`).
            throw new GraphQLError("Cannot resolve selections on a non-object type ({$currentType->getName()}).");
        }

        $result = [];
        foreach ($selectionSet as $fieldName => $nestedSelectionSet) {
            $fieldDefinition = $currentType->getField($fieldName);

            if (!$fieldDefinition) {
                // If a field is requested that doesn't exist on the schema, it's an error.
                throw new GraphQLError("Cannot query field \"{$fieldName}\" on type \"{$currentType->getName()}\".");
            }

            $resolver = $fieldDefinition['resolve'] ?? null;
            $fieldType = $fieldDefinition['type'];

            $fieldValue = null;
            if (is_callable($resolver)) {
                try {
                    $fieldValue = call_user_func($resolver, $source, []); // Empty args for now
                } catch (GraphQLError $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    throw new GraphQLError("Resolver for field \"{$fieldName}\" threw an exception: " . $e->getMessage(), 0, $e);
                }
            } else {
                // Default resolver: look for property/method on source
                if (is_array($source) && array_key_exists($fieldName, $source)) {
                    $fieldValue = $source[$fieldName];
                } elseif (is_object($source) && property_exists($source, $fieldName)) {
                    $fieldValue = $source->$fieldName;
                } elseif (is_object($source) && method_exists($source, $fieldName)) {
                    $fieldValue = call_user_func([$source, $fieldName]);
                }
            }

            // If the field type is an object and there are nested selections, recurse
            if ($fieldType->getKind() === Type::KIND_OBJECT && !empty($nestedSelectionSet)) {
                // If the resolved value for the object field is null, and it's non-nullable, coerceValue will error.
                // If nullable, return null directly, no need to recurse into null.
                if ($fieldValue === null) {
                    $result[$fieldName] = null;
                } else {
                    // Recursively resolve selections for the nested object
                    $result[$fieldName] = $this->resolveSelections($nestedSelectionSet, $fieldType, $fieldValue);
                }
            } elseif ($fieldType->getKind() === Type::KIND_LIST && $fieldType->getOfType()->getKind() === Type::KIND_OBJECT) {
                // Handle list of objects
                $coercedList = [];
                if (is_array($fieldValue) || ($fieldValue instanceof \Traversable)) {
                    foreach ($fieldValue as $item) {
                        // For each item in the list, resolve its selections
                        if ($item === null) {
                            $coercedList[] = null;
                        } else {
                            $coercedList[] = $this->resolveSelections($nestedSelectionSet, $fieldType->getOfType(), $item);
                        }
                    }
                } elseif ($fieldValue !== null) {
                    // If it's a list type but the value isn't iterable (and not null)
                    throw new GraphQLError("Value is not iterable for List type " . $fieldType->getName() . ": " . var_export($fieldValue, true));
                }
                $result[$fieldName] = $coercedList;
            } else {
                // For scalar types, non-object lists, or objects without nested selections (though parser should always give an empty array for objects)
                // Just coerce the simple value
                $result[$fieldName] = $this->coerceValue($fieldValue, $fieldType);
            }
        }
        return $result;
    }


    /**
     * Coerces a value to the specified GraphQL type.
     * This is a very simplified coercion. For object types, it no longer recurses
     * into selections because `resolveSelections` now handles that.
     * @param mixed $value
     * @param Type $type
     * @return mixed
     * @throws GraphQLError
     */
    protected function coerceValue(mixed $value, Type $type): mixed
    {
        switch ($type->getKind()) {
            case Type::KIND_NON_NULL:
                $coercedValue = $this->coerceValue($value, $type->getOfType());
                if ($coercedValue === null) {
                    throw new GraphQLError("Cannot return null for non-nullable type " . $type->getName() . ".");
                }
                return $coercedValue;
            case Type::KIND_LIST:
                $itemType = $type->getOfType();
                if ($value === null) {
                    return null; // A nullable list can be null
                }
                // Ensure value is iterable. If not, treat as a single item list.
                if (!is_array($value) && !($value instanceof \Traversable)) {
                    $value = [$value]; // Wrap non-array/traversable in an array
                }
                $coercedList = [];
                foreach ($value as $item) {
                    $coercedList[] = $this->coerceValue($item, $itemType);
                }
                return $coercedList;
            case Type::KIND_SCALAR:
                switch ($type->getName()) {
                    case Type::STRING:
                    case Type::ID:
                        if ($value === null) {
                            return null;
                        }
                        return (string) $value;
                    case Type::INT:
                        if ($value === null) {
                            return null;
                        }
                        if (!is_numeric($value) || str_contains((string)$value, '.') || (string)(int)$value !== (string)$value) {
                            throw new GraphQLError("Value is not a valid Int: " . var_export($value, true));
                        }
                        return (int) $value;
                    case Type::BOOLEAN:
                        if ($value === null) {
                            return null;
                        }
                        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$value;
                    case Type::FLOAT:
                        if ($value === null) {
                            return null;
                        }
                        if (!is_numeric($value)) {
                            throw new GraphQLError("Value is not a valid Float: " . var_export($value, true));
                        }
                        return (float) $value;
                    default:
                        throw new GraphQLError("Unknown scalar type: " . $type->getName());
                }
            case Type::KIND_OBJECT:
                // If value is null, return null (handled by non-null check if applicable)
                if ($value === null) {
                    return null;
                }
                // When coerceValue is called for KIND_OBJECT, it's typically for a nested object
                // whose sub-fields have *already been resolved and selected* by resolveSelections.
                // So, we just return the value as is (assuming it's already the filtered object).
                // Or if it's a simple object with no sub-selections.
                if (is_array($value) || is_object($value)) {
                    return (array) $value; // Ensure it's an array for consistent output
                }
                // If it's an object type but the resolved value isn't an array or object, it's an error.
                throw new GraphQLError("Value cannot be coerced to object type " . $type->getName() . ": " . var_export($value, true));
            default:
                throw new GraphQLError("Unsupported type kind for coercion: " . $type->getKind());
        }
    }
}