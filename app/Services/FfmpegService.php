<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class FfmpegService
{
    public function available(): bool
    {
        return $this->binaryAvailable(config('betube.ffmpeg'));
    }

    public function probeAvailable(): bool
    {
        return $this->binaryAvailable(config('betube.ffprobe'));
    }

    public function metadata(string $path): array
    {
        if (! $this->probeAvailable()) {
            $metadata = app(Mp4Inspector::class)->inspect($path);
            if ($metadata) {
                return $metadata;
            }
            throw new RuntimeException('FFprobe is unavailable and this file could not be verified as a compatible H.264 MP4. Install FFmpeg and FFprobe, configure FFMPEG_PATH and FFPROBE_PATH, then retry.');
        }

        $process = new Process([config('betube.ffprobe'), '-v', 'error', '-show_format', '-show_streams', '-of', 'json', $path]);
        $process->setTimeout(60);
        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            throw new RuntimeException('The uploaded file could not be read as a video. Upload a valid MP4, MOV, WebM or MKV file.', previous: $exception);
        }
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $stream = collect($data['streams'] ?? [])->first(fn ($item) => ($item['codec_type'] ?? null) === 'video' && empty($item['disposition']['attached_pic']));
        if (! $stream || ($stream['width'] ?? 0) < 2 || ($stream['height'] ?? 0) < 2) {
            throw new RuntimeException('The uploaded file does not contain a readable video track.');
        }
        $duration = (float) ($data['format']['duration'] ?? $stream['duration'] ?? 0);
        if (! is_finite($duration) || $duration <= 0) {
            throw new RuntimeException('The video duration could not be determined. Please export the video again.');
        }
        $audioCompatible = collect($data['streams'] ?? [])->where('codec_type', 'audio')->every(fn ($item) => in_array($item['codec_name'] ?? '', ['aac', 'mp3'], true));
        $rotation = (float) ($stream['tags']['rotate'] ?? collect($stream['side_data_list'] ?? [])->firstWhere('rotation')['rotation'] ?? 0);
        $rotated = abs((int) round($rotation)) % 180 === 90;

        return [
            'duration' => $duration,
            'width' => (int) ($rotated ? $stream['height'] : $stream['width']),
            'height' => (int) ($rotated ? $stream['width'] : $stream['height']),
            'compatible_mp4' => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'mp4' && ($stream['codec_name'] ?? '') === 'h264' && $audioCompatible,
        ];
    }

    public function thumbnail(string $source, string $target, float $seconds): void
    {
        $this->run(['-ss', (string) max(0, $seconds), '-i', $source, '-frames:v', '1', '-vf', 'scale=640:-2:force_original_aspect_ratio=decrease', '-q:v', '3', $target]);
    }

    public function transcode(string $source, string $target, int $height): void
    {
        $this->run(['-i', $source, '-map', '0:v:0', '-map', '0:a:0?', '-vf', 'scale=-2:'.$height, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', '-sn', '-dn', $target]);
    }

    public function variantHeights(int $sourceHeight): array
    {
        $heights = array_values(array_filter(config('betube.variant_heights'), fn ($height) => $height <= $sourceHeight));
        // Small uploads still need a browser-compatible rendition without upscaling.
        return $heights ?: [max(2, (int) floor($sourceHeight / 2) * 2)];
    }

    private function binaryAvailable(string $binary): bool
    {
        try {
            $process = new Process([$binary, '-version']);
            $process->setTimeout(10)->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function run(array $arguments): void
    {
        $process = new Process(array_merge([config('betube.ffmpeg'), '-hide_banner', '-loglevel', 'error', '-nostdin', '-y'], $arguments));
        $process->setTimeout(config('betube.process_timeout'));
        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            throw new RuntimeException('FFmpeg could not process this video. Check that FFmpeg supports libx264 and AAC, and that storage has enough space, then retry.', previous: $exception);
        }
    }
}
