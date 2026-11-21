<?php

namespace App\Http\Controllers;

use App\Models\HotelSetting;
use App\Models\Outlet;
use App\Services\CloudSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CloudSyncApiController extends Controller
{
    public function __construct(
        private readonly CloudSyncService $cloudSync
    ) {}

    public function health(): JsonResponse
    {
        $settings = HotelSetting::allAsMap();

        return response()->json([
            'ok' => true,
            'mode' => $settings['pos_mode'] ?? 'outlet',
            'outlets_connected' => Outlet::query()->where('is_active', true)->count(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function receiveSync(Request $request): JsonResponse
    {
        try {
            $payload = $request->json()->all();
            $result = $this->cloudSync->ingestInbound(
                payload: is_array($payload) ? $payload : [],
                rawBody: $request->getContent(),
                bearerToken: $request->bearerToken(),
                signature: $request->header('X-POS-Signature'),
                timestamp: $request->header('X-POS-Timestamp')
            );

            return response()->json($result, 200);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'token') || str_contains(strtolower($e->getMessage()), 'signature')
                ? 401
                : 422;

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
