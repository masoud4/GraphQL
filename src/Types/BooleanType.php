<?php
namespace masoud4\GraphQL\Types;

use masoud4\GraphQL\Type;

class BooleanType extends Type
{
    public function __construct()
    {
        parent::__construct(Type::BOOLEAN, Type::KIND_SCALAR);
        $this->description = "The `Boolean` scalar type represents `true` or `false`.";
    }
}