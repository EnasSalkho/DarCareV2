<?php

namespace App\Policies;

use App\Modules\Providers\Models\Provider;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Modules\Users\Models\User;

class ServiceRequestPolicy
{
    /**
     * Customer owns the request, or assigned provider. Admins are not covered here.
     */
    public function view(mixed $actor, ServiceRequest $serviceRequest): bool
    {
        if ($actor instanceof User && $actor->isCustomer()) {
            return (int) $actor->id === (int) $serviceRequest->user_id;
        }

        if ($actor instanceof Provider) {
            return $serviceRequest->provider_id !== null
                && (int) $actor->id === (int) $serviceRequest->provider_id;
        }

        return false;
    }

    /**
     * Only the assigned provider may update status.
     */
    public function updateStatus(mixed $actor, ServiceRequest $serviceRequest): bool
    {
        if (! $actor instanceof Provider) {
            return false;
        }

        return $serviceRequest->provider_id !== null
            && (int) $actor->id === (int) $serviceRequest->provider_id;
    }

    /**
     * Only a customer User (role = user) may create requests.
     */
    public function create(mixed $actor): bool
    {
        return $actor instanceof User && $actor->isCustomer();
    }
}
