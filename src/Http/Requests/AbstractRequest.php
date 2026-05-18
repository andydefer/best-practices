<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Http\Requests;

use AndyDefer\BestPractices\Records\Recordable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Abstract base class for all Form Requests.
 *
 * Extends Laravel's FormRequest to enforce the toRecord() method contract.
 * Every Form Request must implement toRecord() to transform validated data
 * into a Record object that can be passed to Actions.
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Http\Requests
 */
abstract class AbstractRequest extends FormRequest
{
    /**
     * Transform the validated request into a Record object.
     *
     * This method creates a Record containing ALL the data needed by the Action:
     * - URL parameters (route parameters)
     * - Query string parameters
     * - Request body data
     * - Authenticated user information
     * - Request metadata (IP, user agent, etc.)
     *
     * @return Recordable The Record object containing all request data
     */
    abstract public function toRecord(): Recordable;
}
