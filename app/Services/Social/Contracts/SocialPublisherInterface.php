<?php

namespace App\Services\Social\Contracts;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\Video;

interface SocialPublisherInterface
{
    /**
     * Publish video to the platform with target caption and metadata
     * Returns array with 'post_id', 'post_url', 'status'
     */
    public function publish(Video $video, ScheduledPostTarget $target, SocialAccount $account): array;

    /**
     * Validate whether video media meets platform technical specifications
     */
    public function validateMedia(Video $video): array;

    /**
     * Check if account connection and access token are valid
     */
    public function validateConnection(SocialAccount $account): bool;

    /**
     * Refresh OAuth access token if supported and needed
     */
    public function refreshToken(SocialAccount $account): bool;

    /**
     * Fetch analytics metrics (views, likes, comments, shares)
     */
    public function getAnalytics(string $externalPostId, SocialAccount $account): array;

    /**
     * Check remaining daily rate limits
     */
    public function getRateLimitStatus(SocialAccount $account): array;
}
