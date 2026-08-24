<?php
// app/Enums/ProviderStatusEnum.php

namespace App\Enums;

enum ProviderStatusEnum: string
{
    case Available = 'available';
    case Busy      = 'busy';
    case Suspended = 'suspended'; // الحالة الجديدة الخاصة بالأدمن
}
