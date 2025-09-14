<?php
namespace app\forms;

use httpclient;
use Exception;
use std, gui, framework, app;
use app\modules\AppVersion;

class Info extends AbstractForm
{

/**
 * @event show
 */
function doShow(\php\gui\event\UXEvent $e = null)
{
    // Получить версию сервера (как было)
    try {
        $client = new HttpClient();
        $version = $client->get("https://mscreate-data.ru/version.php")->body();
        $this->ServerVersion->text = "Сервер: " . trim($version);
    } catch (\Exception $ex) {
        $this->ServerVersion->text = "Сервер: ошибка загрузки";
    }

    // Получить версию лаунчера из AppVersion
    $launcherVersion = AppVersion::get();
    $this->LauncherVersion->text = "Лаунчер: " . $launcherVersion;
}
        
    /**
     * @event ResizeButton.click-Left 
     */
    function doResizeButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }

    /**
     * @event DonateButton.click-Left 
     */
    function doDonateButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }

    /**
     * @event ReportButton.click-Left 
     */
    function doReportButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }

    /**
     * @event FAQButton.click-Left 
     */
    function doFAQButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }

}
