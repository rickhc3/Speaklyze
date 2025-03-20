<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function ask(Request $request, Video $video)
    {
        $request->validate(['message' => 'required|string']);

        // Criar a sessão de chat caso ainda não tenha sido gerada (segurança)
        if (!$video->chat_session_id) {
            $video->update(['chat_session_id' => Str::uuid()]);
        }

        // Salvar a pergunta do usuário
        ChatMessage::create([
            'chat_session_id' => $video->chat_session_id, // Usamos a sessão de chat
            'role' => 'user',
            'message' => $request->message,
            'video_id' => $video->id
        ]);

        // Recuperar mensagens da sessão específica desse vídeo
        $messages = collect([
            ['role' => 'assistant', 'content' => "Aqui está o resumo do vídeo:\n" . $video->summary]
        ])->merge(
            ChatMessage::where('chat_session_id', $video->chat_session_id)
                ->orderBy('created_at')
                ->get()
                ->map(fn($m) => [
                    'role' => $m->role,
                    'content' => $m->message
                ])
        );


        // Chamar API OpenAI
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY')
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => $messages->toArray(),
        ]);

        $reply = $response->json('choices.0.message.content');

        // Salvar resposta do assistente
        ChatMessage::create([
            'chat_session_id' => $video->chat_session_id,
            'role' => 'assistant',
            'message' => $reply,
            'video_id' => $video->id
        ]);

        return response()->json(['reply' => $reply]);
    }

    /**
     * Recupera todas as mensagens associadas a um vídeo.
     */
    public function getMessages(Video $video)
    {
        if (!$video->chat_session_id) {
            return response()->json(['messages' => [], 'session_id' => null]);
        }

        $messages = ChatMessage::where('chat_session_id', $video->chat_session_id)
            ->orderBy('created_at')
            ->get(['role', 'message']);

        return response()->json([
            'messages' => $messages,
            'session_id' => $video->chat_session_id
        ]);
    }
}
