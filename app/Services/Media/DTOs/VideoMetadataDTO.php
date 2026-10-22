<?php

namespace App\Services\Media\DTOs;

class VideoMetadataDTO
{
    public function __construct(
        public string $fileHash,
        public int $fileSize,
        public int $durationSeconds,
        public int $width,
        public int $height,
        public string $aspectRatio,
        public float $fps,
        public string $mimeType,
        public ?string $thumbnailPath = null,
        public array $rawMetadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'file_hash' => $this->fileHash,
            'file_size' => $this->fileSize,
            'duration_seconds' => $this->durationSeconds,
            'width' => $this->width,
            'height' => $this->height,
            'aspect_ratio' => $this->aspectRatio,
            'fps' => $this->fps,
            'mime_type' => $this->mimeType,
            'thumbnail_path' => $this->thumbnailPath,
            'technical_metadata' => $this->rawMetadata,
        ];
    }
}
