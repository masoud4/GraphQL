<?php
namespace masoud4\GraphQL;

abstract class Query extends Type
{
    public function __construct()
    {
        parent::__construct('Query', Type::KIND_OBJECT);
        $this->description = "The root Query type of the schema.";
        // Child classes will define $this->fields in their constructor
    }
}
