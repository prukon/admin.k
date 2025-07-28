<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\DemoRequest;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

use App\Mail\NewContactSubmission;

use App\Models\ContactSubmission;


class LandingPageController extends Controller
{
    public function index()
    {
        return view('landing.index');
    }

    public function submission()
    {
        $submissions = ContactSubmission::latest()->paginate(20);
        return view('admin.submissions.index', compact('submissions'));

    }


    public function contactSend(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'required|string',
            'message' => 'nullable|string',
        ]);

        $submission = ContactSubmission::create($validated);

        Mail::to('prukon@gmail.com')->send(new NewContactSubmission($submission));

        if ($request->ajax()) {
            return response()->json(['message' => 'Заявка отправлена!']);
        }

        return back()->with('success', 'Сообщение отправлено!');
    }

    public function oferta()
    {
        return view('landing.oferta');

    }



}
