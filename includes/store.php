<?php
declare(strict_types=1);

final class Store
{
    public static function path(string $name): string
    {
        return DATA_DIR . '/' . $name . '.json';
    }

    private static function flags(string $name): int
    {
        return JSON_FLAGS;
    }

    public static function get(string $name, $default = [])
    {
        $file = self::path($name);
        if (!is_file($file)) {
            return $default;
        }
        $fp = fopen($file, 'r');
        if (!$fp) {
            return $default;
        }
        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $data = json_decode((string)$raw, true);
        return $data === null ? $default : $data;
    }

    public static function put(string $name, $data): void
    {
        $file = self::path($name);
        $tmp = $file . '.tmp';
        $json = json_encode($data, self::flags($name));
        $fp = fopen($tmp, 'c+');
        if (!$fp) {
            throw new RuntimeException('Cannot write ' . $name);
        }
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        rename($tmp, $file);
    }

    /**
     * Atomic read-modify-write. Callback receives current data and must return new data.
     */
    public static function update(string $name, callable $fn, $default = [])
    {
        $file = self::path($name);
        if (!is_file($file)) {
            file_put_contents($file, json_encode($default, self::flags($name)), LOCK_EX);
        }
        $fp = fopen($file, 'c+');
        if (!$fp) {
            throw new RuntimeException('Cannot open ' . $name);
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $data = json_decode((string)$raw, true);
        if ($data === null) {
            $data = $default;
        }
        $result = $fn($data);
        $json = json_encode($result, self::flags($name));
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $result;
    }
}
