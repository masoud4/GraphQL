<?php
namespace masoud4\GraphQL\Types;

use masoud4\GraphQL\Type;

class StringType extends Type
{
    public function __construct()
    {
        parent::__construct(Type::STRING, Type::KIND_SCALAR);
        $this->description = "The `String` scalar type represents textual data, represented as UTF-8 character sequences. The String type is most often used by GraphQL to represent free-form human-readable text.";
    }
}