<?php

namespace WerdsWords\LinkStack\SharedProfiles\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiLinkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! $token) {
            abort(401, 'Unauthenticated.');
        }

        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model');
        $user = $userModel::where('api_token', $token)->first();

        if (! $user) {
            abort(401, 'Invalid API token.');
        }

        $validated = $request->validate([
            'link' => 'required|url|max:2048',
            'title' => 'required|string|max:255',
            'button_id' => 'required|integer|exists:buttons,id',
            'meta' => 'sometimes|array',
        ]);

        $user->links()->create([
            'link' => $validated['link'],
            'title' => $validated['title'],
            'button_id' => $validated['button_id'],
            'type' => 'predefined',
            'type_params' => isset($validated['meta']) ? json_encode($validated['meta']) : null,
            'status' => config('linkstack-shared-profiles.auto_approve') ? 'published' : 'pending',
            'order' => 999,
        ]);

        return response()->json(['status' => 'queued'], 201);
    }
}
