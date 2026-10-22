<?php

namespace App\Services\Social;

use App\Services\Social\Contracts\SocialPublisherInterface;
use App\Services\Social\Publishers\FacebookPublisher;
use App\Services\Social\Publishers\InstagramPublisher;
use App\Services\Social\Publishers\LinkedInPublisher;
use App\Services\Social\Publishers\PinterestPublisher;
use App\Services\Social\Publishers\TelegramPublisher;
use App\Services\Social\Publishers\TikTokPublisher;
use App\Services\Social\Publishers\WhatsAppPublisher;
use App\Services\Social\Publishers\XPublisher;
use App\Services\Social\Publishers\YouTubePublisher;
use InvalidArgumentException;

class SocialPublisherManager
{
    /** @var array<string, SocialPublisherInterface> */
    protected array $publishers = [];

    public function __construct()
    {
        $this->registerPublisher('facebook', new FacebookPublisher());
        $this->registerPublisher('instagram', new InstagramPublisher());
        $this->registerPublisher('youtube', new YouTubePublisher());
        $this->registerPublisher('x', new XPublisher());
        $this->registerPublisher('pinterest', new PinterestPublisher());
        $this->registerPublisher('tiktok', new TikTokPublisher());
        $this->registerPublisher('linkedin', new LinkedInPublisher());
        $this->registerPublisher('whatsapp', new WhatsAppPublisher());
        $this->registerPublisher('telegram', new TelegramPublisher());
    }

    public function registerPublisher(string $platformCode, SocialPublisherInterface $publisher): void
    {
        $this->publishers[$platformCode] = $publisher;
    }

    public function getPublisher(string $platformCode): SocialPublisherInterface
    {
        if (! isset($this->publishers[$platformCode])) {
            throw new InvalidArgumentException("Bilinmeyen veya desteklenmeyen sosyal medya platformu: '{$platformCode}'");
        }

        return $this->publishers[$platformCode];
    }

    public function getSupportedPlatforms(): array
    {
        return array_keys($this->publishers);
    }
}
