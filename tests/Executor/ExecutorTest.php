<?php
// tests/Executor/ExecutorTest.php
namespace Tests\Executor;

use PHPUnit\Framework\TestCase;
use masoud4\GraphQL\Schema;
use masoud4\GraphQL\Type;
use masoud4\GraphQL\Executor\Executor;
use masoud4\GraphQL\Parser\QueryParser;
use masoud4\GraphQL\Error\GraphQLError;
use masoud4\GraphQL\Query;
use masoud4\GraphQL\Mutation;

// --- Define Test Types and Schema for the ExecutorTest ---
// In a real application, these would be in your 'App' namespace,
// but for a self-contained test, we define them here.

/**
 * A simple User object type for testing.
 */
class TestUserType extends Type
{
    public function __construct()
    {
        parent::__construct('User', Type::KIND_OBJECT);
        $this->description = "A test user object.";
        $this->fields = [
            'id' => Type::field(Type::nonNull(Type::scalar(Type::ID)), 'The user ID.'),
            'name' => Type::field(Type::scalar(Type::STRING), 'The user name.'),
            'email' => Type::field(Type::nonNull(Type::scalar(Type::STRING)), 'The user email.'),
            'age' => Type::field(Type::scalar(Type::INT), 'The user age.', [], function($user, $args) {
                // Custom resolver for age to demonstrate resolver execution
                return $user['age'] ?? null;
            }),
            'status' => Type::field(Type::scalar(Type::STRING), 'The user status.'),
            'isActive' => Type::field(Type::scalar(Type::BOOLEAN), 'Whether the user account is active.'),
        ];
    }
}

/**
 * A simple Product object type for testing.
 */
class TestProductType extends Type
{
    public function __construct()
    {
        parent::__construct('Product', Type::KIND_OBJECT);
        $this->description = "A test product object.";
        $this->fields = [
            'id' => Type::field(Type::nonNull(Type::scalar(Type::ID)), 'The product ID.'),
            'name' => Type::field(Type::scalar(Type::STRING), 'The product name.'),
            'price' => Type::field(Type::scalar(Type::FLOAT), 'The product price.'),
        ];
    }
}


/**
 * The root Query type for testing.
 */
class TestQueryType extends Query
{
    public function __construct()
    {
        parent::__construct();
        $this->fields = [
            'hello' => Type::field(Type::scalar(Type::STRING), 'A simple greeting.', [], function() {
                return 'World';
            }),
            'user' => Type::field(new TestUserType(), 'Fetches a single user.', [], function($rootValue, $args) {
                // Simulate a data source
                $users = [
                    '1' => ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30, 'status' => 'active', 'isActive' => true],
                    '2' => ['id' => '2', 'name' => 'Bob', 'email' => 'bob@example.com', 'age' => 25, 'status' => 'inactive', 'isActive' => false],
                    '3' => ['id' => '3', 'name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35, 'status' => 'active', 'isActive' => true],
                ];
                // If a user is provided in rootValue, use that. Otherwise, default to Alice.
                // This makes the test more robust for testing rootValue.
                if (isset($rootValue['user'])) {
                    return $rootValue['user'];
                }
                // In a real scenario, $args['id'] would be used if parser supported it
                return $users['1'] ?? null; // Always return Alice for simplicity if no rootValue user
            }),
            'users' => Type::field(Type::listOf(new TestUserType()), 'Fetches a list of users.', [], function() {
                return [
                    ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30, 'status' => 'active', 'isActive' => true],
                    ['id' => '2', 'name' => 'Bob', 'email' => 'bob@example.com', 'age' => 25, 'status' => 'inactive', 'isActive' => false],
                ];
            }),
            'product' => Type::field(new TestProductType(), 'Fetches a single product.', [], function() {
                return ['id' => 'P1', 'name' => 'Laptop', 'price' => 1200.50];
            }),
            'nullableString' => Type::field(Type::scalar(Type::STRING), 'A nullable string.', [], function() {
                return null;
            }),
            'nonNullableString' => Type::field(Type::nonNull(Type::scalar(Type::STRING)), 'A non-nullable string.', [], function() {
                return 'I am not null';
            }),
            'nonNullableStringNullResolver' => Type::field(Type::nonNull(Type::scalar(Type::STRING)), 'A non-nullable string that resolves to null.', [], function() {
                return null; // This will cause an error
            }),
            'listOfString' => Type::field(Type::listOf(Type::scalar(Type::STRING)), 'A list of strings.', [], function() {
                return ['apple', 'banana', 'cherry'];
            }),
            'listOfNonNullString' => Type::field(Type::listOf(Type::nonNull(Type::scalar(Type::STRING))), 'A list of non-nullable strings.', [], function() {
                return ['one', 'two', 'three'];
            }),
            'listOfNonNullStringWithNull' => Type::field(Type::listOf(Type::nonNull(Type::scalar(Type::STRING))), 'A list of non-nullable strings with a null item.', [], function() {
                return ['valid', null, 'another_valid']; // This should cause an error
            }),
            // Add the errorField directly to the TestQueryType for consistent schema definition
            'errorField' => Type::field(Type::scalar(Type::STRING), 'A field that throws an error.', [], function() {
                throw new \Exception("Something went wrong in the resolver!");
            }),
        ];
    }
}

/**
 * The root Mutation type for testing.
 */
class TestMutationType extends Mutation
{
    public function __construct()
    {
        parent::__construct();
        $this->fields = [
            'createUser' => Type::field(new TestUserType(), 'Creates a new user.', [], function($rootValue, $args) {
                // Simulate creating a user
                return ['id' => 'new-123', 'name' => 'New User', 'email' => 'new@example.com', 'age' => 22, 'status' => 'active', 'isActive' => true];
            }),
            'updateUserStatus' => Type::field(Type::nonNull(Type::scalar(Type::BOOLEAN)), 'Updates user status.', [], function($rootValue, $args) {
                // Simulate updating status
                return true;
            }),
        ];
    }
}


class ExecutorTest extends TestCase
{
    protected Schema $schema;
    protected Executor $executor;
    protected QueryParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        // Initialize the schema with our test types
        $this->schema = new Schema(new TestQueryType(), new TestMutationType());
        $this->executor = new Executor($this->schema);
        $this->parser = new QueryParser(); // Only used for parsing, not passed to Executor
    }

    /**
     * Helper to execute a query string and return the data or throw an error.
     * @param string $queryString
     * @param array $rootValue
     * @return array
     * @throws GraphQLError
     */
    private function executeQuery(string $queryString, array $rootValue = []): array
    {
        $parsedQuery = $this->parser->parse($queryString);
        return $this->executor->execute($parsedQuery, $rootValue);
    }

    public function testExecuteBasicScalarQuery(): void
    {
        $queryString = '{ hello }';
        $result = $this->executeQuery($queryString);
        $this->assertEquals(['hello' => 'World'], $result);
    }

    public function testExecuteQueryWithObjectType(): void
    {
        $queryString = '{ user { id name email } }';
        $result = $this->executeQuery($queryString);
        // The executor now returns only the explicitly requested fields for object types.
        $this->assertEquals(['user' => ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com']], $result);
    }

    public function testExecuteQueryWithPartialObjectTypeFields(): void
    {
        $queryString = '{ user { name } }';
        $result = $this->executeQuery($queryString);
        $this->assertEquals(['user' => ['name' => 'Alice']], $result);
    }

    public function testExecuteQueryWithMultipleFields(): void
    {
        $queryString = '{ hello user { name email } }';
        $result = $this->executeQuery($queryString);
        $this->assertEquals([
            'hello' => 'World',
            'user' => ['name' => 'Alice', 'email' => 'alice@example.com']
        ], $result);
    }

    public function testExecuteQueryWithListType(): void
    {
        $queryString = '{ users { id name } }';
        $result = $this->executeQuery($queryString);
        $this->assertEquals([
            'users' => [
                ['id' => '1', 'name' => 'Alice'],
                ['id' => '2', 'name' => 'Bob'],
            ]
        ], $result);
    }

    public function testExecuteMutation(): void
    {
        $queryString = 'mutation { createUser { id name email } }';
        $result = $this->executeQuery($queryString);
        $this->assertArrayHasKey('createUser', $result);
        $this->assertArrayHasKey('id', $result['createUser']);
        $this->assertArrayHasKey('name', $result['createUser']);
        $this->assertArrayHasKey('email', $result['createUser']);
        $this->assertEquals('New User', $result['createUser']['name']);
        $this->assertEquals('new@example.com', $result['createUser']['email']);
    }

    public function testExecuteMutationWithScalarReturn(): void
    {
        $queryString = 'mutation { updateUserStatus }';
        $result = $this->executeQuery($queryString);
        $this->assertEquals(['updateUserStatus' => true], $result);
    }

    public function testExecuteQueryWithCustomResolver(): void
    {
        $queryString = '{ user { age } }';
        $result = $this->executeQuery($queryString);
        $this->assertEquals(['user' => ['age' => 30]], $result);
    }

    public function testExecuteQueryWithNonNullableFieldReturningValue(): void
    {
        $queryString = '{ nonNullableString }';
        $result = $this->executeQuery($queryString);
        $this->assertEquals(['nonNullableString' => 'I am not null'], $result);
    }

    public function testExecuteQueryWithNonNullableFieldReturningNullThrowsError(): void
    {
        $queryString = '{ nonNullableStringNullResolver }';
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('Cannot return null for non-nullable type String!.');
        $this->executeQuery($queryString);
    }

    public function testExecuteQueryWithNonExistentFieldThrowsError(): void
    {
        $queryString = '{ nonExistentField }';
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('Cannot query field "nonExistentField" on type "Query".');
        $this->executeQuery($queryString);
    }

    public function testExecuteQueryWithNestedNonExistentFieldThrowsError(): void
    {
        // This test should throw an error because 'invalidField' does not exist in the TestUserType schema.
        $queryString = '{ user { id invalidField } }';
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('Cannot query field "invalidField" on type "User".'); // The type is "User", not "Query"
        $this->executeQuery($queryString);
    }


    public function testExecuteQueryWithRootValueArray(): void
    {
        $queryString = '{ user { name } }';
        $rootValue = [
            'user' => ['id' => '3', 'name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35, 'isActive' => true]
        ];
        $result = $this->executeQuery($queryString, $rootValue);
        $this->assertEquals(['user' => ['name' => 'Charlie']], $result);
    }

    public function testCoerceValueString(): void
    {
        $queryString = '{ nullableString }'; // Test with a nullable string
        $result = $this->executeQuery($queryString);
        $this->assertNull($result['nullableString']);

        $queryString = '{ nonNullableString }'; // Test with a non-nullable string
        $result = $this->executeQuery($queryString);
        $this->assertIsString($result['nonNullableString']);
    }

    public function testCoerceValueInt(): void
    {
        $queryString = '{ user { age } }';
        $result = $this->executeQuery($queryString);
        $this->assertIsInt($result['user']['age']);
    }

    public function testCoerceValueFloat(): void
    {
        $queryString = '{ product { price } }';
        $result = $this->executeQuery($queryString);
        $this->assertIsFloat($result['product']['price']);
    }

    public function testCoerceValueBoolean(): void
    {
        $queryString = '{ user { isActive } }';
        $result = $this->executeQuery($queryString);
        $this->assertIsBool($result['user']['isActive']);
        $this->assertTrue($result['user']['isActive']);
    }

    public function testCoerceValueListString(): void
    {
        $queryString = '{ listOfString }';
        $result = $this->executeQuery($queryString);
        $this->assertEquals(['apple', 'banana', 'cherry'], $result['listOfString']);
        $this->assertIsArray($result['listOfString']);
        $this->assertContainsOnly('string', $result['listOfString']);
    }

    public function testCoerceValueListOfNonNullString(): void
    {
        $queryString = '{ listOfNonNullString }';
        $result = $this->executeQuery($queryString);
        $this->assertEquals(['one', 'two', 'three'], $result['listOfNonNullString']);
        $this->assertIsArray($result['listOfNonNullString']);
        $this->assertContainsOnly('string', $result['listOfNonNullString']);
    }

    public function testCoerceValueListOfNonNullStringWithNullThrowsError(): void
    {
        $queryString = '{ listOfNonNullStringWithNull }';
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('Cannot return null for non-nullable type String!.');
        $this->executeQuery($queryString);
    }

    public function testExecutorHandlesResolverThrowingGeneralException(): void
    {
        // The errorField is now defined directly in TestQueryType's constructor,
        // so it's part of the schema from the start.
        $queryString = '{ errorField }';
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('Resolver for field "errorField" threw an exception: Something went wrong in the resolver!');
        $this->executeQuery($queryString);
    }
}
