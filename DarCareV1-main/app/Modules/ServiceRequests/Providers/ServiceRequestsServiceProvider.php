<?php
// app/Modules/ServiceRequests/Providers/ServiceRequestsServiceProvider.php

namespace App\Modules\ServiceRequests\Providers;

use App\Modules\ServiceRequests\Contracts\ServiceRequestServiceInterface;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Modules\ServiceRequests\Services\ServiceRequestService;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ServiceRequestsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ServiceRequestServiceInterface::class, ServiceRequestService::class);
    }

    public function boot(): void
    {
        Gate::policy(ServiceRequest::class, ServiceRequestPolicy::class);
    }
}
