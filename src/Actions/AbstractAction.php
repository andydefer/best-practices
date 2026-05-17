<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Actions;

use AndyDefer\BestPractices\Traits\Http\SendsHttpResponses;

/**
 * Abstract base class for all Action classes.
 *
 * An Action encapsulates the logic for a single HTTP route. Each Action receives
 * URL parameters and a Form Request, orchestrates services/workers, and returns
 * a consistent HTTP response using the HasReplyer trait.
 *
 * **Important rules:**
 * - One Action = one HTTP route (never reuse the same Action for multiple routes)
 * - Must return a single, unique response type (no union types)
 * - Must receive a Form Request as the last parameter (except for routes without parameters)
 *
 * @example
 * // API Action returning JsonResponse
 * final class ListUsersAction extends AbstractAction
 * {
 *     public function run(ListUsersRequest $request): JsonResponse
 *     {
 *         $users = $this->userService->getAll();
 *         return $this->json($users);
 *     }
 * }
 * @example
 * // Web Action returning InertiaResponse
 * final class ShowDashboardAction extends AbstractAction
 * {
 *     public function run(ShowDashboardRequest $request): InertiaResponse
 *     {
 *         return $this->inertia('Dashboard/Index');
 *     }
 * }
 *
 * @author Andy Defer
 */
abstract class AbstractAction
{
    use SendsHttpResponses;

    /**
     * Executes the action logic for a specific HTTP route.
     *
     * This method must be implemented by each concrete Action class.
     * The method signature should include:
     * - URL parameters in the order they appear in the route
     * - A Form Request as the last parameter (when validation is needed)
     *
     * **Return type constraint:** The concrete Action must declare a single,
     * unique return type (e.g., JsonResponse|InertiaResponse|RedirectResponse).
     * Union types are forbidden in concrete implementations.
     *
     * @param  mixed  ...$parameters  URL parameters and Form Request (in that order)
     * @return mixed The HTTP response (concrete type varies by Action)
     */
    abstract public function run(...$parameters): mixed;
}
