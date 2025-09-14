<?php
namespace app\modules;

use std, gui, framework, app;
use Throwable;
use php\lib\fs;
use php\io\File;
use php\lang\Thread;
use php\lang\System;
use php\compress\ZipFile;

class DownloaderModule extends AbstractModule
{
    /**
     * Скачивание архива (простой вариант, для совместимости)
     */
    public static function downloadZip($url, $localPath, $onProgress = null): void
    {
        fs::copy($url, $localPath);
    }

    /**
     * Распаковка архива
     */
    public static function extractZip($zip, $dest): void
    {
        $zipFile = new ZipFile($zip);
        $zipFile->unpack($dest);
        fs::delete($zip);
    }

    /**
     * Проверка установки клиента
     */
    public static function isInstalled($appdata): bool
    {
        return is_dir($appdata . "\\.mineshit\\game")
            && is_file($appdata . "\\.mineshit\\jre\\java-runtime-gamma\\windows-x64\\java-runtime-gamma\\bin\\javaw.exe");
    }

    /**
     * Корректное кодирование имени файла для URL (оставляем /)
     */
    public static function encodeFileNameForUrl(string $fileName): string
    {
        return str_replace('%2F', '/', rawurlencode($fileName));
    }

    /**
     * Потоковая загрузка файла на диск с ретраями и .part
     * Возвращает true при успехе.
     */
    public static function downloadFile(string $url, string $dest, string $label = '', int $retries = 3): bool
    {
        $tmp = $dest . '.part';

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                if (file_exists($tmp)) @unlink($tmp);

                LogHelper::log("Загрузка [$label] попытка {$attempt}/{$retries}: $url -> $dest");

                // В JPHP fs::copy читает потоково, без загрузки в память целиком
                fs::copy($url, $tmp);

                $size = file_exists($tmp) ? filesize($tmp) : 0;
                if ($size <= 0) {
                    throw new \Exception("Пустой файл после загрузки [$label]");
                }

                if (file_exists($dest)) @unlink($dest);
                @rename($tmp, $dest);

                LogHelper::log("✅ downloadFile OK [$label], {$size} байт");
                return true;
            } catch (\Throwable $e) {
                LogHelper::log("Ошибка загрузки [$label]: " . $e->getMessage());
                @unlink($tmp);
                Thread::sleep(1000 * $attempt); // простой backoff
            }
        }
        return false;
    }

    /**
     * "Семейный" ключ файла — чтобы определять старые/новые версии одного и того же мода.
     * Например: L_Enders_Cataclysm-3.15.jar и L_Enders_Cataclysm-3.08.jar -> один familyKey.
     */
    // --- utils без preg ---
    private static function endsWith(string $s, string $suffix): bool {
        $ls = strlen($s); $lf = strlen($suffix);
        return $lf <= $ls && substr($s, $ls - $lf) === $suffix;
    }
    private static function toLower(string $s): string { return \php\lib\str::lower($s); }
    
    // Флэттим allowList до массива строк
    private static function flattenAllowList($x): array {
        $out = [];
        $stack = [$x];
        while (!empty($stack)) {
            $cur = array_pop($stack);
            if (is_array($cur)) {
                foreach ($cur as $v) $stack[] = $v;
            } else if (is_string($cur) && $cur !== '') {
                $out[] = $cur;
            }
        }
        return $out;
    }
    
    // [1,7,3] для "mowziesmobs-1.7.3"
    private static function extractVersionParts(string $fn): array {
        $name = self::toLower($fn);
        // убираем .jar вручную
        if (self::endsWith($name, '.jar')) $name = substr($name, 0, strlen($name) - 4);
    
        // ищем с КОНЦА последний “цифры и точки”
        $best = '';
        $run  = '';
        for ($i = strlen($name)-1; $i >= 0; $i--) {
            $ch = $name[$i];
            $isDigit = ($ch >= '0' && $ch <= '9') || $ch === '.';
            if ($isDigit) {
                $run = $ch . $run;
            } else {
                if ($run !== '') { $best = $run; break; }
            }
        }
        if ($run !== '' && $best === '') $best = $run;
    
        if ($best === '') return [];
        $parts = explode('.', $best);
        $out = [];
        foreach ($parts as $p) {
            if ($p === '') continue;
            $out[] = (int)$p;
        }
        return $out;
    }
    
    private static function cmpVersion(array $a, array $b): int {
        $n = max(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $ai = $a[$i] ?? 0; $bi = $b[$i] ?? 0;
            if ($ai < $bi) return -1;
            if ($ai > $bi) return 1;
        }
        return 0;
    }
    
    // Ключ семейства: имя без заключительного блока версии
    public static function familyKey($fileName): string {
        if (!is_string($fileName) || $fileName === '') return '';
        $base = self::toLower($fileName);
        if (self::endsWith($base, '.jar')) $base = substr($base, 0, strlen($base)-4);
    
        // срезаем с конца "...-1.2.3" / "..._1.2.3" / "...+1.2.3"
        $i = strlen($base) - 1;
        $seenDigit = false;
        while ($i >= 0) {
            $ch = $base[$i];
            if (($ch >= '0' && $ch <= '9') || $ch === '.') { $seenDigit = true; $i--; }
            else break;
        }
        if ($seenDigit) {
            if ($i >= 0 && ($base[$i] === '-' || $base[$i] === '_' || $base[$i] === '+')) $i--;
            $base = substr($base, 0, $i + 1);
        }
    
        // нормализуем: не a-z0-9 -> '_'
        $norm = '';
        $prevU = false;
        for ($k = 0, $L = strlen($base); $k < $L; $k++) {
            $ch = $base[$k];
            $isAlpha = ($ch >= 'a' && $ch <= 'z');
            $isNum   = ($ch >= '0' && $ch <= '9');
            if ($isAlpha || $isNum) { $norm .= $ch; $prevU = false; }
            else if (!$prevU) { $norm .= '_'; $prevU = true; }
        }
        return trim($norm, '_');
    }
    
    // ГЛАВНАЯ ЧИСТИЛКА — безопасная
    public static function cleanModsDirectory(string $modsDir, array $allowList): void
    {
        try {
            if (!fs::isDir($modsDir)) { LogHelper::log("cleanModsDirectory: нет папки $modsDir"); return; }
    
            // 0) готовим allow: плоский список строк
            $allowFlat = self::flattenAllowList($allowList);
            if (count($allowFlat) === 0) { LogHelper::log("cleanModsDirectory: пустой allowList — ничего не удаляем"); return; }
    
            // 1) группируем по семействам и выбираем победителя (самую новую версию)
            $groups = []; // fam => [bestName, bestVerParts]
            foreach ($allowFlat as $fn) {
                if (!is_string($fn) || $fn === '') continue;
                $fam = self::familyKey($fn);
                if ($fam === '') continue;
                $ver = self::extractVersionParts($fn);
                if (!isset($groups[$fam])) {
                    $groups[$fam] = [$fn, $ver];
                } else {
                    $cur = $groups[$fam];
                    $cmp = self::cmpVersion($ver, $cur[1]);
                    if ($cmp > 0 || ($cmp === 0 && strcmp($fn, $cur[0]) > 0)) {
                        $groups[$fam] = [$fn, $ver];
                    }
                }
            }
            // победители
            $winners = [];
            foreach ($groups as $fam => $pair) $winners[$fam] = $pair[0];
    
            // карты для быстрых проверок (нижний регистр)
            $winnerExact = [];
            foreach ($winners as $fn) $winnerExact[self::toLower($fn)] = true;
            $winnerFam = array_fill_keys(array_keys($winners), true);
    
            // 2) сканируем mods
            $scan = null;
            try { $scan = fs::scan($modsDir); }
            catch (\Throwable $e) { LogHelper::log("cleanModsDirectory: fs::scan error: ".$e->getMessage()); $scan = @scandir($modsDir) ?: []; }
            if (!is_array($scan)) return;
    
            foreach ($scan as $name) {
                $path = rtrim($modsDir, "\\/") . DIRECTORY_SEPARATOR . $name;
                if (!fs::isFile($path)) continue;
    
                $lower = self::toLower($name);
    
                // мусорные хвосты — удаляем всегда
                if (self::endsWith($lower, '.part') || self::endsWith($lower, '.tmp') || self::endsWith($lower, '.old') || self::endsWith($lower, '.bak')) {
                    $ok = fs::delete($path);
                    LogHelper::log(($ok ? "Удалил" : "Не удалил") . " временный файл: $name");
                    continue;
                }
    
                // БЕЗОПАСНОСТЬ: не .jar — НЕ ТРОГАЕМ (никаких “без .jar” больше)
                if (!self::endsWith($lower, '.jar')) continue;
    
                // точное совпадение с победителем — оставляем
                if (isset($winnerExact[$lower])) continue;
    
                // проверим семейство
                $fam = self::familyKey($name);
                if (isset($winnerFam[$fam])) {
                    $ok = fs::delete($path);
                    LogHelper::log(($ok ? "Удалил" : "Не удалил") . " старую версию (семейство '$fam'): $name");
                } else {
                    $ok = fs::delete($path);
                    LogHelper::log(($ok ? "Удалил" : "Не удалил") . " лишний мод: $name");
                }
            }
        } catch (\Throwable $e) {
            LogHelper::log("⚠ cleanModsDirectory: ".$e->getMessage());
        }
    }
}
