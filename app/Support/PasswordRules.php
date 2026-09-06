<?php

namespace App\Support;

final class PasswordRules
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'min:10', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/'];
    }
}
