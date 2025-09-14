<?php
namespace app\forms;

use std, gui, framework, app;
use bundle\updater\GitHubUpdater;

class Update extends AbstractForm
{
    /**
     * @event show
     */
    function doShow(\php\gui\event\UXEvent $e = null)
    {
        $updater = new GitHubUpdater('IKingVentana', 'MineShit-Launcher');
        // Опционально: $updater->setCurrentVersion(...);
        $updater->checkUpdates(function ($needUpdate, $release) {
            if ($needUpdate) {
                $this->Description->text = $release['version'] . ' - ' . $release['description'];
            } else {
                $this->Description->text = 'Обновлений не найдено';
            }
        });
    }

    /**
     * @event ActiveUpdateButton.click
     */
    function doActiveUpdateButtonClick($e = null)
    {
        // Логика запуска Updater.jar, как у тебя было
        $version = \app\modules\AppVersion::get();
        $exeName = "MineShit Launcher.exe";
        execute("java -jar Updater.jar {$version} {$exeName}");
        app()->shutdown();
    }
}