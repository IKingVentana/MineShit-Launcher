<?php
namespace app\forms;

use httpclient;
use Exception;
use std, gui, framework, app;
use app\modules\AppVersion;
use php\gui\UXDialog; 

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
$e = $event ?: $e; // legacy code from 16 rc-2

		UXDialog::showAndWait('Изменение масштаба пока не поддерживается. Следите за обновлениями!');

        
    }

    /**
     * @event DonateButton.click-Left 
     */
    function doDonateButtonClickLeft(UXMouseEvent $e = null)
    {    
$e = $event ?: $e; // legacy code from 16 rc-2

		browse('https://tbank.ru/cf/uSCxVtuotA');

        
    }

    /**
     * @event ReportButton.click-Left 
     */
    function doReportButtonClickLeft(UXMouseEvent $e = null)
    {    
$e = $event ?: $e; // legacy code from 16 rc-2

		browse('https://forms.gle/aUsfMoxiUT4AB4Hf7');

        
    }

    /**
     * @event FAQButton.click-Left 
     */
    function doFAQButtonClickLeft(UXMouseEvent $e = null)
    {    
$e = $event ?: $e; // legacy code from 16 rc-2

		browse('https://t.me/c/1904936807/5470');

        
    }

}
