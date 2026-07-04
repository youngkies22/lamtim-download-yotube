<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VideoDownloadController extends Controller
{
    /**
     * Domains this tool is allowed to fetch. Prevents the app from being
     * abused as a general-purpose URL fetching proxy.
     */
    private const ALLOWED_HOSTS = [
        'youtube.com',
        'youtu.be',
        'tiktok.com',
        'instagram.com',
        'facebook.com',
        'fb.watch',
    ];

    /**
     * Quality keys the client may request, mapped to the real yt-dlp format
     * selector server-side. Never accept a raw yt-dlp format id from the client.
     */
    private const QUALITY_SELECTORS = [
        'best' => 'bv*+ba/b',
        '1080p' => 'bv*[height<=1080]+ba/b[height<=1080]',
        '720p' => 'bv*[height<=720]+ba/b[height<=720]',
        '480p' => 'bv*[height<=480]+ba/b[height<=480]',
        '360p' => 'bv*[height<=360]+ba/b[height<=360]',
    ];

    private const QUALITY_LABELS = [
        'best' => 'Kualitas Terbaik (MP4)',
        '1080p' => '1080p (MP4)',
        '720p' => '720p (MP4)',
        '480p' => '480p (MP4)',
        '360p' => '360p (MP4)',
        'audio' => 'Audio saja (MP3)',
    ];

    public function info(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url'],
        ]);

        if (! $this->isAllowedHost($data['url'])) {
            return response()->json([
                'message' => 'Platform tidak didukung. Gunakan link YouTube, TikTok, Instagram, atau Facebook.',
            ], 422);
        }

        try {
            $result = Process::timeout(60)->env($this->ytdlpEnv())->run([
                config('services.ytdlp.bin'),
                '-j',
                '--no-warnings',
                '--no-playlist',
                $data['url'],
            ]);
        } catch (ProcessTimedOutException) {
            return response()->json([
                'message' => 'Server terlalu lama merespons. Coba lagi.',
            ], 504);
        }

        if ($result->failed()) {
            return response()->json([
                'message' => 'Video tidak ditemukan atau tidak bisa diakses (mungkin private/dihapus).',
            ], 422);
        }

        $meta = json_decode($result->output(), true);

        if (! is_array($meta)) {
            return response()->json([
                'message' => 'Gagal membaca informasi video.',
            ], 422);
        }

        $heights = collect($meta['formats'] ?? [])
            ->pluck('height')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $maxHeight = $heights->max() ?? 0;

        $qualities = collect(['1080p' => 1080, '720p' => 720, '480p' => 480, '360p' => 360])
            ->filter(fn ($height) => $maxHeight >= $height)
            ->keys()
            ->prepend('best')
            ->push('audio')
            ->map(fn ($key) => ['key' => $key, 'label' => self::QUALITY_LABELS[$key]])
            ->values();

        return response()->json([
            'title' => $meta['title'] ?? 'Video',
            'thumbnail' => $meta['thumbnail'] ?? null,
            'duration' => $meta['duration'] ?? null,
            'platform' => $meta['extractor_key'] ?? $meta['extractor'] ?? null,
            'qualities' => $qualities,
        ]);
    }

    public function download(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url'],
            'quality' => ['required', Rule::in(array_merge(array_keys(self::QUALITY_SELECTORS), ['audio']))],
        ]);

        if (! $this->isAllowedHost($data['url'])) {
            return response()->json([
                'message' => 'Platform tidak didukung. Gunakan link YouTube, TikTok, Instagram, atau Facebook.',
            ], 422);
        }

        $tmpDir = storage_path('app/tmp/'.Str::uuid());
        File::ensureDirectoryExists($tmpDir);

        $args = [
            config('services.ytdlp.bin'),
            '-q',
            '--no-warnings',
            '--no-playlist',
            '--ffmpeg-location', config('services.ytdlp.ffmpeg'),
            '-o', $tmpDir.DIRECTORY_SEPARATOR.'%(title).150B [%(id)s].%(ext)s',
        ];

        if ($data['quality'] === 'audio') {
            $args = array_merge($args, ['-f', 'bestaudio/best', '-x', '--audio-format', 'mp3']);
        } else {
            $args = array_merge($args, [
                '-f', self::QUALITY_SELECTORS[$data['quality']],
                '--merge-output-format', 'mp4',
            ]);
        }

        $args[] = $data['url'];

        try {
            $result = Process::timeout(300)->env($this->ytdlpEnv())->run($args);
        } catch (ProcessTimedOutException) {
            File::deleteDirectory($tmpDir);

            return response()->json([
                'message' => 'Download memakan waktu terlalu lama. Coba kualitas yang lebih rendah.',
            ], 504);
        }

        if ($result->failed()) {
            File::deleteDirectory($tmpDir);

            return response()->json([
                'message' => 'Gagal mengunduh video pada kualitas ini. Coba kualitas lain.',
            ], 422);
        }

        // Don't trust yt-dlp's own --print after_move:filepath output: on Windows it can
        // report a different string than the filename it actually wrote (e.g. titles with
        // "?" get saved with a sanitized full-width "？" but printed with the character
        // stripped). Since $tmpDir is a fresh per-request UUID folder, just read back
        // whatever file actually landed there instead of string-matching yt-dlp's report.
        $filePath = collect(File::files($tmpDir))->first()?->getPathname();

        if (! $filePath || ! is_file($filePath)) {
            File::deleteDirectory($tmpDir);

            return response()->json([
                'message' => 'File hasil download tidak ditemukan.',
            ], 422);
        }

        app()->terminating(fn () => File::deleteDirectory($tmpDir));

        return response()->download($filePath, basename($filePath))->deleteFileAfterSend(true);
    }

    /**
     * yt-dlp is installed as a Python user-site script; the PHP dev server's
     * child process doesn't always resolve the user site-packages path on
     * its own, so it must be passed explicitly via PYTHONPATH.
     */
    private function ytdlpEnv(): array
    {
        return array_filter([
            'PYTHONPATH' => config('services.ytdlp.pythonpath'),
        ]);
    }

    private function isAllowedHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        $host = strtolower(preg_replace('/^www\./', '', $host));

        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
