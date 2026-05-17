<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Traits\Http;

use AndyDefer\BestPractices\Data\DataInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides HTTP response helper methods for controllers and actions.
 *
 * This trait offers a consistent interface for generating various HTTP responses
 * including JSON API responses, file downloads, streaming responses, and Inertia views.
 * All methods are designed to be used directly within controller classes or actions.
 *
 * @author Andy Defer
 */
trait SendsHttpResponses
{
    /**
     * Creates a JSON response for API endpoints.
     *
     * Automatically converts DataInterface objects to arrays using their toArray() method.
     *
     * @param  DataInterface  $data  The data to return
     * @param  int  $code  HTTP status code (200, 201, 202, etc.)
     * @return JsonResponse JSON formatted HTTP response
     */
    public function json(DataInterface $data, int $code = 200): JsonResponse
    {
        return response()->json($data->toArray(), $code);
    }

    /**
     * Creates a redirect response to another URL.
     *
     * @param  string  $url  Destination URL
     * @param  int  $code  HTTP redirect status code (301, 302, 303, 307, 308)
     * @return RedirectResponse HTTP redirect response
     */
    public function redirect(string $url, int $code = 302): RedirectResponse
    {
        return redirect($url, $code);
    }

    /**
     * Creates a streaming response for real-time data transmission.
     *
     * Useful for streaming large files, real-time CSV generation, or video streaming.
     *
     * @param  callable  $callback  Function that writes output directly to the response stream
     * @param  string  $contentType  MIME type of the streamed content
     * @param  int  $code  HTTP status code
     * @return StreamedResponse Streamed HTTP response
     */
    public function stream(callable $callback, string $contentType = 'application/octet-stream', int $code = 200): StreamedResponse
    {
        return response()->stream($callback, $code, [
            'Content-Type' => $contentType,
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Creates a Server-Sent Events (SSE) streaming response.
     *
     * SSE allows servers to push real-time events to clients over a single HTTP connection.
     * Useful for live notifications, real-time dashboards, or progress updates.
     *
     * @param  callable  $callback  Function that emits SSE events using the SSE format
     * @return StreamedResponse SSE streaming response with proper headers
     */
    public function sse(callable $callback): StreamedResponse
    {
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Creates a 204 No Content response.
     *
     * Used when the request was successful but there's no content to return.
     *
     * @return Response Empty HTTP response with 204 status code
     */
    public function noContent(): Response
    {
        return response('', 204);
    }

    /**
     * Creates an Inertia.js response for modern single-page applications.
     *
     * Renders a React/Vue component with server-side data when using Inertia.js.
     *
     * @param  string  $component  Name of the React/Vue component to render
     * @return InertiaResponse Inertia response that renders the specified component
     */
    public function inertia(string $component): InertiaResponse
    {
        return Inertia::render($component);
    }

    /**
     * Creates a raw HTML response.
     *
     * Use this only for rare cases where Inertia.js is not suitable,
     * such as email previews, legacy views, or external integrations.
     *
     * @param  string  $html  Raw HTML content to return
     * @param  int  $code  HTTP status code
     * @return Response HTML response with proper content type header
     */
    public function html(string $html, int $code = 200): Response
    {
        return response($html, $code, [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * Returns a file to be displayed inline in the browser.
     *
     * The browser will attempt to display the file (PDF, image, video) directly
     * rather than downloading it.
     *
     * @param  string  $filePath  Absolute or relative path to the file
     * @param  string|null  $fileName  Optional custom filename for inline display
     * @return BinaryFileResponse File response with inline disposition
     */
    public function fileInline(string $filePath, ?string $fileName = null): BinaryFileResponse
    {
        $fileName = $fileName ?? basename($filePath);

        return response()->file($filePath, [
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Forces a file to be downloaded by the browser.
     *
     * The browser will save the file to disk rather than displaying it.
     *
     * @param  string  $filePath  Absolute or relative path to the file
     * @param  string|null  $fileName  Optional custom filename for the downloaded file
     * @return BinaryFileResponse File response with attachment disposition
     */
    public function fileDownload(string $filePath, ?string $fileName = null): BinaryFileResponse
    {
        $fileName = $fileName ?? basename($filePath);

        return response()->download($filePath, $fileName);
    }

    /**
     * Creates a plain text response.
     *
     * Useful for API endpoints that return raw text, logs, or configuration files.
     *
     * @param  string  $content  Text content to return
     * @param  int  $code  HTTP status code
     * @return Response Plain text response with proper content type
     */
    public function text(string $content, int $code = 200): Response
    {
        return response($content, $code, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
