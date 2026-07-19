<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactController extends Controller
{
    public function create()
    {
        return view('contact.create');
    }

    public function store(StoreContactMessageRequest $request)
    {
        try {
            ContactMessage::query()->create($request->contactMessageData());
        } catch (Throwable $exception) {
            Log::error('Contact form submission failed', [
                'email' => $request->input('email'),
                'subject' => $request->input('subject'),
                'exception' => $exception,
            ]);

            $message = 'We could not send your message at this time. Please try again or contact us using the available contact information.';

            if ($request->expectsJson()) {
                return new JsonResponse([
                    'message' => $message,
                ], 500);
            }

            return back()->withErrors(['contact' => $message])->withInput();
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => 'Message sent. The Eclise team will respond soon.',
            ]);
        }

        return redirect()->route('contact.create')->with('status', 'Message sent. The Eclise team will respond soon.');
    }
}
