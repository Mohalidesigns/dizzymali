<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Tailor = 'tailor';
    case Staff = 'staff';
    case Admin = 'admin';
    case SuperAdmin = 'super-admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Tailor => 'Tailor',
            self::Staff => 'Staff',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super admin',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }

    /** @return list<string> Roles that may use the admin panel. */
    public static function backOffice(): array
    {
        return [self::Tailor->value, self::Staff->value, self::Admin->value, self::SuperAdmin->value];
    }
}
