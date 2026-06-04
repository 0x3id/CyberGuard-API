<?php
namespace App\Http\Controllers;

use App\Models\UserApiKey;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserApiKeyController extends Controller
{
    // Allowed Services
    protected $allowedServices = ['virustotal', 'abuseipdb', 'whoisxml', 'shodan', 'urlscan', 'ai_assistant'];

    /**
     * Get All Api Keys Of User
     */
    public function index(Request $request)
    {
        // Key the user's saved API keys by service so we can access id + key
        $savedKeys = $request->user()->apiKeys->keyBy('service');

        $response = [];
        foreach ($this->allowedServices as $service) {
            $entry = $savedKeys->get($service);
            $rawKey = $entry?->key ?? null;
            $keyId  = $entry?->uuid ?? null;

            // If ai_assistant and no user key, use default system key (no id)
            if ($service === 'ai_assistant' && !$rawKey) {
                $rawKey = config('services.ai.default_key') ?? env('DEFAULT_AI_ASSISTANT_KEY');
                $isDefault = true;
                $keyId = null;
            } else {
                $isDefault = false;
            }

            $response[$service] = [
                'id' => $keyId,
                'has_key' => !empty($rawKey),
                'key' => $rawKey,
                'masked' => $rawKey ? Str::mask($rawKey, '•', 4, -4) : null,
            ];
        }

        return response()->json($response);
    }

    /**
     * Save Or Update
     */
    public function store(Request $request)
    {
        $request->validate([
            'keys' => 'required|array',
            'keys.*' => 'nullable|string|max:500',
        ]);

        foreach ($request->input('keys') as $service => $value) {
            if (in_array($service, $this->allowedServices)) {
                
                if (empty($value)) {
                    $request->user()->apiKeys()->where('service', $service)->delete();
                    continue;
                }

                $request->user()->apiKeys()->updateOrCreate(
                    ['service' => $service],
                    ['key' => $value]
                );
            }
        }

        return response()->json(['message' => 'API Keys Saved Or Updated Successfuly.'], 201);
    }

    /**
     * Delete Specific Key
     */
    public function delete(Request $request, UserApiKey $apiKey): JsonResponse
    {
        $user = $request->user();

        // Ensure the key belongs to the authenticated user
        if ($apiKey->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete the API key
        $apiKey->delete();

        // Log the deletion
        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'user.apikey.delete',
            'entity_type' => UserApiKey::class,
            'entity_id'   => $apiKey->uuid ?? $apiKey->id ?? null,
            'ip_address'  => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['message' => 'API key deleted successfully']);
    }
}