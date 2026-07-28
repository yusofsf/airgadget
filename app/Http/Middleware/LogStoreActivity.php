<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogStoreActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = (string) Str::uuid();
        $request->attributes->set('store_request_id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $this->write('info', 'request.started', $this->requestContext($request));

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->write($exception instanceof ValidationException ? 'warning' : 'error', 'request.failed', [
                ...$this->requestContext($request),
                'duration_ms' => $this->duration($startedAt),
                'exception' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'exception_code' => $exception->getCode(),
                'validation_errors' => $exception instanceof ValidationException ? $exception->errors() : null,
            ]);

            throw $exception;
        }

        $level = $response->getStatusCode() >= 500
            ? 'error'
            : ($response->getStatusCode() >= 400 ? 'warning' : 'info');

        $this->write($level, 'request.completed', [
            ...$this->requestContext($request),
            'status' => $response->getStatusCode(),
            'duration_ms' => $this->duration($startedAt),
            'response_type' => $response->headers->get('content-type'),
        ]);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function requestContext(Request $request): array
    {
        return [
            'method' => $request->method(),
            'url' => $request->getSchemeAndHttpHost().'/'.ltrim($request->route()?->uri() ?? $request->path(), '/'),
            'route' => $request->route()?->getName(),
            'route_parameters' => $this->routeParameters($request),
            'query_keys' => array_keys($request->query()),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'content_type' => $request->header('content-type'),
            'content_length' => $request->server('CONTENT_LENGTH'),
            'input_keys' => array_values(array_diff(array_keys($request->except([
                'password', 'password_confirmation', 'current_password', '_token',
                'card_number', 'card_to_card_number', 'receipt',
            ])), ['password', 'password_confirmation', 'current_password'])),
            'uploads' => $this->uploadedFiles($request->allFiles()),
        ];
    }

    private function uploadedFiles(array $files, string $prefix = ''): array
    {
        $uploads = [];

        foreach ($files as $key => $file) {
            $field = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($file)) {
                $uploads = [...$uploads, ...$this->uploadedFiles($file, $field)];
                continue;
            }

            if ($file instanceof UploadedFile) {
                $uploads[] = [
                    'field' => $field,
                    'original_name' => basename($file->getClientOriginalName()),
                    'client_mime' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                    'upload_error' => $file->getError(),
                    'is_valid' => $file->isValid(),
                ];
            }
        }

        return $uploads;
    }

    private function routeParameters(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(function ($value, $key) {
                if (in_array(Str::lower((string) $key), ['token', 'password', 'signature'], true)) {
                    return '[REDACTED]';
                }

                return is_object($value) && method_exists($value, 'getRouteKey')
                    ? $value->getRouteKey()
                    : (is_scalar($value) ? $value : get_debug_type($value));
            })
            ->all();
    }

    private function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function write(string $level, string $event, array $context): void
    {
        try {
            Log::channel(config('logging.store_channel', 'store'))->log($level, $event, $context);
        } catch (Throwable $loggingException) {
            // Logging must never make the storefront unavailable.
            report($loggingException);
        }
    }
}
