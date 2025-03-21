<?php

namespace App\Http\Controllers;

use App\Events\VideoProcessed;
use App\Events\VideoProcessingFailed;
use App\Jobs\ProcessVideoJob;
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
            'description' => $videoDetails['description'] ?: 'Não foi possível obter a descrição',
            'genre' => $videoDetails['genre'] ?: 'Não foi possível obter o gênero',
            'published_date' => $videoDetails['published_date'] ?: null,
            'status' => 'processing',
            'video_id' => $videoId,
            'chat_session_id' => Str::uuid(),
            'user_id' => auth()->id(),
        ]);

        dispatch(new ProcessVideoJob($video));

        return response()->json($video);
    }

    public function index()
    {
        return response()->json(auth()->user()->videos()->withTrashed()->latest()->get());
    }

    public function show($id)
    {
        $video = auth()->user()->videos()->withTrashed()->findOrFail($id);
        return response()->json($video);
    }

    public function destroy($id)
    {
        $video = auth()->user()->videos()->findOrFail($id);
        $video->delete();

        return response()->json(['message' => 'Vídeo removido da lista']);
    }

    public function restore($id)
    {
        $video = auth()->user()->videos()->onlyTrashed()->findOrFail($id);
        $video->restore();

        return response()->json(['message' => 'Vídeo restaurado']);
    }

    public function forceDelete($id)
    {
        $video = auth()->user()->videos()->onlyTrashed()->findOrFail($id);
        $video->forceDelete();

        return response()->json(['message' => 'Vídeo excluído permanentemente']);
    }

    public function retry($id)
    {
        $video = auth()->user()->videos()->findOrFail($id);

        if (!in_array($video->status, ['failed_video_processing', 'failed_audio_processing', 'failed_transcription'])) {
            return response()->json(['message' => 'Este vídeo não está em estado de falha.'], 422);
        }

        $video->update(['status' => 'processing']);

        dispatch(new ProcessVideoJob($video));

        return response()->json(['message' => 'Reprocessamento iniciado.']);
    }

    public function emptyTrash()
    {
        auth()->user()->videos()->onlyTrashed()->get()->each(function ($video) {
            $video->forceDelete();
        });

        return response()->json(['message' => 'Lixeira esvaziada com sucesso']);
    }

    private function processVideo(Video $video, string $videoId)
    {
        Log::info("Processando vídeo {$videoId}");
        // Diretórios
        $videoDir = storage_path("app/videos/{$videoId}");
        $audioDir = storage_path("app/audios/{$videoId}");

        if (!file_exists($videoDir)) {
            mkdir($videoDir, 0777, true);
        }
        if (!file_exists($audioDir)) {
            mkdir($audioDir, 0777, true);
        }

        $videoPath = "{$videoDir}/{$videoId}.mp4";
        $audioPath = "{$audioDir}/{$videoId}.wav";

        try {
            Process::timeout(3000)->run("yt-dlp -f best -o \"{$videoPath}\" \"{$video->youtube_url}\"");

            if (!file_exists($videoPath)) {
                Log::error("Erro ao baixar o vídeo {$videoId}");
                $video->update(['status' => 'failed_video_processing']);
                event(new VideoProcessingFailed($video, 'Erro ao baixar o vídeo'));
                return;
            }

            // 2. Extrair áudio com ffmpeg
            $result = Process::timeout(3000)->run("ffmpeg -i \"{$videoPath}\" -vn -acodec pcm_s16le -ar 16000 -ac 1 \"{$audioPath}\"");

            Log::info("FFMPEG Output: " . $result->output());
            Log::error("FFMPEG Error: " . $result->errorOutput());

            if (!file_exists($audioPath)) {
                Log::error("Erro ao extrair áudio do vídeo {$videoId}");
                $video->update(['status' => 'failed_audio_processing']);
                event(new VideoProcessingFailed($video, 'Erro ao extrair áudio do vídeo'));
                return;
            }

            // 3. Transcrever com Whisper (detecção automática de idioma)
            $output = Process::timeout(3000)
                ->path(dirname($audioPath))
                ->run("whisper \"{$videoId}.wav\" --model base");
            $transcription = $output->output();

            if (!$transcription) {
                Log::error("Erro ao transcrever o áudio do vídeo {$videoId}");
                $video->update(['status' => 'failed_transcription']);
                event(new VideoProcessingFailed($video, 'Erro ao transcrever o áudio do vídeo'));
                return;
            }

            // 4. Detectar idioma
            preg_match('/Detected language: (\w+)/', $transcription, $languageMatch);
            $detectedLanguage = $languageMatch[1] ?? 'Desconhecido';

            // 5. Resumo com OpenAI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY')
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [['role' => 'user', 'content' => "Resuma esse texto que é uma transcrição de um vídeo: {$transcription}"]],
            ]);

            $summary = $response->json('choices.0.message.content') ?? 'Erro ao resumir texto';

            // 6. Salvar no banco
            $video->update([
                'transcription' => $transcription,
                'summary' => $summary,
                'language' => $detectedLanguage,
                'status' => 'completed',
            ]);

            Log::info("Vídeo {$videoId} processado com sucesso");
            event(new VideoProcessed($video));
        } catch (\Exception $e) {
            Log::error("Erro ao processar vídeo {$videoId}: {$e->getMessage()}");
            event(new VideoProcessingFailed($video, $e->getMessage()));
        } finally {
            // Remover arquivos temporários
            if (file_exists($videoPath)) {
                unlink($videoPath);
                array_map('unlink', glob("$videoDir/*.*"));
            }
            if (file_exists($audioPath)) {
                unlink($audioPath);
                array_map('unlink', glob("$audioDir/*.*"));
            }
        }
    }

    private function getYoutubeVideoDetails($videoUrl)
    {
        try {
            $client = new Client();
            $response = $client->get($videoUrl);
            $html = $response->getBody()->getContents();

            // Expressão regular para capturar o título do vídeo
            preg_match('/<meta itemprop="name" content="(.*?)">/', $html, $titleMatch);
            $videoTitle = $titleMatch[1] ?? null;

            // Expressão regular para capturar a URL e o nome do canal dentro do <span itemprop="author">
            preg_match('/<span itemprop="author".*?>\s*<link itemprop="url" href="(.*?)">\s*<link itemprop="name" content="(.*?)">/s', $html, $channelMatch);

            $channelUrl = $channelMatch[1] ?? null;
            $channelName = $channelMatch[2] ?? null;

            // Expressão regular para capturar a descrição do vídeo
            preg_match('/<meta property="og:description" content="(.*?)">/s', $html, $descriptionMatch);
            $description = $descriptionMatch[1] ?? null;

            // Expressão regular para capturar o gênero do vídeo
            preg_match('/<meta itemprop="genre" content="(.*?)">/s', $html, $genreMatch);
            $genre = $genreMatch[1] ?? null;

            // Expressão regular para capturar a data de publicação
            preg_match('/<meta itemprop="datePublished" content="(.*?)">/s', $html, $dateMatch);
            $publishedDate = $dateMatch[1] ?? null;

            return [
                'title' => str_replace(" - YouTube", "", $videoTitle),
                'channel' => $channelName,
                'channel_url' => $channelUrl,
                'description' => $description,
                'genre' => $genre,
                'published_date' => $publishedDate
            ];
        } catch (\Exception $e) {
            return [
                'title' => 'Erro ao obter título',
                'channel' => 'Erro ao obter canal',
                'channel_url' => 'Erro ao obter URL do canal',
                'description' => 'Erro ao obter descrição',
                'genre' => 'Erro ao obter gênero',
                'published_date' => 'Erro ao obter data de publicação'
            ];
        }
    }
}
