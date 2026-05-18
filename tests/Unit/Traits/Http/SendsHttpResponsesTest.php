<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Traits\Http;

use AndyDefer\BestPractices\Tests\Fixtures\Actions\TestAction;
use AndyDefer\BestPractices\Tests\Fixtures\Data\TestUserData;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserStatus;
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
        // Arrange
        $createdAt = new DateTime('2024-01-15 10:30:00');
        $testData = new TestUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::ADMIN,
            grade: TestUserGrade::GOLD,
            emailVerifiedAt: '2024-01-15T10:30:00Z',
            tags: ['admin', 'premium'],
            createdAt: $createdAt->format('Y-m-d\TH:i:s\Z'),
        );

        // Act
        $response = $this->sut->json($testData, 201);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame($testData->toArray(), $response->getData(true));
    }

    public function test_json_returns_empty_response_when_data_is_null_and_status_is_204(): void
    {
        // Act
        $response = $this->sut->noContent();

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(204, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertEmpty($content);
    }

    public function test_json_defaults_to_200_status_code_when_no_code_provided(): void
    {
        // Arrange
        $createdAt = new DateTime('2024-01-15 10:30:00');
        $testData = new TestUserData(
            id: '1',
            name: 'John Doe',
            email: 'john@example.com',
            status: TestUserStatus::ACTIVE,
            role: TestUserRole::USER,
            grade: TestUserGrade::BRONZE,
            emailVerifiedAt: '2024-01-15T10:30:00Z',
            tags: ['admin', 'premium'],
            createdAt: $createdAt->format('Y-m-d\TH:i:s\Z'),
        );

        // Act
        $response = $this->sut->json($testData);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($testData->toArray(), $response->getData(true));
    }

    public function test_redirect_returns_redirect_response_with_custom_status_code(): void
    {
        // Arrange
        $targetUrl = '/dashboard';
        $statusCode = 301;

        // Act
        $response = $this->sut->redirect($targetUrl, $statusCode);

        // Assert
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertStringEndsWith($targetUrl, $response->getTargetUrl());
    }

    public function test_redirect_defaults_to_302_status_code_for_temporary_redirects(): void
    {
        // Arrange
        $targetUrl = '/dashboard';

        // Act
        $response = $this->sut->redirect($targetUrl);

        // Assert
        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_stream_returns_streamed_response_with_custom_parameters(): void
    {
        // Arrange
        $callback = function (): void {
            echo 'test data';
        };
        $contentType = 'video/mp4';
        $statusCode = 206;

        // Act
        $response = $this->sut->stream($callback, $contentType, $statusCode);

        // Assert
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame($contentType, $response->headers->get('Content-Type'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    }

    public function test_stream_uses_default_content_type_when_none_provided(): void
    {
        // Arrange
        $callback = function (): void {};

        // Act
        $response = $this->sut->stream($callback);

        // Assert
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function test_sse_returns_properly_configured_server_sent_events_response(): void
    {
        // Arrange
        $callback = function (): void {
            echo "data: test\n\n";
        };

        // Act
        $response = $this->sut->sse($callback);

        // Assert
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame('keep-alive', $response->headers->get('Connection'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    }

    public function test_no_content_returns_empty_204_response(): void
    {
        // Act
        $response = $this->sut->noContent();

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
    }

    public function test_inertia_returns_inertia_response_for_frontend_component_rendering(): void
    {
        // Arrange
        $componentName = 'Dashboard/Index';

        // Act
        $inertiaResponse = $this->sut->inertia($componentName);

        // Assert
        $this->assertInstanceOf(\Inertia\Response::class, $inertiaResponse);

        $reflection = new \ReflectionClass($inertiaResponse);
        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);

        $this->assertSame($componentName, $componentProperty->getValue($inertiaResponse));
    }

    public function test_html_returns_html_response_with_custom_status_code(): void
    {
        // Arrange
        $htmlContent = '<h1>Hello World</h1>';
        $statusCode = 201;

        // Act
        $response = $this->sut->html($htmlContent, $statusCode);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame('text/html', $response->headers->get('Content-Type'));
        $this->assertSame($htmlContent, $response->getContent());
    }

    public function test_html_defaults_to_200_status_code_when_none_provided(): void
    {
        // Arrange
        $htmlContent = '<h1>Test</h1>';

        // Act
        $response = $this->sut->html($htmlContent);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_file_inline_returns_file_with_inline_disposition_for_browser_display(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');
        $customFileName = 'custom.pdf';

        // Act
        $response = $this->sut->fileInline($tempFile, $customFileName);

        // Assert
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'inline; filename="' . $customFileName . '"',
            $response->headers->get('Content-Disposition')
        );

        unlink($tempFile);
    }

    public function test_file_inline_uses_original_filename_when_no_custom_name_provided(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $expectedFileName = basename($tempFile);

        // Act
        $response = $this->sut->fileInline($tempFile);

        // Assert
        $this->assertStringContainsString(
            'inline; filename="' . $expectedFileName . '"',
            $response->headers->get('Content-Disposition')
        );

        unlink($tempFile);
    }

    public function test_file_download_forces_file_download_with_custom_filename(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');
        $customFileName = 'download.pdf';

        // Act
        $response = $this->sut->fileDownload($tempFile, $customFileName);

        // Assert
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            $customFileName,
            $response->headers->get('Content-Disposition')
        );

        unlink($tempFile);
    }

    public function test_file_download_uses_original_filename_when_no_custom_name_provided(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $expectedFileName = basename($tempFile);

        // Act
        $response = $this->sut->fileDownload($tempFile);

        // Assert
        $this->assertStringContainsString(
            $expectedFileName,
            $response->headers->get('Content-Disposition')
        );

        unlink($tempFile);
    }

    public function test_text_returns_plain_text_response_with_custom_status_code(): void
    {
        // Arrange
        $textContent = 'Hello World';
        $statusCode = 201;

        // Act
        $response = $this->sut->text($textContent, $statusCode);

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame($textContent, $response->getContent());
    }

    public function test_text_defaults_to_200_status_code_when_none_provided(): void
    {
        // Arrange
        $textContent = 'Hello World';

        // Act
        $response = $this->sut->text($textContent);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }
}
