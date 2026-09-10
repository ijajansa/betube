<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('avatar_path')->nullable());

        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('handle', 50)->unique();
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('visibility', 16)->default('public')->index();
            $table->unsignedBigInteger('subscriber_count')->default(0);
            $table->unsignedBigInteger('video_count')->default(0);
            $table->unsignedBigInteger('total_views')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('title', 200);
            $table->string('slug', 240)->index();
            $table->text('description')->nullable();
            $table->string('visibility', 16)->default('private');
            $table->string('processing_status', 16)->default('pending');
            $table->string('upload_status', 16)->default('draft');
            $table->string('storage_disk', 40)->default('local');
            $table->string('original_path')->nullable();
            $table->string('playback_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->json('generated_thumbnails')->nullable();
            $table->double('duration')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('dislike_count')->default(0);
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->boolean('age_restricted')->default(false);
            $table->boolean('comments_enabled')->default(true);
            $table->boolean('embedding_enabled')->default(true);
            $table->boolean('is_blocked')->default(false);
            $table->text('processing_error')->nullable();
            $table->unsignedTinyInteger('processing_progress')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['visibility', 'processing_status', 'upload_status', 'published_at'], 'videos_public_feed_index');
            $table->index(['category_id', 'visibility', 'published_at'], 'videos_category_feed_index');
            $table->index(['channel_id', 'deleted_at', 'created_at']);
            $table->index(['view_count', 'published_at']);
        });

        Schema::create('video_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('label', 20);
            $table->string('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->default('video/mp4');
            $table->timestamps();
            $table->unique(['video_id', 'label']);
        });

        Schema::create('video_tag', function (Blueprint $table) {
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['video_id', 'tag_id']);
            $table->index(['tag_id', 'video_id']);
        });

        Schema::create('video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('viewer_hash', 64);
            $table->unsignedBigInteger('bucket');
            $table->double('watched_seconds')->default(0);
            $table->double('last_position')->default(0);
            $table->timestamps();
            $table->unique(['video_id', 'viewer_hash', 'bucket'], 'video_view_window_unique');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('watch_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->double('position')->default(0);
            $table->double('duration')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'video_id']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('video_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->timestamps();
            $table->unique(['user_id', 'video_id']);
            $table->index(['video_id', 'type']);
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('active_channel_id')->nullable()->constrained('channels')->nullOnDelete();
            $table->string('theme', 10)->default('system');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['user_settings', 'video_reactions', 'watch_progress', 'video_views', 'video_tag', 'video_variants', 'videos', 'tags', 'categories', 'channels'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
    }
};
