<?php
namespace masoud4\GraphQL;

use masoud4\GraphQL\Error\GraphQLError;

class Schema
{
    protected Type $queryType;
    protected ?Type $mutationType = null;
    protected array $typeMap = []; // Map of type names to Type objects

    /**
     * @param Type $queryType The root Query type for the schema.
     * @param Type|null $mutationType Optional root Mutation type for the schema.
     */
    public function __construct(Type $queryType, ?Type $mutationType = null)
    {
        if ($queryType->getKind() !== Type::KIND_OBJECT) {
            throw new GraphQLError("Query type must be an ObjectType.");
        }
        if ($mutationType && $mutationType->getKind() !== Type::KIND_OBJECT) {
            throw new GraphQLError("Mutation type must be an ObjectType.");
        }

        $this->queryType = $queryType;
        $this->mutationType = $mutationType;

        // Automatically register all scalar types
        $this->registerType(Type::scalar(Type::STRING));
        $this->registerType(Type::scalar(Type::INT));
        $this->registerType(Type::scalar(Type::BOOLEAN));
        $this->registerType(Type::scalar(Type::FLOAT));
        $this->registerType(Type::scalar(Type::ID));

        // Register root types and their nested types
        $this->registerType($queryType);
        if ($mutationType) {
            $this->registerType($mutationType);
        }
    }

    /**
     * Recursively registers a type and its nested types in the type map.
     * @param Type $type
     * @return void
     */
    protected function registerType(Type $type): void
    {
        if (isset($this->typeMap[$type->getName()])) {
            return; // Already registered
        }

        $this->typeMap[$type->getName()] = $type;

        switch ($type->getKind()) {
            case Type::KIND_OBJECT:
                foreach ($type->getFields() as $fieldDefinition) {
                    if ($fieldDefinition['type'] instanceof Type) {
                        $this->registerType($fieldDefinition['type']);
                    }
                }
                break;
            case Type::KIND_LIST:
            case Type::KIND_NON_NULL:
                $this->registerType($type->getOfType());
                break;
            // Scalar types have no nested types to register
        }
    }

    /**
     * Get the root Query type.
     * @return Type
     */
    public function getQueryType(): Type
    {
        return $this->queryType;
    }

    /**
     * Get the root Mutation type.
     * @return Type|null
     */
    public function getMutationType(): ?Type
    {
        return $this->mutationType;
    }

    /**
     * Get a type by its name from the schema's type map.
     * @param string $name
     * @return Type|null
     */
    public function getType(string $name): ?Type
    {
        return $this->typeMap[$name] ?? null;
    }
}
