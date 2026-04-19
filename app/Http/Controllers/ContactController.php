<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request, int $propertyId): JsonResponse
    {
        $property = Property::where('id', $propertyId)->where('status', 'published')->firstOrFail();

        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:30',
            'message' => 'required|string|max:2000',
        ]);

        $contact = $property->contacts()->create([
            ...$data,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json($contact, 201);
    }

    public function myContacts(Request $request): JsonResponse
    {
        $propertyIds = $request->user()->properties()->pluck('id');

        $contacts = Contact::whereIn('property_id', $propertyIds)
            ->with('property:id,title')
            ->latest()
            ->paginate(20);

        return response()->json($contacts);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $propertyIds = $request->user()->properties()->pluck('id');

        $contact = Contact::whereIn('property_id', $propertyIds)->findOrFail($id);
        $contact->update(['is_read' => true]);

        return response()->json($contact);
    }
}
