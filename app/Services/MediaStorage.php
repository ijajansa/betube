<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaStorage
{
    public function workingDirectory(Video $video): string
    {
        $directory = storage_path('app/processing/'.$video->uuid.'-'.Str::uuid());
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the local processing directory. Check storage permissions.');
        }

        return $directory;
    }

    public function stage(Video $video, string $directory): string
    {
        $extension = pathinfo($video->original_path, PATHINFO_EXTENSION);
        $path = $directory.'/source.'.$extension;
        $source = Storage::disk($video->storage_disk)->readStream($video->original_path);
        if (! is_resource($source)) {
            throw new RuntimeException('The source upload is missing or unreadable.');
        }
        $target = fopen($path, 'wb');
        try {
            if (! is_resource($target) || stream_copy_to_stream($source, $target) === false) {
                throw new RuntimeException('Could not stage this upload. Check local storage space.');
            }
        } finally {
            fclose($source);
            if (is_resource($target)) {
                fclose($target);
            }
        }

        return $path;
    }

    public function put(Video $video, string $localPath, string $filename): string
    {
        $path = 'videos/'.$video->uuid.'/'.$filename;
        $stream = fopen($localPath, 'rb');
        if (! is_resource($stream)) {
            throw new RuntimeException('A processed file could not be read.');
        }
        try {
            if (! Storage::disk($video->storage_disk)->put($path, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('A processed file could not be stored. Check the configured media disk.');
            }
        } finally {
            fclose($stream);
        }

        return $path;
    }

    public function cleanup(string $directory): void
    {
        // Only remove this job's own flat staging directory, never a user-supplied path.
        $root = realpath(storage_path('app/processing'));
        $target = realpath($directory);
        if (! $root || ! $target || ! str_starts_with($target, $root.DIRECTORY_SEPARATOR)) {
            return;
        }
        foreach (glob($target.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($target);
    }
}
