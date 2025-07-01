<?php
// src/Parser/QueryParser.php
namespace masoud4\GraphQL\Parser;

use masoud4\GraphQL\Error\GraphQLError;

/**
 * A VERY basic GraphQL query parser.
 * This parser supports:
 * - Top-level queries and mutations.
 * - Basic field selection (e.g., { field1 field2 }).
 * - Basic nested field selection (e.g., { user { id name } }).
 * - No arguments, fragments, directives, or aliases.
 *
 * It returns a simplified array structure.
 */
class QueryParser
{
    /**
     * Parses a GraphQL query string into a simplified array structure.
     * @param string $queryString The GraphQL query string.
     * @return array A structured array representing the parsed query.
     * @throws GraphQLError If the query string is malformed or unsupported.
     */
    public function parse(string $queryString): array
    {
        // Trim whitespace and remove comments (lines starting with #)
        $queryString = preg_replace('/\s*#.*$/m', '', $queryString);
        $queryString = trim($queryString);

        if (empty($queryString)) {
            throw new GraphQLError("Empty query string.");
        }

        // Determine operation type (query or mutation). Default to query if not specified.
        $operationType = 'query';
        $queryBody = $queryString;

        if (preg_match('/^(query|mutation)\s*{/i', $queryString, $matches)) {
            $operationType = strtolower($matches[1]);
            // Remove the operation type keyword and the first '{'
            $queryBody = trim(substr($queryString, strlen($matches[0]) - 1));
        } elseif (str_starts_with($queryString, '{')) {
            // Implicit query, just strip the outer braces
            $queryBody = trim($queryString);
        } else {
            throw new GraphQLError("Unsupported query format. Expected 'query {' or 'mutation {' or '{'.");
        }

        // Basic check for balanced braces (not robust for complex queries)
        $openBraces = substr_count($queryBody, '{');
        $closeBraces = substr_count($queryBody, '}');
        if ($openBraces !== $closeBraces) {
            throw new GraphQLError("Mismatched curly braces in query.");
        }

        // Parse fields recursively
        $fields = $this->parseFields($queryBody);

        return [
            'operationType' => $operationType,
            'fields' => $fields,
        ];
    }

    /**
     * Recursively parses fields from a query body string.
     * @param string $bodyString The part of the query string containing fields (e.g., "{ id name { firstName } }").
     * @return array A structured array of fields, where sub-fields are nested.
     * Example: ['id' => [], 'name' => [], 'user' => ['id' => [], 'email' => []]]
     * @throws GraphQLError
     */
    private function parseFields(string $bodyString): array
    {
        $fields = [];
        $bodyString = trim($bodyString);

        // Remove outer braces if they exist (e.g., "{ field1 field2 }")
        if (str_starts_with($bodyString, '{') && str_ends_with($bodyString, '}')) {
            $bodyString = substr($bodyString, 1, -1);
            $bodyString = trim($bodyString);
        }

        if (empty($bodyString)) {
            return [];
        }

        // Use a simple regex to split fields, handling nested braces
        // This regex attempts to find field names optionally followed by a nested selection set.
        // It's still basic and doesn't handle arguments or aliases robustly.
        preg_match_all('/(\w+)\s*({[^}]*}|(?=\s*\w+|$))/s', $bodyString, $matches, PREG_SET_ORDER);

        $offset = 0;
        $len = strlen($bodyString);

        while ($offset < $len) {
            // Find the next field name
            if (preg_match('/\G\s*(\w+)\s*/A', $bodyString, $fieldMatch, 0, $offset)) {
                $fieldName = $fieldMatch[1];
                $offset += strlen($fieldMatch[0]);

                // Check for nested selection set
                if (preg_match('/\G\s*{/A', $bodyString, $braceMatch, 0, $offset)) {
                    // Found an opening brace, now find its matching closing brace
                    $braceCount = 1;
                    $startBrace = $offset;
                    $offset += strlen($braceMatch[0]);

                    while ($offset < $len && $braceCount > 0) {
                        if ($bodyString[$offset] === '{') {
                            $braceCount++;
                        } elseif ($bodyString[$offset] === '}') {
                            $braceCount--;
                        }
                        $offset++;
                    }

                    if ($braceCount !== 0) {
                        throw new GraphQLError("Unbalanced braces in field '{$fieldName}'.");
                    }
                    $nestedBody = substr($bodyString, $startBrace, $offset - $startBrace);
                    $fields[$fieldName] = $this->parseFields($nestedBody);
                } else {
                    // Scalar field or field with no selection set (end of current level)
                    $fields[$fieldName] = [];
                }
            } else {
                // If we can't find a field name, it's a parsing error
                throw new GraphQLError("Unexpected token near: " . substr($bodyString, $offset, 20) . "...");
            }
        }

        return $fields;
    }
}