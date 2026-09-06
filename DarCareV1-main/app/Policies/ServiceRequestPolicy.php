<?php

namespace App\Policies;

use App\Modules\Providers\Models\Provider;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Modules\Users\Models\User;

class ServiceRequestPolicy
{
    /**
     * Customer can view his own request.
     * Assigned provider can view the request.
     */
    public function view(mixed $actor, ServiceRequest $serviceRequest): bool
    {
        // Customer
        if ($actor instanceof User && $actor->isCustomer()) {
            return (int) $actor->id === (int) $serviceRequest->user_id;
        }

        // Provider
        if ($actor instanceof Provider) {
            return $serviceRequest->provider_id !== null
                && (int) $actor->id === (int) $serviceRequest->provider_id;
        }

        return false;
    }

    /**
     * Only the assigned provider can update request status.
     */
    public function updateStatus(
        mixed $actor,
        ServiceRequest $serviceRequest
    ): bool {
        // لازم يكون الشخص الحالي Provider
        if (! $actor instanceof Provider) {
            return false;
        }

        // لازم يكون هذا الطلب مسند له
        if ($serviceRequest->provider_id === null) {
            return false;
        }

        return (int) $actor->id === (int) $serviceRequest->provider_id;
    }

    /**
     * Only customer User can create requests.
     */
    public function create(mixed $actor): bool
    {
        return $actor instanceof User && $actor->isCustomer();
    }
}