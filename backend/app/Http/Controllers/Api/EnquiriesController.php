<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EnquiryIdempotencyConflict;
use App\Exceptions\EnquiryProductsUnavailable;
use App\Exceptions\EnquiryResourceUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnquiryRequest;
use App\Services\EnquirySubmission;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class EnquiriesController extends Controller
{
    public function store(StoreEnquiryRequest $request, EnquirySubmission $submission): JsonResponse
    {
        $type = $request->validated('type');
        if (($type === 'product_enquiry' && ! config('enquiries.product_enabled'))
            || ($type === 'cart_enquiry' && ! config('enquiries.cart_enabled'))) {
            return response()->json(['error' => [
                'code' => 'feature_unavailable', 'message' => 'This enquiry option is currently unavailable.', 'requestId' => (string) str()->uuid(),
            ]], 404)->header('Cache-Control', 'no-store');
        }

        $key = (string) $request->header('Idempotency-Key', '');
        if (! preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $key)) {
            return response()->json(['error' => [
                'code' => 'idempotency_key_required', 'message' => 'A valid Idempotency-Key header is required.', 'requestId' => (string) str()->uuid(),
            ]], 422);
        }

        try {
            $result = $submission->submit($request->validated(), $key);
        } catch (EnquiryIdempotencyConflict) {
            return response()->json(['error' => [
                'code' => 'idempotency_conflict', 'message' => 'This idempotency key was already used for different content.', 'requestId' => (string) str()->uuid(),
            ]], 409);
        } catch (EnquiryProductsUnavailable $exception) {
            return response()->json(['error' => [
                'code' => 'products_unavailable', 'message' => 'One or more selected products are unavailable.',
                'requestId' => (string) str()->uuid(), 'products' => $exception->productIds,
            ]], 422);
        } catch (EnquiryResourceUnavailable) {
            return response()->json(['error' => [
                'code' => 'resource_unavailable', 'message' => 'The selected item is unavailable.', 'requestId' => (string) str()->uuid(),
            ]], 404);
        } catch (QueryException) {
            // Database exceptions can include SQL details; keep them out of public responses and application logs.
            return response()->json(['error' => [
                'code' => 'persistence_unavailable', 'message' => 'The request could not be saved. Please retry with the same key.',
                'requestId' => (string) str()->uuid(),
            ]], 503);
        }

        $response = response()->json(['data' => [
            'status' => 'persisted',
            'enquiryId' => $result['enquiry']->public_id,
            'notificationStatus' => $result['notification_status'],
            ...($result['download_grant'] ? ['downloadGrant' => [
                'token' => $result['download_grant']['token'],
                'expiresAt' => $result['download_grant']['expiresAt'],
                'downloadPath' => '/api/v1/catalogues/download',
                'enquiryHeader' => 'X-Catalogue-Enquiry',
            ]] : []),
        ]], $result['replayed'] ? 200 : 201);

        return $response->header('Cache-Control', 'no-store');
    }
}
