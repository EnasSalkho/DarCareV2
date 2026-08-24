<?php
// app/Enums/RoleEnum.php

namespace App\Enums;

enum RoleEnum: string
{
    case User     = 'user';
    case Provider = 'provider';
    case Admin    = 'admin';
}
