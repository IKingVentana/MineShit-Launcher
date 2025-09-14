<?php
namespace app\forms;

use httpclient;
use Exception;
use std, gui, framework, app;
use php\io\File;
use php\io\FileStream;
use php\gui\framework\AbstractForm;
use php\gui\event\UXMouseEvent;
use httpclient\HttpClient;
use php\desktop\Desktop;
use php\lang\System;
use php\desktop\SystemDesktop;
use php\time\Time;
use php\lang\Thread;

class MainForm extends AbstractForm
{

    private $userFile = "user.txt";

    /**
     * @event construct
     */
    function doConstruct()
    {
        $secret = 'mineshitimmortalbestserverever'; // тот же что выше!
        $file = new \php\io\File($this->userFile);
        if ($file->exists()) {
            $content = json_decode((new \php\io\FileStream($file))->readFully(), true);
            if ($content && isset($content['login'], $content['sign'])) {
                $login = $content['login'];
                $expectedSign = md5($login . $secret);
                if ($content['sign'] === $expectedSign && strlen($login) >= 3) {
                    $this->LauncherLoginField->text = $login;
                    $this->openLoggedInForm($login);
                    return;
                }
            }
            // если что-то не так — удаляем файл, сбрасываем автологин
            $file->delete();
        }
    }
    
    /**
     * @event LoginButton.click-Left
     */
    function doLoginButtonClickLeft(UXMouseEvent $e = null)
    {
        $login = $this->LauncherLoginField->text;
        $password = $this->LauncherPasswordField->text;

        $this->ConsoleLabel->text = "⏳ Проверка...";

        try {
            $client = new HttpClient();
            $response = $client->post("https://mscreate-data.ru/auth.php", [
                "login" => $login,
                "password" => $password
            ]);

            $json = json_decode($response->body());

            if ($json && $json->status === "ok") {
                $this->ConsoleLabel->text = "✅ Успешный вход!";

                // Сохраняем логин в файл
                $secret = 'mineshitimmortalbestserverever'; // Поставь свой секрет, не публикуй его!
                $data = [
                    'login' => $login,
                    'sign' => md5($login . $secret)
                ];
                $stream = new FileStream($this->userFile, "w");
                $stream->write(json_encode($data));
                $stream->close();

                $this->openLoggedInForm($login);
            } else {
                $this->ConsoleLabel->text = "❌ Неверный логин или пароль";
            }
        } catch (Exception $e) {
            $this->ConsoleLabel->text = "⚠ Ошибка подключения: " . $e->getMessage();
        }
    }

    private function openLoggedInForm(string $login)
    {
        $form = app()->getForm('MainFormLoginned');
        if ($form) {
            if (method_exists($form, 'setPlayerName')) {
                $form->setPlayerName($login);
            }
            $form->show();
            $this->hide();
        } else {
            $this->ConsoleLabel->text = "⚠ Ошибка: форма не найдена";
        }
    }
    
    
    /**
     * @event DiscordButton.click-Left 
     */
    function doDiscordButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }

    /**
     * @event TelegramButton.click-Left 
     */
    function doTelegramButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }


    /**
     * @event ModSettingsButton.click-Left
     */
    function doModSettingsButtonClickLeft(UXMouseEvent $e = null)
    {
    
    }

    /**
     * @event LauncherLoginField.construct 
     */
    function doLauncherLoginFieldConstruct(UXEvent $e = null)
    {    
        
    }

    /**
     * @event LauncherPasswordField.construct 
     */
    function doLauncherPasswordFieldConstruct(UXEvent $e = null)
    {    
        
    }

    /**
     * @event LauncherPasswordField.globalKeyDown-Space 
     */
    function doLauncherPasswordFieldGlobalKeyDownSpace(UXKeyEvent $e = null)
    {    
        
    }

    /**
     * @event StartButton.click-Left 
     */
    function doStartButtonClickLeft(UXMouseEvent $e = null)
    {    
        
    }

    /**
     * @event SettingsButton.click-Left 
     */
    function doSettingsButtonClickLeft(UXMouseEvent $e = null)
    {    
        $form = app()->getForm('Settings');
        $form->setPreviousForm('MainForm'); // или 'MainForm' в другом случае
        $form->show();
        $this->hide();
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
     * @event LauncherPasswordField.keyDown-Enter
     */
    function doLauncherPasswordFieldKeyDownEnter(UXKeyEvent $e = null)
    {
        if (!$this->visible) return;
    
        $now = Time::millis();
        if ($this->loginBusy || ($now - $this->lastEnterAt) < $this->enterCooldownMs) {
            return;
        }
        $this->lastEnterAt = $now;
        $this->loginBusy = true;
    
        try {
            $this->doLoginButtonClickLeft();
        } finally {
            // небольшой «замок», чтобы авто‑повтор Enter не триггерил повторно
            \php\lang\Thread::run(function () {
                \php\lang\Thread::sleep(500);
                uiLater(function () { $this->loginBusy = false; });
            });
        }
    }

    /**
     * @event LoginButton.globalKeyPress-Enter
     */
    function doLoginButtonGlobalKeyPressEnter(UXKeyEvent $e = null)
    {
        // Отключаем глобальный Enter на кнопке, чтобы не было дубля
        if ($e) $e->consume();
        return;
    }
    
    /**
     * @event LauncherPasswordField.keyDown-Enter
     */
    function doLauncherPasswordFieldKeyDownEnter(UXKeyEvent $e = null)
    {
        // Если форма уже скрыта (например, автологин ушёл на другую форму) — ничего не делаем
        if (!$this->visible) {
            if ($e) $e->consume();
            return;
        }
    
        // Съедаем событие, чтобы не улетело дальше и не вызвало повторный клик
        if ($e) $e->consume();
    
        // Запускаем логин на UI-потоке, без потоков и статических run()
        uiLater(function () {
            if (!$this->visible) return;
    
            // Простейшая валидация
            $login = trim((string)($this->LauncherLoginField->text ?? ''));
            if ($login === '' || strlen($login) < 3) {
                $this->ConsoleLabel->text = "Введите логин (минимум 3 символа)";
                return;
            }
    
            // Кнопка может быть ещё не создана/скрыта — проверяем
            $btn = $this->LoginButton ?? null;
            if ($btn && !$btn->disable) {
                $this->doLoginButtonClickLeft();
            }
        });
    }
}
