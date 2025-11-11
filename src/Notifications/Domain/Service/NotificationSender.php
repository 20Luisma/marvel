<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Service;

interface NotificationSender
{
    public function send(string $message): void;
}
