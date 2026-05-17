<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records;

/**
 * Empty record for optional filter parameters.
 *
 * This record is used when a Service or Repository needs to accept a Record
 * parameter but no actual data is required. It provides a type-safe way to
 * handle optional filtering without using null or empty arrays.
 *
 * **Usage:**
 * ```php
 * // In a Service method that requires a Record parameter
 * public function getUsers(UserCriteriaRecord $record): Collection
 * {
 *     // When no criteria are needed, pass an EmptyRecord
 * }
 *
 * // Calling the method with no filters
 * $users = $userService->getUsers(new EmptyRecord());
 * ```
 *
 * @author Andy Defer
 */
final class EmptyRecord extends AbstractRecord
{
    public function __construct() {}
}
