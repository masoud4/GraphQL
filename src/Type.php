<?php
namespace masoud4\GraphQL;

use Closure;
use masoud4\GraphQL\Error\GraphQLError;

abstract class Type
{
    // Scalar types
    public const STRING = 'String';
    public const INT = 'Int';
    public const BOOLEAN = 'Boolean';
    public const FLOAT = 'Float';
    public const ID = 'ID';

    // Type Kind (for internal representation)
    public const KIND_SCALAR = 'SCALAR';
    public const KIND_OBJECT = 'OBJECT';
    public const KIND_LIST = 'LIST';
    public const KIND_NON_NULL = 'NON_NULL';

    protected string $name;
    protected string $kind;
    protected ?string $description = null;

    // For ObjectType
    protected array $fields = []; // ['fieldName' => ['type' => Type::STRING, 'description' => '...', 'args' => [], 'resolve' => callable]]

    // For ListType and NonNullType
    protected ?Type $ofType = null;

    protected function __construct(string $name, string $kind)
    {
        $this->name = $name;
        $this->kind = $kind;
    }

    /**
     * Creates a new ObjectType.
     * @param string $name
     * @param array $fields Array of field definitions.
     * @param string|null $description
     * @return Type
     */
    public static function object(string $name, array $fields, ?string $description = null): Type
    {
        $type = new class($name, self::KIND_OBJECT) extends Type {
            public function __construct(string $name, string $kind) {
                parent::__construct($name, $kind);
            }
            public function getFields(): array { return $this->fields; }
            public function getField(string $name): ?array { return $this->fields[$name] ?? null; }
        };
        $type->fields = $fields;
        $type->description = $description;
        return $type;
    }

    /**
     * Creates a ListType.
     * @param Type $ofType The type of elements in the list.
     * @return Type
     */
    public static function listOf(Type $ofType): Type
    {
        $type = new class('List<' . $ofType->getName() . '>', self::KIND_LIST) extends Type {
            public function __construct(string $name, string $kind) {
                parent::__construct($name, $kind);
            }
            public function getOfType(): Type { return $this->ofType; }
        };
        $type->ofType = $ofType;
        return $type;
    }

    /**
     * Creates a NonNullType.
     * @param Type $ofType The underlying type that cannot be null.
     * @return Type
     */
    public static function nonNull(Type $ofType): Type
    {
        if ($ofType->getKind() === self::KIND_NON_NULL) {
            // Already non-null, return original
            return $ofType;
        }
        $type = new class($ofType->getName() . '!', self::KIND_NON_NULL) extends Type {
            public function __construct(string $name, string $kind) {
                parent::__construct($name, $kind);
            }
            public function getOfType(): Type { return $this->ofType; }
        };
        $type->ofType = $ofType;
        return $type;
    }

    /**
     * Factory method to get a scalar type instance.
     * @param string $scalarName (e.g., 'String', 'Int')
     * @return Type
     * @throws GraphQLError
     */
    public static function scalar(string $scalarName): Type
    {
        $className = __NAMESPACE__ . '\\Types\\' . ucfirst($scalarName) . 'Type';
        if (!class_exists($className)) {
            throw new GraphQLError("Unknown scalar type: {$scalarName}");
        }
        return new $className();
    }

    /**
     * Get the name of the type.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the kind of the type (SCALAR, OBJECT, LIST, NON_NULL).
     * @return string
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Get the description of the type.
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the fields of an ObjectType.
     * @return array
     * @throws GraphQLError If called on a non-object type.
     */
    public function getFields(): array
    {
        if ($this->kind !== self::KIND_OBJECT) {
            throw new GraphQLError("Cannot get fields from a non-object type ({$this->name}).");
        }
        return $this->fields;
    }

    /**
     * Get a specific field definition from an ObjectType.
     * @param string $name
     * @return array|null
     * @throws GraphQLError If called on a non-object type.
     */
    public function getField(string $name): ?array
    {
        if ($this->kind !== self::KIND_OBJECT) {
            throw new GraphQLError("Cannot get field '{$name}' from a non-object type ({$this->name}).");
        }
        return $this->fields[$name] ?? null;
    }

    /**
     * Get the underlying type for LIST or NON_NULL types.
     * @return Type
     * @throws GraphQLError If called on a non-LIST/NON_NULL type.
     */
    public function getOfType(): Type
    {
        if ($this->kind !== self::KIND_LIST && $this->kind !== self::KIND_NON_NULL) {
            throw new GraphQLError("Cannot get 'ofType' from a non-LIST or non-NON_NULL type ({$this->name}).");
        }
        return $this->ofType;
    }

    /**
     * Helper to define a field.
     * @param Type $type The field's type.
     * @param string|null $description
     * @param array $args Field arguments definition.
     * @param callable|null $resolve Resolver function for the field.
     * @return array
     */
    public static function field(Type $type, ?string $description = null, array $args = [], ?callable $resolve = null): array
    {
        return [
            'type' => $type,
            'description' => $description,
            'args' => $args,
            'resolve' => $resolve,
        ];
    }

    /**
     * Helper to define a field argument.
     * @param Type $type The argument's type.
     * @param string|null $description
     * @param mixed $defaultValue
     * @return array
     */
    public static function arg(Type $type, ?string $description = null, mixed $defaultValue = null): array
    {
        return [
            'type' => $type,
            'description' => $description,
            'defaultValue' => $defaultValue,
        ];
    }
}
