<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitEnquiryPayload
{
    private const MAX_BYTES = 65536;

    public function handle(Request $request, Closure $next): Response
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        if ($contentLength > self::MAX_BYTES || strlen((string) $request->getContent()) > self::MAX_BYTES) {
            return response()->json(['error' => [
                'code' => 'payload_too_large', 'message' => 'The request is too large.', 'requestId' => (string) str()->uuid(),
            ]], 413);
        }

        if (! $request->isJson()) {
            return response()->json(['error' => [
                'code' => 'json_required', 'message' => 'A JSON request body is required.', 'requestId' => (string) str()->uuid(),
            ]], 400);
        }
        $decoded = json_decode((string) $request->getContent());
        if (json_last_error() !== JSON_ERROR_NONE || ! is_object($decoded)) {
            return response()->json(['error' => [
                'code' => 'invalid_json', 'message' => 'The request body must be a valid JSON object.', 'requestId' => (string) str()->uuid(),
            ]], 400);
        }

        return $next($request);
    }
}
