<?php
// app/Http/Controllers/Chat/ChatPageController.php
namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChatPageController extends Controller
{
public function index(Request $request)
{
// Важно: в твоём леяуте должен быть @stack('scripts') перед </body>
return view('chat.index');
}
}
