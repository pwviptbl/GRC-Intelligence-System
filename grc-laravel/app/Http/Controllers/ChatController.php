<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function index()
    {
        return view('chat.index');
    }

    public function send(Request $request)
    {
        $message = $request->input('message');
        if (empty($message)) return response()->json(['erro' => 'Mensagem vazia'], 400);

        $result = $this->gemini->chat($message);
        return response()->json($result);
    }
}
