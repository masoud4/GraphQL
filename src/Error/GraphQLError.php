<?php
namespace masoud4\GraphQL\Error;

use Exception;

class GraphQLError extends Exception
{
    /**
     * @var array Additional error details, e.g., 'locations', 'path'.
     */
    protected array $extensions = [];

    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null, array $extensions = [])
    {
        parent::__construct($message, $code, $previous);
        $this->extensions = $extensions;
    }

    /**
     * Get additional error extensions.
     * @return array
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Convert the error to a standard GraphQL error format.
     * @return array
     */
    public function toArray(): array
    {
        $error = [
            'message' => $this->getMessage(),
        ];

        if (!empty($this->extensions)) {
            $error['extensions'] = $this->extensions;
        }

        // Add file and line in development/debug mode (optional)
        if (defined('APP_DEBUG') && APP_DEBUG) {
            $error['debug'] = [
                'file' => $this->getFile(),
                'line' => $this->getLine(),
                'trace' => explode("\n", $this->getTraceAsString()),
            ];
        }

        return $error;
    }
}
