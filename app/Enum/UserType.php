<?php

namespace App\Enum;

enum UserType: string
{
    case App = 'app';
    case Staff = 'staff';
    case Guest = 'guest';
}
