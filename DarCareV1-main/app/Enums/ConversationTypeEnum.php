<?php

namespace App\Enums;

enum ConversationTypeEnum: string
{
    case Request = 'request';
    case SupportCustomer = 'support_customer';
    case SupportProvider = 'support_provider';
    case Direct = 'direct';
}
