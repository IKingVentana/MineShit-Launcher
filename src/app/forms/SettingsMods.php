<?php
namespace app\forms;

use std, gui, framework, app;
use php\gui\event\UXMouseEvent;

class SettingsMods extends AbstractForm
{
    public $modsList = [
        "DistantHorizon",
        "VoiceChat",
        "EmojiType",
        "CameraOverhaul",
        "JEI",
        "XaerosWorldMap",
        "XaerosMinimap",
        "Emotes"
    ];
    public $defaultMods = [
        "DistantHorizon"   => false,
        "VoiceChat"        => true,
        "EmojiType"        => true,
        "CameraOverhaul"   => false,
        "JEI"              => true,
        "XaerosWorldMap"   => true,
        "XaerosMinimap"    => true,
        "Emotes" => true
    ];

    /**
     * Восстановить чекбоксы из файла или по умолчанию
     */
    public function syncCheckboxes()
    {
        $appdata = getenv('APPDATA');
        $gameDir = $appdata . "\\.mineshit\\game";
        $configFile = $gameDir . "\\mods_config.json";
        $modsConfig = $this->defaultMods;

        // Прочитать файл, если он есть
        if (file_exists($configFile)) {
            $fileData = json_decode(file_get_contents($configFile), true);
            if (is_array($fileData)) {
                $modsConfig = array_merge($modsConfig, $fileData);
            }
        } else {
            // Файла нет — создаём по умолчанию!
            if (!is_dir($gameDir)) mkdir($gameDir, 0777, true);
            file_put_contents($configFile, json_encode($modsConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Ставим значения чекбоксов
        foreach ($this->modsList as $modId) {
            if (isset($this->{$modId}) && $this->{$modId} instanceof \php\gui\UXCheckBox) {
                $this->{$modId}->selected = (bool)$modsConfig[$modId];
            }
        }
    }

    /**
     * @event ApplyModsButton.click-Left
     */
    function doApplyModsButtonClickLeft(\php\gui\event\UXMouseEvent $e = null)
    {
        $selectedMods = [];
        foreach ($this->modsList as $modId) {
            if (isset($this->{$modId}) && $this->{$modId} instanceof \php\gui\UXCheckBox) {
                $selectedMods[$modId] = (bool)$this->{$modId}->selected;
            } else {
                $selectedMods[$modId] = false;
            }
        }

        $appdata = getenv('APPDATA');
        $gameDir = $appdata . "\\.mineshit\\game";
        $configFile = $gameDir . "\\mods_config.json";
        if (!is_dir($gameDir)) mkdir($gameDir, 0777, true);
        file_put_contents($configFile, json_encode($selectedMods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Скрываем фрагмент если надо:
        if (isset($this->form->ModSettingsFragment)) {
            $this->form->ModSettingsFragment->visible = false;
        }
    }

    /**
     * @event show
     */
    function doShow($e = null) {
        $this->syncCheckboxes();
    }
}
