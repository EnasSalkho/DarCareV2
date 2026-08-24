<?php
// app/Modules/Chat/Contracts/ChatServiceInterface.php

namespace App\Modules\Chat\Contracts;

interface ChatServiceInterface
{
    public function getMessages(int $requestId): mixed;
    public function sendMessage(int $requestId, object $sender, string $body): object;
    
}
