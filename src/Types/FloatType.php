<?php
namespace masoud4\GraphQL\Types;

use masoud4\GraphQL\Type;

class FloatType extends Type
{
    public function __construct()
    {
        parent::__construct(Type::FLOAT, Type::KIND_SCALAR);
        $this->description = "The `Float` scalar type represents a signed double-precision fractional value.";
    }
}