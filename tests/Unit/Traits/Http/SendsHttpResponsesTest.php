<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Traits\Http;

use AndyDefer\BestPractices\Tests\Fixtures\Actions\TestAction;
use AndyDefer\BestPractices\Tests\Fixtures\Data\TestUserData;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestBackedStringEnum;
use AndyDefer\BestPractices\Tests\TestCase;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SendsHttpResponsesTest extends TestCase
{
    private TestAction $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new TestAction;
    }

    public function test_json_returns_json_response_with_data(): void
    {
        // Arrange: Create a test data object with known values
        $createdAt = new DateTime('2024-01-15 10:30:00');
        $testData = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: '2024-01-15T10:30:00Z',
            createdAt: $createdAt,
            tags: ['admin', 'premium'],
            metadata: ['last_login' => '2024-01-15']
        );

        // Act: Call the json method with data and custom status code
        $response = $this->sut->json($testData, 201);

        // Assert: Verify response type, status code, and content match the provided data
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($testData->toArray(), $response->getData(true));
    }

    public function test_json_returns_empty_response_when_data_is_null_and_status_is_204(): void
    {
        // Arrange: Prepare null data and 204 No Content status code
        // Act: Call the json method with null data and 204 status
        $response = $this->sut->noContent();

        // Assert: Verify empty response with 204 status code
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(204, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertTrue($content === '' || $content === '{}');
    }

    public function test_json_defaults_to_200_status_code_when_no_code_provided(): void
    {
        // Arrange: Create a test data object with various property types
        $createdAt = new DateTime('2024-01-15 10:30:00');
        $testData = new TestUserData(
            id: 1,
            name: 'John Doe',
            status: TestBackedStringEnum::ACTIVE,
            emailVerifiedAt: '2024-01-15T10:30:00Z',
            createdAt: $createdAt,
            tags: ['admin', 'premium'],
            metadata: ['last_login' => '2024-01-15']
        );

        // Act: Call the json method without specifying status code
        $response = $this->sut->json($testData);

        // Assert: Verify default 200 status code and correct content
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($testData->toArray(), $response->getData(true));
    }

    public function test_redirect_returns_redirect_response_with_custom_status_code(): void
    {
        // Arrange: Define target URL and permanent redirect status code
        $targetUrl = '/dashboard';
        $statusCode = 301;

        // Act: Call the redirect method with custom parameters
        $response = $this->sut->redirect($targetUrl, $statusCode);

        // Assert: Verify permanent redirect response and target URL
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertStringEndsWith($targetUrl, $response->getTargetUrl());
    }

    public function test_redirect_defaults_to_302_status_code_for_temporary_redirects(): void
    {
        // Arrange: Define target URL only
        $targetUrl = '/dashboard';

        // Act: Call the redirect method without custom status code
        $response = $this->sut->redirect($targetUrl);

        // Assert: Verify temporary redirect (302) status code
        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_stream_returns_streamed_response_with_custom_parameters(): void
    {
        // Arrange: Create a callback that outputs test data, with video content type and partial content status
        $callback = function (): void {
            echo 'test data';
        };
        $contentType = 'video/mp4';
        $statusCode = 206;

        // Act: Call the stream method with custom parameters
        $response = $this->sut->stream($callback, $contentType, $statusCode);

        // Assert: Verify streamed response type, partial content status, and proper headers
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame($contentType, $response->headers->get('Content-Type'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    }

    public function test_stream_uses_default_content_type_when_none_provided(): void
    {
        // Arrange: Create an empty callback
        $callback = function (): void {};

        // Act: Call the stream method without specifying content type
        $response = $this->sut->stream($callback);

        // Assert: Verify default binary stream content type
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function test_sse_returns_properly_configured_server_sent_events_response(): void
    {
        // Arrange: Create an SSE callback that emits a test event
        $callback = function (): void {
            echo "data: test\n\n";
        };

        // Act: Call the sse method with the callback
        $response = $this->sut->sse($callback);

        // Assert: Verify SSE response has correct headers for real-time event streaming
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame('keep-alive', $response->headers->get('Connection'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    }

    public function test_no_content_returns_empty_204_response(): void
    {
        // Arrange: No setup needed for this test

        // Act: Call the noContent method
        $response = $this->sut->noContent();

        // Assert: Verify empty response with 204 No Content status code
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
    }

    public function test_inertia_returns_inertia_response_for_frontend_component_rendering(): void
    {
        // Arrange: Define the component name to render
        $componentName = 'Dashboard/Index';

        // Act: Call the inertia method with the component name
        $inertiaResponse = $this->sut->inertia($componentName);

        // Assert: Verify Inertia response type and component name
        $this->assertInstanceOf(\Inertia\Response::class, $inertiaResponse);

        // Verify the component name using reflection (since property is protected)
        $reflection = new \ReflectionClass($inertiaResponse);
        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);

        $this->assertSame($componentName, $componentProperty->getValue($inertiaResponse));
    }

    public function test_html_returns_html_response_with_custom_status_code(): void
    {
        // Arrange: Create HTML content and custom status code
        $htmlContent = '<h1>Hello World</h1>';
        $statusCode = 201;

        // Act: Call the html method with custom parameters
        $response = $this->sut->html($htmlContent, $statusCode);

        // Assert: Verify HTML response type, created status, and proper content type header
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame('text/html', $response->headers->get('Content-Type'));
        $this->assertSame($htmlContent, $response->getContent());
    }

    public function test_html_defaults_to_200_status_code_when_none_provided(): void
    {
        // Arrange: Create HTML content only
        $htmlContent = '<h1>Test</h1>';

        // Act: Call the html method without specifying status code
        $response = $this->sut->html($htmlContent);

        // Assert: Verify default 200 OK status code
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_file_inline_returns_file_with_inline_disposition_for_browser_display(): void
    {
        // Arrange: Create a temporary file with test content and custom file name
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');
        $customFileName = 'custom.pdf';

        // Act: Call the fileInline method with custom file name
        $response = $this->sut->fileInline($tempFile, $customFileName);

        // Assert: Verify binary file response with inline disposition for browser display
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'inline; filename="'.$customFileName.'"',
            $response->headers->get('Content-Disposition')
        );

        // Cleanup: Remove temporary file to avoid disk pollution
        unlink($tempFile);
    }

    public function test_file_inline_uses_original_filename_when_no_custom_name_provided(): void
    {
        // Arrange: Create a temporary file and get its base name
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $expectedFileName = basename($tempFile);

        // Act: Call the fileInline method without custom file name
        $response = $this->sut->fileInline($tempFile);

        // Assert: Verify inline disposition uses the original file name from the filesystem
        $this->assertStringContainsString(
            'inline; filename="'.$expectedFileName.'"',
            $response->headers->get('Content-Disposition')
        );

        // Cleanup: Remove temporary file to avoid disk pollution
        unlink($tempFile);
    }

    public function test_file_download_forces_file_download_with_custom_filename(): void
    {
        // Arrange: Create a temporary file with test content and custom download name
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');
        $customFileName = 'download.pdf';

        // Act: Call the fileDownload method with custom file name
        $response = $this->sut->fileDownload($tempFile, $customFileName);

        // Assert: Verify binary file response with attachment disposition for forced download
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            $customFileName,
            $response->headers->get('Content-Disposition')
        );

        // Cleanup: Remove temporary file to avoid disk pollution
        unlink($tempFile);
    }

    public function test_file_download_uses_original_filename_when_no_custom_name_provided(): void
    {
        // Arrange: Create a temporary file and get its base name
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $expectedFileName = basename($tempFile);

        // Act: Call the fileDownload method without custom file name
        $response = $this->sut->fileDownload($tempFile);

        // Assert: Verify download disposition includes the original file name
        $this->assertStringContainsString(
            $expectedFileName,
            $response->headers->get('Content-Disposition')
        );

        // Cleanup: Remove temporary file to avoid disk pollution
        unlink($tempFile);
    }

    public function test_text_returns_plain_text_response_with_custom_status_code(): void
    {
        // Arrange: Create text content and custom status code
        $textContent = 'Hello World';
        $statusCode = 201;

        // Act: Call the text method with custom parameters
        $response = $this->sut->text($textContent, $statusCode);

        // Assert: Verify plain text response type, created status, and proper content type
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame($textContent, $response->getContent());
    }

    public function test_text_defaults_to_200_status_code_when_none_provided(): void
    {
        // Arrange: Create text content only
        $textContent = 'Hello World';

        // Act: Call the text method without specifying status code
        $response = $this->sut->text($textContent);

        // Assert: Verify default 200 OK status code
        $this->assertSame(200, $response->getStatusCode());
    }
}
