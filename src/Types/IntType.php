<?php
namespace masoud4\GraphQL\Types;

use masoud4\GraphQL\Type;

class IntType extends Type
{
    public function __construct()
    {
        parent::__construct(Type::INT, Type::KIND_SCALAR);
        $this->description = "The `Int` scalar type represents a signed 32‐bit integer. It's often used for unique identifiers or counts.";
    }
}