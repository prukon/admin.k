<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ChatPageController extends Controller
{
    public function index(): View
    {
        return view('chat.index');
    }
}
