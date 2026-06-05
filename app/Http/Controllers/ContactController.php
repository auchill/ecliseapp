<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;

class ContactController extends Controller
{
    public function create()
    {
        return view('contact.create');
    }

    public function store(StoreContactMessageRequest $request)
    {
        ContactMessage::query()->create($request->validated());

        return redirect()->route('contact.create')->with('status', 'Message sent. The Eclise team will respond soon.');
    }
}
