<?php
declare(strict_types=1);
namespace Drebedengi;

/** Reads single-line KEY=value entries without interpolation or global environment changes. */
final class EnvFile
{
    public static function read(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        $text = @file_get_contents($path);
        if ($text === false) {
            throw new \RuntimeException('Cannot read .env file.');
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $values = [];
        foreach (preg_split('/\r\n|\n|\r/', $text) as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/D', $line, $match)) {
                throw new \InvalidArgumentException('Invalid .env syntax at line ' . ($index + 1));
            }
            $value = $match[2];
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $end = strpos($value, $quote, 1);
                if ($end === false || !preg_match('/^\s*(?:#.*)?$/D', substr($value, $end + 1))) {
                    throw new \InvalidArgumentException('Invalid .env quoting at line ' . ($index + 1));
                }
                $value = substr($value, 1, $end - 1);
            } else {
                $value = rtrim(preg_replace('/\s+#.*$/', '', $value));
            }
            $values[$match[1]] = $value;
        }
        return $values;
    }
}
