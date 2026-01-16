<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDemoRequest;
use App\Models\DemoRequest;
use Illuminate\Http\RedirectResponse;

class DemoRequestController extends Controller
{
    public function store(StoreDemoRequest $request): RedirectResponse
    {
        $payload = $request->safe()->except(['website']);

        DemoRequest::create([
            'hotel_name' => $payload['hotel_name'],
            'name' => $payload['name'],
            'phone' => $payload['phone'],
            'city' => $payload['city'] ?? null,
            'email' => $payload['email'] ?? null,
            'message' => $payload['message'] ?? null,
            'source' => 'landing',
        ]);

        return back()->with('demoSuccess', true);
    }
}
