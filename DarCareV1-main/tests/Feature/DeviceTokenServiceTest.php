<?php

use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Notifications\Services\DeviceTokenService;

it('transfers device token ownership in the service layer', function () {
    $customerA = createCustomer();
    $customerB = createCustomer();

    $service = app(DeviceTokenService::class);

    $first = $service->register($customerA, 'transfer-token', 'android');
    expect($first->tokenable_id)->toBe($customerA->id);

    $second = $service->register($customerB, 'transfer-token', 'ios', 'Phone B');

    expect(DeviceToken::count())->toBe(1);
    expect($second->tokenable_id)->toBe($customerB->id);
    expect($second->platform)->toBe('ios');
    expect($second->device_name)->toBe('Phone B');
});
