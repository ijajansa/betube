<?php

return [
    // Video files and thumbnails are private; MediaController checks access on every request.
    'media_disk' => env('BETUBE_MEDIA_DISK', 'local'),
    'max_upload_mb' => (int) env('BETUBE_MAX_UPLOAD_MB', 1024),
    'ffmpeg' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),
    'process_timeout' => (int) env('FFMPEG_TIMEOUT', 1800),
    'variant_heights' => [360, 480, 720],
    'view_dedup_minutes' => 30,
];
