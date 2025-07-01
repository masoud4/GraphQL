<?php
namespace masoud4\GraphQL;

abstract class Mutation extends Type
{
    public function __construct()
    {
        parent::__construct('Mutation', Type::KIND_OBJECT);
        $this->description = "The root Mutation type of the schema.";
        // Child classes will define $this->fields in their constructor
    }
}
