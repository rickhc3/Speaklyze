<?php

namespace App\Http\Controllers;

use App\Events\VideoProcessed;
use App\Models\Video;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['youtube_url' => 'required|url']);

        // Extrai o ID do vídeo do YouTube
        preg_match('/(?:v=|\/)([0-9A-Za-z_-]{11})/', $request->youtube_url, $matches);
        $videoId = $matches[1] ?? null;

        if (!$videoId) {
            return response()->json(['error' => 'URL inválida.'], 422);
        }

        $videoDetails = $this->getYoutubeVideoDetails($request->youtube_url);


        $video = Video::create([
            'youtube_url' => $request->youtube_url,
            'title' => $videoDetails['title'] ?: 'Não foi possível obter o título',
            'channel' => $videoDetails['channel'] ?: 'Não foi possível obter o canal',
            'channel_url' => $videoDetails['channel_url'] ?: 'Não foi possível obter a URL do canal',
            'status' => 'processing', // Novo status
            'video_id' => $videoId,
            'chat_session_id' => Str::uuid(), // Criando um UUID único para a sessão
        ]);

        dispatch(function () use ($video, $videoId) {
            $this->processVideo($video, $videoId);
        });

        return response()->json($video);
    }

    private function processVideo(Video $video, string $videoId)
    {
        Log::info("Processando vídeo {$videoId}");
        // Diretórios
        $videoDir = storage_path("app/videos");
        $audioDir = storage_path("app/audios");

        if (!file_exists($videoDir)) {
            mkdir($videoDir, 0777, true);
        }
        if (!file_exists($audioDir)) {
            mkdir($audioDir, 0777, true);
        }

        // Caminhos dos arquivos
        $videoPath = "{$videoDir}/{$videoId}.mp4";
        $audioPath = "{$audioDir}/{$videoId}.wav";

        Process::timeout(3000)->run("yt-dlp -f best -o \"{$videoPath}\" \"{$video->youtube_url}\"");

        if (!file_exists($videoPath)) {
            Log::error("Erro ao baixar o vídeo {$videoId}");
            $video->update(['status' => 'failed_video_processing']);
            return;
        }

        // 2. Extrair áudio com ffmpeg
        Process::timeout(3000)->run("ffmpeg -i \"{$videoPath}\" -vn -acodec pcm_s16le -ar 16000 -ac 1 \"{$audioPath}\"");

        if (!file_exists($audioPath)) {
            Log::error("Erro ao extrair áudio do vídeo {$videoId}");
            $video->update(['status' => 'failed_audio_processing']);
            return;
        }

        // 3. Transcrever com Whisper (detecção automática de idioma)
        $output = Process::timeout(3000)->path(storage_path("app/audios"))->run("whisper \"{$videoId}.wav\" --model base");
        $transcription = $output->output();

        if (!$transcription) {
            Log::error("Erro ao transcrever o áudio do vídeo {$videoId}");
            $video->update(['status' => 'failed_transcription']);
            return;
        }

        // 4. Resumo com OpenAI
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY')
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => "Resuma esse texto que é uma transcrição de um vídeo: {$transcription}"]],
        ]);

        $summary = $response->json('choices.0.message.content') ?? 'Erro ao resumir texto';

        // 5. Salvar no banco
        $video->update([
            'video_id' => $videoId, // Para exibir no frontend
            'transcription' => $transcription,
            'summary' => $summary,
            'status' => 'completed',
        ]);

        Log::info("Vídeo {$videoId} processado com sucesso");

        event(new VideoProcessed($video));

        // 6. Excluir os arquivos de vídeo e áudio após o processamento
        unlink($videoPath);
        unlink($audioPath);
        // remove all files in storage/app/videos and storage/app/audios. files only, not directories
        array_map('unlink', glob("$videoDir/*.*"));
        array_map('unlink', glob("$audioDir/*.*"));


    }

    public function index()
    {
        return response()->json(Video::withTrashed()->latest()->get());
    }

    public function show(Video $video)
    {
        return response()->json($video);
    }

    public function destroy($id)
    {
        $video = Video::findOrFail($id);
        $video->delete();
        return response()->json(['message' => 'Vídeo removido da lista']);
    }

    public function restore($id)
    {
        $video = Video::onlyTrashed()->findOrFail($id);
        $video->restore();
        return response()->json(['message' => 'Vídeo restaurado']);
    }

    private function getYoutubeVideoDetails($videoUrl)
    {
        try {
            $client = new Client();
            $response = $client->get($videoUrl);
            $html = $response->getBody()->getContents();

            // Expressão regular para capturar o título do vídeo
            preg_match('/<meta itemprop="name" content="(.*?)">/', $html, $titleMatch);
            $videoTitle = $titleMatch[1] ?? 'Título não encontrado';

            // Expressão regular para capturar a URL e o nome do canal dentro do <span itemprop="author">
            preg_match('/<span itemprop="author".*?>\s*<link itemprop="url" href="(.*?)">\s*<link itemprop="name" content="(.*?)">/s', $html, $channelMatch);

            $channelUrl = $channelMatch[1] ?? 'URL do canal não encontrada';
            $channelName = $channelMatch[2] ?? 'Canal desconhecido';

            return [
                'title' => str_replace(" - YouTube", "", $videoTitle),
                'channel' => $channelName,
                'channel_url' => $channelUrl
            ];
        } catch (\Exception $e) {
            return [
                'title' => 'Erro ao obter título',
                'channel' => 'Erro ao obter canal',
                'channel_url' => 'Erro ao obter URL do canal'
            ];
        }
    }
}
