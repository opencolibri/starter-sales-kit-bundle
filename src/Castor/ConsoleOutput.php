<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Castor;

final class ConsoleOutput
{
    public static function withoutProgressBars(string $output): string
    {
        $withoutAnsi = preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $output) ?? $output;
        $normalized = str_replace(["\r\n", "\r"], "\n", $withoutAnsi);
        $lines = explode("\n", $normalized);
        $kept = [];

        foreach ($lines as $line) {
            if (self::isProgressBarLine($line)) {
                continue;
            }

            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }

    private static function isProgressBarLine(string $line): bool
    {
        return 1 === preg_match('/^\s*\d+\s*\/\s*\d+\s*\[.*\]\s*\d+\s*%/', $line);
    }
}
