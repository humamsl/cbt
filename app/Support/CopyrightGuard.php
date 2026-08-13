<?php

namespace App\Support;

class CopyrightGuard
{
    private const TOKEN = 'QW5vbnltb3VTTA==';

    private static ?bool $result = null;

    public static function owner(): string
    {
        return base64_decode(self::TOKEN);
    }

    private static function guardedFiles(): array
    {
        return [
            resource_path('views/auth/login.blade.php'),
        ];
    }

    public static function passes(): bool
    {
        if (self::$result !== null) {
            return self::$result;
        }

        $owner = self::owner();
        if ($owner === '') {
            return self::$result = false;
        }

        foreach (self::guardedFiles() as $file) {
            if (! is_file($file)) {
                return self::$result = false;
            }

            $contents = @file_get_contents($file);
            if ($contents === false || ! str_contains($contents, $owner)) {
                return self::$result = false;
            }
        }

        return self::$result = true;
    }
}
