<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Concerns\RespondsWithApiEnvelope;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CurrentUserController extends Controller
{
    use RespondsWithApiEnvelope;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions', 'scopedCompanies');

        return $this->success('Authenticated user retrieved.', [
            'user' => new UserResource($user),
        ]);
    }
}
