<?php

namespace App\Jobs;

use App\Events\VideoProcessed;
use App\Events\VideoProcessingFailed;
use App\Events\VideoProcessingProgress;
use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVideoJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Video $video)
    {
    }

    public function handle(): void
    {
        $video = $this->video;
        $videoId = $video->video_id;

        Log::info("Processando vídeo {$videoId}");

        $videoDir = storage_path("app/videos/{$videoId}");
        $audioDir = storage_path("app/audios/{$videoId}");

        @mkdir($videoDir, 0777, true);
        @mkdir($audioDir, 0777, true);

        $videoPath = "{$videoDir}/{$videoId}.mp4";
        $audioPath = "{$audioDir}/{$videoId}.wav";

        try {
            Process::timeout(3000)->run("yt-dlp -f best -o \"{$videoPath}\" \"{$video->youtube_url}\"");
            event(new VideoProcessingProgress($video, 20));

            if (!file_exists($videoPath)) {
                $video->update(['status' => 'failed_video_processing']);
                event(new VideoProcessingFailed($video, 'Erro ao baixar o vídeo'));
                return;
            }

            Process::timeout(3000)->run("ffmpeg -i \"{$videoPath}\" -vn -acodec pcm_s16le -ar 16000 -ac 1 \"{$audioPath}\"");
            event(new VideoProcessingProgress($video, 40));

            if (!file_exists($audioPath)) {
                $video->update(['status' => 'failed_audio_processing']);
                event(new VideoProcessingFailed($video, 'Erro ao extrair áudio do vídeo'));
                return;
            }

            $output = Process::timeout(3000)
                ->path(dirname($audioPath))
                ->run("whisper \"{$videoId}.wav\" --model base");

            event(new VideoProcessingProgress($video, 60));

            $transcription = $output->output();

            if (!$transcription) {
                Log::error("Whisper Output: " . $output->output());
                Log::error("Whisper Error: " . $output->errorOutput());
                $video->update(['status' => 'failed_transcription', 'transcription' => $transcription]);
                event(new VideoProcessingFailed($video, 'Erro ao transcrever o áudio do vídeo'));
                return;
            }

            preg_match('/Detected language: (\w+)/', $transcription, $langMatch);
            $language = $langMatch[1] ?? 'Desconhecido';

            $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => "Resuma esse texto que é uma transcrição de um vídeo, então trate isso como um vídeo: {$transcription}"]
                ],
            ]);


            event(new VideoProcessingProgress($video, 80));

            $summary = $response->json('choices.0.message.content') ?? 'Erro ao resumir texto';

            $video->update([
                'transcription' => $transcription,
                'summary' => $summary,
                'language' => $language,
                'status' => 'completed',
            ]);
            event(new VideoProcessingProgress($video, 100));
            event(new VideoProcessed($video));
        } catch (\Throwable $e) {
            Log::error("Erro ao processar vídeo {$videoId}: {$e->getMessage()}");
            event(new VideoProcessingFailed($video, $e->getMessage()));
        } finally {
            @unlink($videoPath);
            @unlink($audioPath);
            @collect(glob("$videoDir/*.*"))->each(fn($f) => @unlink($f));
            @collect(glob("$audioDir/*.*"))->each(fn($f) => @unlink($f));
        }
    }
}
