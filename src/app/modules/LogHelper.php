<?php
namespace app\modules;

use Throwable;
use php\time\Time;
use php\lib\fs;

class LogHelper
{
    static function log($msg)
    {
        try {
            $logDir = getenv('APPDATA') . '\\.mineshit\\logs\\';
            if (!fs::isDir($logDir)) {
                fs::makeDir($logDir, 0777, true);
            }
            $dateForLog = Time::now()->toString('yyyy-MM-dd');
            $logFile = $logDir . 'launcher_' . $dateForLog . '.log';
            $timeForLog = Time::now()->toString('yyyy-MM-dd HH:mm:ss');
            $line = "[$timeForLog] $msg\n";
            file_put_contents($logFile, $line, FILE_APPEND);
        } catch (\Throwable $e) {
            // Можно вывести alert или просто игнорировать (чтоб не крашился основной поток)
        }
    }
}