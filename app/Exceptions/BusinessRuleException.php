<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/** Domain rule violation rendered as `{message, errors, code}` JSON (422 by default). */
class BusinessRuleException extends Exception
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        private readonly int $status = 422,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->errors === [] ? ['_' => [$this->getMessage()]] : $this->errors,
            'code' => $this->errorCode,
        ], $this->status);
    }
}
