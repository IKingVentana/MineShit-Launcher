<?php
namespace app\forms;

use Exception;
use bundle\updater\UpdateMe;
use std, gui, framework, app;
use php\desktop\SystemDesktop;
use bundle\updater\GitHubUpdater;
use php\io\File;
use php\lib\fs;
use php\lang\System;
use php\gui\dialog\UXDialog;
use php\gui\UXDialog;
use app\modules\AppVersion;


class Settings extends AbstractForm
{

private $previousForm = null;
private $previousX = 0;
private $previousY = 0;


public function setPreviousForm($formName)
{
    $this->previousForm = $formName;

    $form = app()->getForm($formName);
    $this->previousX = $form->x;
    $this->previousY = $form->y;

    // Ставим настройки в то же место
    $this->x = $this->previousX;
    $this->y = $this->previousY;
}

/**
 * @event ExitButton.click-Left
 */
function doExitButtonClickLeft(UXMouseEvent $e = null)
{
    if ($this->previousForm) {
        $form = app()->getForm($this->previousForm);
        $form->show();
    }
    $this->hide();
}

    
    
    /**
     * ОПЕРАТИВНАЯ ПАМЯТЬ -----------------------------------------------------------------------------------------------------------------------------------------------
     */
     
     
    /**
     * RAM-кнопки — список ID, можно редактировать
     */
    private $ramButtons = ['RamButton3', 'RamButton4', 'RamButton6', 'RamButton8', 'RamButton10'];
    private $selectedRamMb = 4096; // по умолчанию 4GB

    /**
     * При открытии формы - прочитать ram.txt и выделить нужную кнопку
     */
    function __construct()
    {
        parent::__construct();
        $this->initRamButtons();
    }

    /**
     * Прочитать файл ram.txt и обновить выделение кнопки
     */
    function initRamButtons()
    {
        $ramPath = getenv('APPDATA') . "/.mineshit/ram.txt";
        if (file_exists($ramPath)) {
            $value = trim(file_get_contents($ramPath)); // убирает пробелы и переносы
            $this->selectedRamMb = (int)$value;
        } else {
            $this->selectedRamMb = 4096;
        }
        $mbToButton = [
            3072 => 'RamButton3',
            4096 => 'RamButton4',
            6144 => 'RamButton6',
            8192 => 'RamButton8',
            10240 => 'RamButton10',
        ];
        // Сбросить все стили
        foreach ($this->ramButtons as $btnId) {
            $this->{$btnId}->classes->remove('ram-active');
            $this->{$btnId}->classes->add('ram-inactive');
        }
        // Подсветить нужную кнопку
        $id = isset($mbToButton[$this->selectedRamMb]) ? $mbToButton[$this->selectedRamMb] : 'RamButton4';
        $this->{$id}->classes->remove('ram-inactive');
        $this->{$id}->classes->add('ram-active');
    }
    
    /**
     * @event show
     */
    function doShow(\php\gui\event\UXEvent $e = null)
    {
        $this->initRamButtons();
    }

    /**
     * Выбор RAM, сохранение и визуальная подсветка
     */
    function handleRamButtonClick(string $id, int $mb)
    {
        // Снять выделение со всех кнопок
        foreach ($this->ramButtons as $btnId) {
            $this->{$btnId}->classes->remove('ram-active');
            $this->{$btnId}->classes->add('ram-inactive');
        }
        // Выделить текущую
        $this->{$id}->classes->remove('ram-inactive');
        $this->{$id}->classes->add('ram-active');
        // Сохранить в файл
        $this->selectedRamMb = $mb;
        file_put_contents(getenv('APPDATA') . "/.mineshit/ram.txt", $mb);
    }

    /**
     * @event RamButton3.click-Left
     */
    function doRamButton3ClickLeft(UXMouseEvent $e = null)
    {
        $this->handleRamButtonClick('RamButton3', 3072);
    }

    /**
     * @event RamButton4.click-Left
     */
    function doRamButton4ClickLeft(UXMouseEvent $e = null)
    {
        $this->handleRamButtonClick('RamButton4', 4096);
    }

    /**
     * @event RamButton6.click-Left
     */
    function doRamButton6ClickLeft(UXMouseEvent $e = null)
    {
        $this->handleRamButtonClick('RamButton6', 6144);
    }

    /**
     * @event RamButton8.click-Left
     */
    function doRamButton8ClickLeft(UXMouseEvent $e = null)
    {
        $this->handleRamButtonClick('RamButton8', 8192);
    }

    /**
     * @event RamButton10.click-Left
     */
    function doRamButton10ClickLeft(UXMouseEvent $e = null)
    {
        $this->handleRamButtonClick('RamButton10', 10240);
    }

    /**
     * Можно добавить вызов initRamButtons() в onShow/onInit для автоматической инициализации при показе формы.
     */
    function onShow()
    {
        $this->initRamButtons();
    }
            
    /**
     * ДРУГОЕ -----------------------------------------------------------------------------------------------------------------------------------------------
     */

    /**
     * @event InfoButton.click-Left 
     */
    function doInfoButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }

    /**
     * @event DeleteClientButton.click-Left
     */
    function doDeleteClientButtonClickLeft(\php\gui\event\UXMouseEvent $e = null)
    {
        $env = System::getEnv();
        $clientPath = $env['APPDATA'] . '\\.mineshit';
    
        // Показываем диалог с вопросом и типом CONFIRMATION (вопросительный знак)
        $answer = UXDialog::showAndWait('Вы действительно хотите удалить клиент?', 'CONFIRMATION');
    
        if ($answer === 'OK') {
            try {
                if (fs::isDir($clientPath)) {
                    fs::delete($clientPath);
                    UXDialog::showAndWait('Клиент успешно удалён.', 'INFORMATION');
                } else {
                    UXDialog::showAndWait('Папка клиента не найдена.', 'ERROR');
                }
            } catch (Exception $ex) {
                UXDialog::showAndWait("Ошибка при удалении:\n" . $ex->getMessage(), 'ERROR');
            }
        }
    }

    /**
     * @event FolderButton.click-Left 
     */
    function doFolderButtonClickLeft(UXMouseEvent $e = null)
    {
        $gameFolder = getenv('APPDATA') . '\\.mineshit\\game\\';
    
        if (fs::isDir($gameFolder)) {
            // Открываем папку в Проводнике
            execute('explorer "' . $gameFolder . '"');
        } else {
            alert("Папка не найдена:\n" . $gameFolder);
        }
    }

    /**
     * @event ChangePutButton.click-Left 
     */
    function doChangePutButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }

/**
 * @event show
 */
function doShow(\php\gui\event\UXEvent $e = null)
{
    $this->initRamButtons();

    $updater = new \bundle\updater\GitHubUpdater('IKingVentana', 'MineShit-Launcher');
    $updater->setCurrentVersion(\app\modules\AppVersion::get());
    $updater->checkUpdates(function ($needUpdate, $release) {
        $this->UpdateIndicator->visible = $needUpdate;
        $this->_latestUpdate = $needUpdate ? $release : null;
    });
}

/**
 * @event UpdateButton.click-Left
 */
function doUpdateButtonClickLeft(UXMouseEvent $e = null)
{
    // Скрываем оба окна если один из них уже открыт (переключение)
    if ($this->UpdFrame->visible || $this->NoUpdFrame->visible) {
        $this->UpdFrame->visible = false;
        $this->NoUpdFrame->visible = false;
        return;
    }

    if (isset($this->_latestUpdate) && $this->_latestUpdate) {
        $this->UpdFrame->fragment = 'Update';
        // Ждем пока фрагмент загрузится
        uiLater(function() {
            $form = $this->UpdFrame->form;
            if ($form && method_exists($form, 'setDescription')) {
                $release = $this->_latestUpdate;
                $form->setDescription($release['version'], $release['description']);
            } else {
                alert('Ошибка: Description или setDescription не найдены во фрагменте Update.');
            }
            $this->UpdFrame->visible = true;
        });
    } else {
        $this->NoUpdFrame->visible = true;
    }
}
    
    /**
     * @event DeleteLauncherButton.click-Left 
     */
    function doDeleteLauncherButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }

}
