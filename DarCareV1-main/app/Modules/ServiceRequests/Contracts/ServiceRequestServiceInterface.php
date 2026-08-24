<?php
// app/Modules/ServiceRequests/Contracts/ServiceRequestServiceInterface.php

namespace App\Modules\ServiceRequests\Contracts;

interface ServiceRequestServiceInterface
{
    public function createRequest(int $userId, array $data): object;

    public function getUserRequests(int $userId): mixed;

    public function getProviderRequests(int $providerId): mixed;

    public function updateStatus(int $requestId, int $providerId, string $status, ?string $scheduledAt): object;

    public function find(int $requestId): object;

    public function getAllRequestsForAdmin(?string $status, ?string $urgency): mixed;

    public function getRequestDetailsForAdmin(int $requestId): object;

    public function reassignProvider(int $requestId, int $providerId): object;
}
