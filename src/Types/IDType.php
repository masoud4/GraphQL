<?php 
namespace masoud4\GraphQL\Types;

use masoud4\GraphQL\Type;

class IDType extends Type
{
    public function __construct()
    {
        parent::__construct(Type::ID, Type::KIND_SCALAR);
        $this->description = "The `ID` scalar type represents a unique identifier, often used to refetch an object or as key for a cache. The ID type is serialized in the same way as a String; however, it is not intended to be human‐readable.";
    }
}