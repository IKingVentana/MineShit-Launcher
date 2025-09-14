<?php
namespace app\forms;

use std;
use gui, framework, app;
use Throwable;
use php\lib\fs;
use php\io\File;
use php\lang\Thread;
use php\gui\event\UXMouseEvent;
use php\compress\ZipFile;
use app\modules\DownloaderModule;
use php\time\Time;
use app\modules\LogHelper; // Подключаем логгер!
use action\Element; 
use php\gui\UXDialog; 

/**
 * Безопасная проверка: есть ли файлы в папке (с fallback-сканерами)
 */
function folderHasAnyFile($dir) {
    try {
        if (!\php\lib\fs::isDir($dir)) return false;

        // 1) Пытаемся через fs::scan
        try {
            $list = \php\lib\fs::scan($dir);
            if (is_array($list) && count($list) > 0) {
                foreach ($list as $item) {
                    $path = rtrim($dir, "\\/") . DIRECTORY_SEPARATOR . $item;
                    if (\php\lib\fs::isFile($path)) return true;
                    if (\php\lib\fs::isDir($path) && folderHasAnyFile($path)) return true;
                }
                return false;
            }
        } catch (\Throwable $e) {
            // пойдём на запасные варианты
        }

        // 2) Fallback: scandir()
        try {
            $list = @scandir($dir);
            if (is_array($list)) {
                foreach ($list as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = rtrim($dir, "\\/") . DIRECTORY_SEPARATOR . $item;
                    if (\php\lib\fs::isFile($path)) return true;
                    if (\php\lib\fs::isDir($path) && folderHasAnyFile($path)) return true;
                }
                return false;
            }
        } catch (\Throwable $e) {
            // ещё один запасной вариант ниже
        }

        // 3) Fallback: glob()
        try {
            $glob = @glob(rtrim($dir, "\\/") . DIRECTORY_SEPARATOR . '*');
            if (is_array($glob)) {
                foreach ($glob as $path) {
                    if (\php\lib\fs::isFile($path)) return true;
                    if (\php\lib\fs::isDir($path) && folderHasAnyFile($path)) return true;
                }
                return false;
            }
        } catch (\Throwable $e) {
            // падать не будем
        }

        // Если ничего не получилось — считаем, что файлов нет
        return false;
    } catch (\Throwable $e) {
        \app\modules\LogHelper::log("[CHECK] folderHasAnyFile({$dir}) error(safe): " . $e->getMessage());
        return false;
    }
}

class MainFormLoginned extends AbstractForm
{
    /**
     * UUID utilities
     */
    protected function hexToUuid(string $hex): string
    {
        return substr($hex, 0, 8) . '-' .
               substr($hex, 8, 4) . '-' .
               substr($hex, 12, 4) . '-' .
               substr($hex, 16, 4) . '-' .
               substr($hex, 20, 12);
    }

    protected function offlineUuid(string $nickname): string
    {
        $hash = md5("OfflinePlayer:" . $nickname);
        return $this->hexToUuid($hash);
    }

    function hasFiles($dir) {
        $list = fs::scan($dir);
        if (!is_array($list) || count($list) == 0) return false;
        foreach ($list as $item) {
            if (fs::isFile($dir . DIRECTORY_SEPARATOR . $item)) return true;
        }
        return false;
    }

    /**
     * Проверка компонента (папка/файл)
     */
    public function isComponentInstalled(array $comp): bool
    {
        if (isset($comp['folder'])) {
            $ok = fs::isDir($comp['folder']);
            LogHelper::log("[CHECK] {$comp['label']} - " . ($ok ? "Папка есть" : "Нет папки"));
            return $ok;
        }
        if (isset($comp['file'])) {
            $ok = fs::isFile($comp['file']) && filesize($comp['file']) > 0;
            LogHelper::log("[CHECK] file {$comp['file']} - " . ($ok ? "OK" : "NO"));
            return $ok;
        }
        return false;
    }

    /**
     * Инициализация списка компонентов
     */
    protected function initComponents(): array
    {
        // (Если нужно — можно присвоить значения в свойства формы:)
        // $this->startBtnDefaultStyle = "-fx-background-color: #00000000; -fx-text-fill: #EBF4FF;";
        // $this->startBtnLoadingStyle = "-fx-background-color: #D41721; -fx-text-fill: #EBF4FF;";

        $this->ConsoleLabelLoginned->text = "⏳ Проверка целостность файлов...";
        $appdata = getenv('APPDATA');
        $base    = "$appdata\\.mineshit";
        $game    = "$base\\game";
        return [
            ['label'=>'jre',  'folder'=>"$base\\jre", 'zip'=>'https://mscreate-data.ru/.mineshit/jre.zip',          'dest'=>$base],
            ['label'=>'assets','folder'=>"$game\\assets", 'zip'=>'https://mscreate-data.ru/.mineshit/game/assets.zip',  'dest'=>$game],
            ['label'=>'bin',   'folder'=>"$game\\bin", 'zip'=>'https://mscreate-data.ru/.mineshit/game/bin.zip',     'dest'=>$game],
            ['label'=>'libraries','folder'=>"$game\\libraries", 'zip'=>'https://mscreate-data.ru/.mineshit/game/libraries.zip','dest'=>$game],
            ['label'=>'versions','folder'=>"$game\\versions", 'zip'=>'https://mscreate-data.ru/.mineshit/game/versions.zip','dest'=>$game],
            ['label'=>'webcache2','folder'=>"$game\\webcache2", 'zip'=>'https://mscreate-data.ru/.mineshit/game/webcache2.zip','dest'=>$game],
            ['label'=>'config','folder'=>"$game\\config", 'zip'=>'https://mscreate-data.ru/.mineshit/game/config.zip',   'dest'=>$game],
            ['label'=>'paxi', 'folder'=>"$game\\config\\paxi", 'zip'=>'https://mscreate-data.ru/.mineshit/game/paxi.zip', 'dest'=>"$game\\config"],
            ['label'=>'kubejs','folder'=>"$game\\kubejs", 'zip'=>'https://mscreate-data.ru/.mineshit/game/kubejs.zip',   'dest'=>$game],
            ['label'=>'resourcepacks','folder'=>"$game\\resourcepacks", 'zip'=>'https://mscreate-data.ru/.mineshit/game/resourcepacks.zip', 'dest'=>$game],
            ['label'=>'shaderpacks','folder'=>"$game\\shaderpacks",   'zip'=>'https://mscreate-data.ru/.mineshit/game/shaderpacks.zip',   'dest'=>$game],
            ['label'=>'options.txt', 'file'=>"$game\\options.txt", 'url'=>'https://mscreate-data.ru/.mineshit/game/options.txt']
        ];
    }

    /**
     * Асинхронная установка компонентов
     */
    function installComponentsAsync($components, $progressBar, $onFinish) {
        $self = $this;
        $total = count($components);

        $installNext = function($i = 0) use (&$installNext, $components, $progressBar, $onFinish, $self, $total) {
            if ($i >= $total) {
                uiLater(function() use ($onFinish) {
                    if ($onFinish) $onFinish();
                });
                return;
            }
            $comp = $components[$i];

            if ($self->isComponentInstalled($comp)) {
                LogHelper::log("Пропускаем компонент {$comp['label']}, уже установлен.");
                uiLater(function() use ($progressBar, $i, &$installNext, $total, $self) {
                    $progressBar->progress = ($i + 1) / $total;
                    $self->ConsoleLabelLoginned->text = "☁ Идет установка файлов. " . ($i + 1) . " из $total";
                    $installNext($i + 1);
                });
                return;
            }

            uiLater(function() use ($progressBar, $i, $total, $self, $comp) {
                $progressBar->progress = $i / $total;
                $self->ConsoleLabelLoginned->text = "☁ Идет установка файлов. " . $i . " из $total";
            });

            $thread = new \php\lang\Thread(function() use ($comp, $progressBar, $i, &$installNext, $total, $self) {
                try {
                    LogHelper::log("ПОТОК ЗАПУЩЕН, $i из $total: {$comp['label']}");
                    if (isset($comp['zip'])) {
                        $tmpZip = sys_get_temp_dir() . "/ms_tmp_" . $comp['label'] . ".zip";
                        LogHelper::log("Скачиваем ZIP [{$comp['label']}] из {$comp['zip']} в $tmpZip");

                        DownloaderModule::downloadFile($comp['zip'], $tmpZip, $comp['label'], 3);

                        LogHelper::log("Распаковываем $tmpZip в {$comp['dest']}");
                        $zipFile = new \php\compress\ZipFile($tmpZip);
                        $zipFile->unpack($comp['dest']);
                        unset($zipFile);
                        @unlink($tmpZip);

                        LogHelper::log("Компонент {$comp['label']} установлен.");
                    } elseif (isset($comp['url']) && isset($comp['file'])) {
                        LogHelper::log("Скачиваем файл {$comp['url']} в {$comp['file']}");
                        DownloaderModule::downloadFile($comp['url'], $comp['file'], $comp['label'] ?? basename($comp['file']), 3);
                        LogHelper::log("Файл {$comp['file']} скачан.");
                    }
                } catch (\Throwable $e) {
                    LogHelper::log("Ошибка при установке компонента {$comp['label']}: " . $e->getMessage());
                }
                uiLater(function() use ($progressBar, $i, &$installNext, $total, $self) {
                    $progressBar->progress = ($i + 1) / $total;
                    $self->ConsoleLabelLoginned->text = "☁ Идет установка файлов. " . ($i + 1) . " из $total";
                    $installNext($i + 1);
                });
            });
            $thread->start();
        };
        $progressBar->progress = 0;
        $installNext(0);
    }

    /**
     * Построить план модов: обязательные + включённые опциональные
     */
    private function buildModsPlan(string $gameDir): array
    {
        // === 1) ОБЯЗАТЕЛЬНЫЕ МОДЫ ===
        $requiredMods = [
            "AdaptiveTooltips-1.3.0-fabric-1.20.2.jar",
            "AdvancementPlaques-1.20.1-forge-1.6.6.jar",
            "allurement-1.20.1-4.0.0.jar",
            "ancient_forgemastery-1.2.0-forge-1.20.1.jar",
            "Anti_Mob_Farm-1.20.1-1.3.11.jar",
            "anvianslib-forge-1.20-1.1.jar",
            "apexcore-1.20.1-10.0.0.jar",
            "appleskin-forge-mc1.20.1-2.5.1.jar",
            "architectury-9.2.14-forge.jar",
            "armor_impact-0.3-forge-1.20.1.jar",
            "AttributeFix-Forge-1.20.1-21.0.4.jar",
            "AutoLeveling-1.20-1.19b.jar",
            "bellsandwhistles-0.4.5-1.20.x-Create6.0+.jar",
            "BetterF3-7.0.2-Forge-1.20.1.jar",
            "BetterThirdPerson-Forge-1.20-1.9.0.jar",
            "biomemusic-1.20.1-2.5.jar",
            "Bookshelf-Forge-1.20.1-20.2.13.jar",
            "caelus-forge-3.2.0+1.20.1.jar",
            "canary-mc1.20.1-0.3.2.jar",
            "Chunky-1.3.146.jar",
            "clean_tooltips-1.0-forge-1.20.1.jar",
            "collective-1.20.1-8.3.jar",
            "combatroll-forge-1.3.3+1.20.1.jar",
            "configurableextramobdrops-1.20.1-3.5.jar",
            "ConnectorExtras-1.11.2+1.20.1.jar",
            "Controlling-forge-1.20.1-12.0.2.jar",
            "coroutil-forge-1.20.1-1.3.7.jar",
            "create_easy_structures-0.2-forge-1.20.1.jar",
            "create_enchantment_industry-1.3.3-for-create-6.0.6.jar",
            "create_jetpack-forge-4.4.2.jar",
            "CreateUnbreakableTools-1.7+forge-1.20.1-build.9.jar",
            "cupboard-1.20.1-2.6.jar",
            "curios-forge-5.14.1+1.20.1.jar",
            "curiouslanterns-1.20.1-1.3.7.jar",
            "diet-forge-2.1.1+1.20.1.jar",
            "DifficultSpawners1.1.0-1.20.1.jar",
            "drippyloadingscreen_forge_3.0.1_MC_1.20.1.jar",
            "dummmmmmy-1.20-2.0.7.jar",
            "dungeons-and-taverns-pillager-outpost-rework-1.1.jar",
            "dynamic-fps-3.6.3+minecraft-1.20.0-forge.jar",
            "dynamiclightsreforged-1.20.1_v1.6.0.jar",
            "dynamicvillage-v0.4-1.20.1.jar",
            "EasyAnvils-v8.0.2-1.20.1-Forge.jar",
            "EasyMagic-v8.0.1-1.20.1-Forge.jar",
            "EnchantmentDescriptions-Forge-1.20.1-17.0.14.jar",
            "endersdelight-1.20.1-1.0.3.jar",
            "enigmaticdelicacy-1.0.0.jar",
            "entity_model_features_forge_1.20.1-2.4.1.jar",
            "entity_texture_features_forge_1.20.1-6.2.9.jar",
            "entityculling-forge-1.6.2-mc1.20.1.jar",
            "extrasounds-1.20.1-forge-1.3.jar",
            "FarmersStructures-1.0.3-1.20.jar",
            "Fastload-Reforged-mc1.20.1-3.4.0.jar",
            "ferritecore-6.0.1-forge.jar",
            "feur_extension_fossil-1.20.1-forge.jar",
            "forge-medievalend-1.0.1.jar",
            "formations-1.0.2-forge-mc1.20.jar",
            "FpsReducer2-forge-1.20-2.5.jar",
            "ftb-teams-forge-2001.3.1.jar",
            "ftb-xmod-compat-forge-2.1.2.jar",
            "highlight-forge-1.20-2.0.1.jar",
            "Iceberg-1.20.1-forge-1.1.21.jar",
            "illagerwarship-1.0.0forge1.20.1.jar",
            "ImmediatelyFast-Forge-1.2.14+1.20.4.jar",
            "immortaldroid-1.20.1-1.0.2.0.jar",
            "immortalskills-1.1.2.jar",
            "interiors-0.5.6+forge-mc1.20.1-local.jar",
            "ironchest-1.20.1-14.4.4.jar",
            "Item-Obliterator-NeoForge-MC1.20.1-2.3.1.jar",
            "ItemProductionLib-1.20.1-1.0.2a-all.jar",
            "konkrete_forge_1.8.0_MC_1.20-1.20.1.jar",
            "legendaryitems-1.20.1-1.0.0.1.jar",
            "Library_of_Exile-1.20.1-2.1.0.jar",
            "lionfishapi-2.4.jar",
            "loadingbackgrounds-forge-1.20.2-1.5.0.jar",
            "man_of_many_planes-0.2.0+1.20.1-forge.jar",
            "melody_forge_1.0.3_MC_1.20.1-1.20.4.jar",
            "memoryleakfix-forge-1.17+-1.1.5.jar",
            "midnightlib-forge-1.4.2.jar",
            "miners_delight-1.20.1-1.2.3.jar",
            "modelfix-1.15.jar",
            "MouseTweaks-forge-mc1.20.1-2.25.1.jar",
            "MyNethersDelight-1.20.1-0.1.7.5.jar",
            "Necronomicon-Forge-1.4.2.jar",
            "noisium-forge-2.3.0+mc1.20-1.20.1.jar",
            "nordic_structures-1.6.9-forge-1.20.1.jar",
            "notenoughanimations-forge-1.7.3-mc1.20.1.jar",
            "NotifMod-1.4.jar",
            "obscure_api-15.jar",
            "Obscure-Tooltips-2.2.jar",
            "OctoLib-FORGE-0.4.2+1.20.1.jar",
            "panorama_screens-1.0+forge+mc1.20.jar",
            "Patchouli-1.20.1-84-FORGE.jar",
            "Paxi-1.20-Forge-4.0.jar",
            "player-animation-lib-forge-1.0.2-rc1+1.20.jar",
            "radiantgear-forge-2.2.0+1.20.1.jar",
            "realmrpg_fallen_adventurers_1.0.3_forge_1.20.1.jar",
            "resourcefulconfig-forge-1.20.1-2.1.2.jar",
            "resourcefullib-forge-1.20.1-2.1.24.jar",
            "royal_variations_[Forge]_1.20.1_1.0.jar",
            "rubidium-extra-0.5.4.3+mc1.20.1-build.121.jar",
            "saturn-mc1.20.1-0.1.3.jar",
            "sawmill-1.20-1.4.7.jar",
            "Searchables-forge-1.20.1-1.0.3.jar",
            "seriousplayeranimations-1.1.1.jar",
            "ShieldExpansion-1.20.1-1.1.7a.jar",
            "simpletridents-1.20.1-1.0.0.2.jar",
            "simplybuttons-1.0.1.0.jar",
            "sophisticatedbackpackscreateintegration-1.20.1-0.1.3.11.jar",
            "starterkit-1.20.1-7.0.jar",
            "structureessentials-1.20.1-3.4.jar",
            "supermartijn642configlib-1.1.8-forge-mc1.20.jar",
            "TerraBlender-forge-1.20.1-3.0.1.7.jar",
            "tidal-towns-1.3.4.jar",
            "Tips-Forge-1.20.1-12.0.5.jar",
            "TreeChop-1.20.1-forge-0.19.0.jar",
            "valhelsia_core-forge-1.20.1-1.1.2.jar",
            "VisualWorkbench-v8.0.0-1.20.1-Forge.jar",
            "warriors_of_past_epoch_[Forge]_1.20.1_1.4.jar",
            "YungsApi-1.20-Forge-4.0.6.jar",
            "YungsBetterDungeons-1.20-Forge-4.0.4.jar",
            "YungsBetterEndIsland-1.20-Forge-2.0.6.jar",
            "YungsBetterMineshafts-1.20-Forge-4.0.4.jar",
            "YungsBetterNetherFortresses-1.20-Forge-2.0.6.jar",
            "YungsBetterStrongholds-1.20-Forge-4.0.3.jar",
            "another_furniture-forge-1.20.1-3.0.2.jar",
            "azurelib-fabric-1.20.1-2.0.36.jar",
            "betterarcheology-1.2.1-1.20.1.jar",
            "bettercombat-forge-1.8.6+1.20.1.jar",
            "blueprint-1.20.1-7.1.0.jar",
            "BrewinAndChewin-1.20.1-3.2.1.jar",
            "cloth-config-11.1.118-forge.jar",
            "create-stuff-additions1.20.1_v2.1.0.jar",
            "CreativeCore_FORGE_v2.12.26_mc1.20.1.jar",
            "creeperoverhaul-3.0.2-forge.jar",
            "embeddium-0.3.30+mc1.20.1.jar",
            "endermanoverhaul-forge-1.20.1-1.0.4.jar",
            "enigmaticaddons-1.2.5.jar",
            "fabric-api-0.92.2+1.11.11+1.20.1.jar",
            "fancymenu_forge_3.2.3_MC_1.20.1.jar",
            "FarmersDelight-1.20.1-1.2.4.jar",
            "forbidden_arcanus-1.20.1-2.2.6.jar",
            "ftb-library-forge-2001.2.9.jar",
            "ftb-quests-forge-2001.4.11.jar",
            "geckolib-forge-1.20.1-4.7.jar",
            "gnumus_settlement_[Forge]1.20.1_v1.0.jar",
            "immersive_aircraft-1.2.0+1.20.1-forge.jar",
            "immortalgingerbread-1.20.1-1.0.0.0-small-fix.jar",
            "kubejs-forge-2001.6.5-build.16.jar",
            "legendarycreatures-1.20.1-1.0.10.jar",
            "modernfix-forge-5.19.3+mc1.20.1.jar",
            "moonlight-1.20-2.13.82-forge.jar",
            "oculus-mc1.20.1-1.8.0.jar",
            "PassiveSkillTree-1.20.1-BETA-0.6.13a-all.jar",
            "ponderjs-1.20.1-2.0.6.jar",
            "protection_pixel-1.1.6-forge-1.20.1.jar",
            "PuzzlesLib-v8.1.22-1.20.1-Forge.jar",
            "rhino-forge-2001.2.3-build.10.jar",
            "sliceanddice-forge-3.4.0.jar",
            "sophisticatedbackpacks-1.20.1-3.23.18.1247.jar",
            "sophisticatedcore-1.20.1-1.2.66.997.jar",
            "strayed-fates-forsaken-1.1.1.0.jar",
            "Structory_1.20.x_v1.3.5.jar",
            "UndeadUnleashed-1.2.2-forge-1.20.1.jar",
            "WWOO-FABRIC+FORGE+QUILT-2.0.0.jar",
            "YetAnotherConfigLib-3.6.2+1.20.1-fabric.jar",
            "YungsBetterJungleTemples-1.20-Forge-2.0.5.jar",
            "YungsBetterOceanMonuments-1.20-Forge-3.0.4.jar",
            "Zeta-1.0-30.jar",
            "born_in_chaos_[Forge]1.20.1_1.7.jar",
            "caverns_and_chasms-1.20.1-2.0.0.jar",
            "Connector-1.0.0-beta.46+1.20.1.jar",
            "create_connected-1.1.7-mc1.20.1-all.jar",
            "create-1.20.1-6.0.6.jar",
            "dungeons-and-taverns-3.0.3.f.jar",
            "EnigmaticLegacy-2.30.1.jar",
            "immortalsoul-diamond-1.20.1-1.1.3.2.jar",
            "irons_spellbooks-1.20.1-3.4.0.7.jar",
            "Jadens-Nether-Expansion-2.0.1-Forge.jar",
            "kotlinforforge-4.11.0-all.jar",
            "naturalist-5.0pre3+forge-1.20.1.jar",
            "PresenceFootsteps-1.20.1-1.9.1-beta.1.jar",
            "Quark-4.0-462.jar",
            "rootoffear-1.20.1-1.0.10.jar",
            "tru.e-ending-v1.1.0c.jar",
            "upgrade_aquatic-1.20.1-6.0.3.jar",
            "aquamirae-6.API15.jar",
            "mowziesmobs-1.7.3.jar",
            "unusualend-2.3.0-forge-1.20.1.jar",
            "cataclysmiccombat-1.3.4.jar",
            "Clumps-forge-1.20.1-12.0.0.4.jar",
            "elretrievetoslot-1.0.1.jar",
            "experimentalsettingsdisabler-1.20.1-3.0.jar",
            "FancyHotbar-Fabric-1.0.0.jar",
            "limitedbackpacks-1.0.jar",
            "MaxHealthFix-Forge-1.20.1-12.0.2.jar",
            "NotEnoughRecipeBook-FORGE-0.4.1+1.20.1.jar",
            "packetfixer-forge-1.3.2-1.19-to-1.20.1.jar",
            "ProtectionBalancer-FABRIC-1.2.0.jar",
            "ResistanceBalancer-(NEO)FORGE-1.0.0.jar",
            "client-talkbubbles-1.0.8.jar",
            "Stoneworks-v8.0.0-1.20.1-Forge.jar",
            "immersive_paintings-0.6.8+1.20.1-forge.jar",
            "Jade.jar",
            "crittersandcompanions-forge.jar",
            "Female-Gender-Mod-forge-1.20.1-3.1.jar",  // ← добавил .jar
            "clickthrough-plus-forge-3.5.1+1.20.1.jar",
            "hearth_and_home-forge-1.20.1-2.0.3.jar",
            "L_Enders_Cataclysm-3.15.jar",
            "AmbientSounds_FORGE_v6.1.4_mc1.20.1.jar"
        ];
    
        // === 2) ОПЦИОНАЛЬНЫЕ МОДЫ ===
        $userMods = [
            "DistantHorizon"   => "DistantHorizons-2.3.4-b-1.20.1-fabric-forge.jar",
            "VoiceChat"        => "voicechat-forge-1.20.1-2.5.35.jar",
            "EmojiType"        => "client-emoji-type-2.2.3+1.20.4-forgelegacy.jar",
            "CameraOverhaul"   => "client-CameraOverhaul-v2.0.4-forge+mc.jar",
            "JEI"              => "jei-1.20.1-forge-15.20.0.108.jar",
            "XaerosWorldMap"   => "XaerosWorldMap_1.39.12_Forge_1.20.jar",
            "XaerosMinimap"    => "Xaeros_Minimap_25.2.10_Forge_1.20.jar",
            "Emotes"           => "emotecraft-for-MC1.20.1-2.2.7-b.build.50-forge.jar"
        ];
    
        // === 3) Конфиг включённых опциональных ===
        $configFile = $gameDir . "\\mods_config.json";
        if (!file_exists($configFile)) {
            $defaultMods = [
                "DistantHorizon"   => false,
                "VoiceChat"        => true,
                "EmojiType"        => true,
                "CameraOverhaul"   => false,
                "JEI"              => true,
                "XaerosWorldMap"   => true,
                "XaerosMinimap"    => true,
                "Emotes"           => true
            ];
            file_put_contents($configFile, json_encode($defaultMods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        $selectedMods = [];
        $decoded = @json_decode(@file_get_contents($configFile), true);
        if (is_array($decoded)) $selectedMods = $decoded;
    
        // === 4) Итоговые списки ===
        $allValidMods = $requiredMods;           // обязательные
        $optionalAll  = array_values($userMods); // все опциональные
        $optionalSelected = [];
    
        foreach ($userMods as $name => $file) {
            if (!empty($selectedMods[$name])) {
                $allValidMods[] = $file;
                $optionalSelected[] = $file;
            }
        }
    
        // === 5) ДЕ-ДУПЛИКАЦИЯ (без учёта регистра) — ВСТАВЛЯЕТСЯ ПЕРЕД return ===
        $seen = [];
        $dedup = [];
        foreach ($allValidMods as $fn) {
            $k = strtolower($fn);
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $dedup[] = $fn;
            }
        }
        $allValidMods = $dedup;
    
        return [
            'all'              => $allValidMods,
            'optionalAll'      => $optionalAll,
            'optionalSelected' => $optionalSelected,
        ];
    }

    /**
     * Вернёт список отсутствующих/пустых модов из ожидаемого набора
     */
    private function getMissingMods(string $modsDir, array $expected): array
    {
        $modsDir = rtrim($modsDir, "\\/") . DIRECTORY_SEPARATOR;
        $missing = [];
        foreach ($expected as $file) {
            $path = $modsDir . $file;
            if (!file_exists($path) || filesize($path) <= 0) {
                $missing[] = $file;
            }
        }
        return $missing;
    }

    /**
     * Скачивание/обновление модов + очистка лишних
     */
    function updateAllMods($onFinish = null)
    {
        $appdata = getenv('APPDATA');
        $gameDir = $appdata . "\\.mineshit\\game";
        $modsDir = $gameDir . "\\mods\\";
        if (!fs::isDir($modsDir)) fs::makeDir($modsDir, 0777, true);
    
        // === вот здесь "сниппет про план" ===
        $plan = $this->buildModsPlan($gameDir);
    
        $allValidMods         = $plan['all'];              // белый список
        $optionalAll          = $plan['optionalAll'];      // все опц. моды (имена файлов)
        $selectedOptionalMods = $plan['optionalSelected']; // включённые опц. моды
    
        $total = count($allValidMods);
    
        $thread = new \php\lang\Thread(function() use (
            $allValidMods, $modsDir, $total, $onFinish,
            $optionalAll, $selectedOptionalMods
        ) {
            $baseUrl = "https://mscreate-data.ru/.mineshit/game/mods/";
    
            // --- скачивание ---
            for ($i = 0; $i < $total; $i++) {
                $fileName = $allValidMods[$i];
                $filePath = $modsDir . $fileName;
                $url = $baseUrl . \app\modules\DownloaderModule::encodeFileNameForUrl($fileName);
    
                if (!file_exists($filePath) || filesize($filePath) <= 0) {
                    \app\modules\LogHelper::log("Скачиваем мод: $fileName");
                    uiLater(function() use ($i, $total, $fileName) {
                        $form = app()->getForm('MainFormLoginned');
                        $form->ConsoleLabelLoginned->text = "☁ Установка модификаций " . ($i + 1) . " из $total ($fileName)";
                    });
    
                    $ok = \app\modules\DownloaderModule::downloadFile($url, $filePath, $fileName, 3);
                    if (!$ok) {
                        \app\modules\LogHelper::log("Не удалось скачать $fileName");
                    } else {
                        \app\modules\LogHelper::log("Мод $fileName установлен.");
                    }
                } else {
                    \app\modules\LogHelper::log("Мод $fileName уже установлен.");
                    uiLater(function() use ($i, $total, $fileName) {
                        $form = app()->getForm('MainFormLoginned');
                        $form->ConsoleLabelLoginned->text = "☁ Проверка модификаций " . ($i + 1) . " из $total ($fileName)";
                    });
                }
            }
    
            // --- удаление выключенных опциональных (та самая часть сниппета) ---
            foreach ($optionalAll as $name) {
                $p = $modsDir . $name;
                if (file_exists($p) && !in_array($name, $selectedOptionalMods, true)) {
                    \app\modules\LogHelper::log("Удаляем отключённый мод $name");
                    @unlink($p);
                }
            }
    
            // --- глобальная очистка: всё, чего нет в белом списке (тоже из сниппета) ---
            uiLater(function() {
                $form = app()->getForm('MainFormLoginned');
                $form->ConsoleLabelLoginned->text = "🧹 Удаление старых модов...";
            });
            \app\modules\DownloaderModule::cleanModsDirectory($modsDir, $allValidMods);
    
            uiLater(function() use ($onFinish) {
                if ($onFinish) $onFinish();
            });
        });
        $thread->start();
    }

    /**
     * @event StartButton.click-Left
     */
    function doStartButtonClickLeft(UXMouseEvent $e = null)
    {
        // визуал
        $this->StartButton->text = "ЗАГРУЗКА";
        $this->StartButton->disable = true;
        $this->StartButton->style = $this->startBtnLoadingStyle;
    
        $appdata = getenv('APPDATA');
        $base = "$appdata\\.mineshit";
        $game = "$base\\game";
        $self = $this;
    
        // 1) базовые папки
        if (!fs::isDir($base)) fs::makeDir($base, 0777, true);
        if (!fs::isDir($game)) fs::makeDir($game, 0777, true);
    
        // 2) проверка целостности
        $allDirs = [
            "$base\\jre",
            "$game\\assets",
            "$game\\bin",
            "$game\\libraries",
            "$game\\versions",
            "$game\\webcache2",
            "$game\\config",
            "$game\\config\\paxi",
            "$game\\kubejs",
            "$game\\resourcepacks",
            "$game\\shaderpacks"
        ];
    
        $allOk = true;
        foreach ($allDirs as $dir) {
            if (!fs::isDir($dir) || !folderHasAnyFile($dir)) {
                $allOk = false;
                break;
            }
        }
        if (!fs::isFile("$game\\options.txt")) $allOk = false;
        if (!fs::isDir("$game\\mods") || !folderHasAnyFile("$game\\mods")) $allOk = false;
    
        // === ГЛАВНОЕ ИЗМЕНЕНИЕ ===
        // Даже если всё ок — все равно синхронизируем/чистим моды перед запуском.
        if ($allOk) {
            \app\modules\LogHelper::log("[FAST_CHECK] Все файлы на месте — делаем быструю синхронизацию модов и чистку, затем запуск.");
            $this->ConsoleLabelLoginned->text = "☁ Проверка и обновление модов...";
            $this->updateAllMods(function() use ($self) {
                $self->ConsoleLabelLoginned->text = "✔ Готово! Запуск...";
                $self->StartButton->text = "СТАРТ";
                $self->StartButton->disable = false;
                $self->StartButton->style = $self->startBtnDefaultStyle;
                $self->doLaunchMinecraft();
            });
            return;
        }
    
        // 3) если целостность не пройдена — ставим недостающее и потом моды
        $components = $this->initComponents();
    
        $this->installComponentsAsync($components, $this->DownloadBar, function() use ($self) {
            $self->ConsoleLabelLoginned->text = "☁ Установка модификаций...";
            $self->StartButton->text = "ЗАГРУЗКА";
            $self->StartButton->disable = true;
            $self->StartButton->style = $self->startBtnLoadingStyle;
    
            $self->updateAllMods(function() use ($self) {
                $self->ConsoleLabelLoginned->text = "✔ Все модификации и файлы установлены! Приятной игры!";
                $self->StartButton->text = "СТАРТ";
                $self->StartButton->disable = false;
                $self->StartButton->style = $self->startBtnDefaultStyle;
                $self->doLaunchMinecraft();
            });
        });
    }

    /**
     * Launch minecraft after install
     */
    public function doLaunchMinecraft(): void
    {
        $this->StartButton->text = "СТАРТ";
        $this->StartButton->disable = false;
        $this->StartButton->style = $this->startBtnDefaultStyle;
        $appdata = getenv('APPDATA');
        $base    = "$appdata\\.mineshit";
        $gameDir = "$base\\game";
        $versionDir = "$gameDir\\versions\\1.20.1-forge-47.4.0";
        $nativesDir = "$versionDir\\natives";
        $assetsDir  = "$gameDir\\assets";
        $javaw = "$base\\jre\\java-runtime-gamma\\windows-x64\\java-runtime-gamma\\bin\\javaw.exe";

        // nickname/uuid
        $nickname = trim($this->LauncherLoginField->text ?: 'Player');
        $uuid = $this->offlineUuid($nickname);

        // 2. Classpath
        $classpath = implode(";", [
            "$gameDir\\libraries\\cpw\\mods\\securejarhandler\\2.1.10\\securejarhandler-2.1.10.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm\\9.7.1\\asm-9.7.1.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm-commons\\9.7.1\\asm-commons-9.7.1.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm-tree\\9.7.1\\asm-tree-9.7.1.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm-util\\9.7.1\\asm-util-9.7.1.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm-analysis\\9.7.1\\asm-analysis-9.7.1.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\accesstransformers\\8.0.4\\accesstransformers-8.0.4.jar",
            "$gameDir\\libraries\\org\\antlr\\antlr4-runtime\\4.9.1\\antlr4-runtime-4.9.1.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\eventbus\\6.0.5\\eventbus-6.0.5.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\forgespi\\7.0.1\\forgespi-7.0.1.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\coremods\\5.2.4\\coremods-5.2.4.jar",
            "$gameDir\\libraries\\cpw\\mods\\modlauncher\\10.0.9\\modlauncher-10.0.9.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\unsafe\\0.2.0\\unsafe-0.2.0.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\mergetool\\1.1.5\\mergetool-1.1.5-api.jar",
            "$gameDir\\libraries\\com\\electronwill\\night-config\\core\\3.6.4\\core-3.6.4.jar",
            "$gameDir\\libraries\\com\\electronwill\\night-config\\toml\\3.6.4\\toml-3.6.4.jar",
            "$gameDir\\libraries\\org\\apache\\maven\\maven-artifact\\3.8.5\\maven-artifact-3.8.5.jar",
            "$gameDir\\libraries\\net\\jodah\\typetools\\0.6.3\\typetools-0.6.3.jar",
            "$gameDir\\libraries\\net\\minecrell\\terminalconsoleappender\\1.2.0\\terminalconsoleappender-1.2.0.jar",
            "$gameDir\\libraries\\org\\jline\\jline-reader\\3.12.1\\jline-reader-3.12.1.jar",
            "$gameDir\\libraries\\org\\jline\\jline-terminal\\3.12.1\\jline-terminal-3.12.1.jar",
            "$gameDir\\libraries\\org\\spongepowered\\mixin\\0.8.5\\mixin-0.8.5.jar",
            "$gameDir\\libraries\\org\\openjdk\\nashorn\\nashorn-core\\15.4\\nashorn-core-15.4.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\JarJarSelector\\0.3.19\\JarJarSelector-0.3.19.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\JarJarMetadata\\0.3.19\\JarJarMetadata-0.3.19.jar",
            "$gameDir\\libraries\\cpw\\mods\\bootstraplauncher\\1.1.2\\bootstraplauncher-1.1.2.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\JarJarFileSystems\\0.3.19\\JarJarFileSystems-0.3.19.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\fmlloader\\1.20.1-47.4.0\\fmlloader-1.20.1-47.4.0.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\fmlearlydisplay\\1.20.1-47.4.0\\fmlearlydisplay-1.20.1-47.4.0.jar",
            "$gameDir\\libraries\\com\\github\\oshi\\oshi-core\\6.2.2\\oshi-core-6.2.2.jar",
            "$gameDir\\libraries\\com\\google\\code\\gson\\gson\\2.10\\gson-2.10.jar",
            "$gameDir\\libraries\\com\\google\\guava\\failureaccess\\1.0.1\\failureaccess-1.0.1.jar",
            "$gameDir\\libraries\\com\\google\\guava\\guava\\31.1-jre\\guava-31.1-jre.jar",
            "$gameDir\\libraries\\com\\ibm\\icu\\icu4j\\71.1\\icu4j-71.1.jar",
            "$gameDir\\libraries\\by\\ely\\authlib\\4.0.43-ely.4\\authlib-4.0.43-ely.4.jar",
            "$gameDir\\libraries\\com\\mojang\\blocklist\\1.0.10\\blocklist-1.0.10.jar",
            "$gameDir\\libraries\\com\\mojang\\brigadier\\1.1.8\\brigadier-1.1.8.jar",
            "$gameDir\\libraries\\com\\mojang\\datafixerupper\\6.0.8\\datafixerupper-6.0.8.jar",
            "$gameDir\\libraries\\com\\mojang\\logging\\1.1.1\\logging-1.1.1.jar",
            "$gameDir\\libraries\\ru\\tln4\\empty\\0.1\\empty-0.1.jar",
            "$gameDir\\libraries\\com\\mojang\\text2speech\\1.17.9\\text2speech-1.17.9.jar",
            "$gameDir\\libraries\\commons-codec\\commons-codec\\1.15\\commons-codec-1.15.jar",
            "$gameDir\\libraries\\commons-io\\commons-io\\2.11.0\\commons-io-2.11.0.jar",
            "$gameDir\\libraries\\commons-logging\\commons-logging\\1.2\\commons-logging-1.2.jar",
            "$gameDir\\libraries\\io\\netty\\netty-buffer\\4.1.82.Final\\netty-buffer-4.1.82.Final.jar",
            "$gameDir\\libraries\\io\\netty\\netty-codec\\4.1.82.Final\\netty-codec-4.1.82.Final.jar",
            "$gameDir\\libraries\\io\\netty\\netty-common\\4.1.82.Final\\netty-common-4.1.82.Final.jar",
            "$gameDir\\libraries\\io\\netty\\netty-handler\\4.1.82.Final\\netty-handler-4.1.82.Final.jar",
            "$gameDir\\libraries\\io\\netty\\netty-resolver\\4.1.82.Final\\netty-resolver-4.1.82.Final.jar",
            "$gameDir\\libraries\\io\\netty\\netty-transport-classes-epoll\\4.1.82.Final\\netty-transport-classes-epoll-4.1.82.Final.jar",
            "$gameDir\\libraries\\io\\netty\\netty-transport-native-unix-common\\4.1.82.Final\\netty-transport-native-unix-common-4.1.82.Final.jar",
            "$gameDir\\libraries\\io\\netty\\netty-transport\\4.1.82.Final\\netty-transport-4.1.82.Final.jar",
            "$gameDir\\libraries\\it\\unimi\\dsi\\fastutil\\8.5.9\\fastutil-8.5.9.jar",
            "$gameDir\\libraries\\net\\java\\dev\\jna\\jna-platform\\5.12.1\\jna-platform-5.12.1.jar",
            "$gameDir\\libraries\\net\\java\\dev\\jna\\jna\\5.12.1\\jna-5.12.1.jar",
            "$gameDir\\libraries\\net\\sf\\jopt-simple\\jopt-simple\\5.0.4\\jopt-simple-5.0.4.jar",
            "$gameDir\\libraries\\org\\apache\\commons\\commons-compress\\1.21\\commons-compress-1.21.jar",
            "$gameDir\\libraries\\org\\apache\\commons\\commons-lang3\\3.12.0\\commons-lang3-3.12.0.jar",
            "$gameDir\\libraries\\org\\apache\\httpcomponents\\httpclient\\4.5.13\\httpclient-4.5.13.jar",
            "$gameDir\\libraries\\org\\apache\\httpcomponents\\httpcore\\4.4.15\\httpcore-4.4.15.jar",
            "$gameDir\\libraries\\org\\apache\\logging\\log4j\\log4j-api\\2.19.0\\log4j-api-2.19.0.jar",
            "$gameDir\\libraries\\org\\apache\\logging\\log4j\\log4j-core\\2.19.0\\log4j-core-2.19.0.jar",
            "$gameDir\\libraries\\org\\apache\\logging\\log4j\\log4j-slf4j2-impl\\2.19.0\\log4j-slf4j2-impl-2.19.0.jar",
            "$gameDir\\libraries\\org\\joml\\joml\\1.10.5\\joml-1.10.5.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-glfw\\3.3.1\\lwjgl-glfw-3.3.1.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-glfw\\3.3.1\\lwjgl-glfw-3.3.1-natives-windows.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-glfw\\3.3.1\\lwjgl-glfw-3.3.1-natives-windows-arm64.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-glfw\\3.3.1\\lwjgl-glfw-3.3.1-natives-windows-x86.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-jemalloc\\3.3.1\\lwjgl-jemalloc-3.3.1.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-jemalloc\\3.3.1\\lwjgl-jemalloc-3.3.1-natives-windows.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-jemalloc\\3.3.1\\lwjgl-jemalloc-3.3.1-natives-windows-arm64.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-jemalloc\\3.3.1\\lwjgl-jemalloc-3.3.1-natives-windows-x86.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-openal\\3.3.1\\lwjgl-openal-3.3.1.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-openal\\3.3.1\\lwjgl-openal-3.3.1-natives-windows.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-openal\\3.3.1\\lwjgl-openal-3.3.1-natives-windows-arm64.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-openal\\3.3.1\\lwjgl-openal-3.3.1-natives-windows-x86.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-opengl\\3.3.1\\lwjgl-opengl-3.3.1.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-opengl\\3.3.1\\lwjgl-opengl-3.3.1-natives-windows.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-opengl\\3.3.1\\lwjgl-opengl-3.3.1-natives-windows-arm64.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-opengl\\3.3.1\\lwjgl-opengl-3.3.1-natives-windows-x86.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-stb\\3.3.1\\lwjgl-stb-3.3.1.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-stb\\3.3.1\\lwjgl-stb-3.3.1-natives-windows.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-stb\\3.3.1\\lwjgl-stb-3.3.1-natives-windows-arm64.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-stb\\3.3.1\\lwjgl-stb-3.3.1-natives-windows-x86.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-tinyfd\\3.3.1\\lwjgl-tinyfd-3.3.1.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-tinyfd\\3.3.1\\lwjgl-tinyfd-3.3.1-natives-windows.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl-tinyfd\\3.3.1\\lwjgl-tinyfd-3.3.1-natives-windows-arm64.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl\\3.3.1\\lwjgl-3.3.1.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl\\3.3.1\\lwjgl-3.3.1-natives-windows.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl\\3.3.1\\lwjgl-3.3.1-natives-windows-arm64.jar",
            "$gameDir\\libraries\\org\\lwjgl\\lwjgl\\3.3.1\\lwjgl-3.3.1-natives-windows-x86.jar",
            "$gameDir\\libraries\\org\\slf4j\\slf4j-api\\2.0.1\\slf4j-api-2.0.1.jar",
            "$versionDir\\1.20.1-forge-47.4.0.jar"
        ]);

        // 3. ModulePath
        $modulePath = implode(";", [
            "$gameDir\\libraries\\cpw\\mods\\bootstraplauncher\\1.1.2\\bootstraplauncher-1.1.2.jar",
            "$gameDir\\libraries\\cpw\\mods\\securejarhandler\\2.1.10\\securejarhandler-2.1.10.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm-commons\\9.7.1\\asm-commons-9.7.1.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm-util\\9.7.1\\asm-util-9.7.1.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm-analysis\\9.7.1\\asm-analysis-9.7.1.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm-tree\\9.7.1\\asm-tree-9.7.1.jar",
            "$gameDir\\libraries\\org\\ow2\\asm\\asm\\9.7.1\\asm-9.7.1.jar",
            "$gameDir\\libraries\\net\\minecraftforge\\JarJarFileSystems\\0.3.19\\JarJarFileSystems-0.3.19.jar"
        ]);

        // RAM из файла (если есть)
        $ramPath = getenv('APPDATA') . "/.mineshit/ram.txt";
        $ramMb = 4096; // дефолт
        if (file_exists($ramPath)) {
            $ramFileVal = trim(file_get_contents($ramPath));
            if (is_numeric($ramFileVal) && (int)$ramFileVal > 512 && (int)$ramFileVal < 65536) {
                $ramMb = (int)$ramFileVal;
            }
        }

        // 4. Полная команда для ProcessBuilder
        $args = [
            "-Xms{$ramMb}M",
            "-XX:+UnlockExperimentalVMOptions",
            "-XX:+DisableExplicitGC",
            "-XX:MaxGCPauseMillis=200",
            "-XX:+AlwaysPreTouch",
            "-XX:+ParallelRefProcEnabled",
            "-XX:+UseG1GC",
            "-XX:G1NewSizePercent=30",
            "-XX:G1MaxNewSizePercent=40",
            "-XX:G1HeapRegionSize=8M",
            "-XX:G1ReservePercent=20",
            "-XX:InitiatingHeapOccupancyPercent=15",
            "-XX:G1HeapWastePercent=5",
            "-XX:G1MixedGCCountTarget=4",
            "-XX:G1MixedGCLiveThresholdPercent=90",
            "-XX:G1RSetUpdatingPauseTimePercent=5",
            "-XX:+UseStringDeduplication",
            "-XX:MaxTenuringThreshold=1",
            "-XX:SurvivorRatio=32",
            "-Xmx{$ramMb}M",
            "-Dfile.encoding=UTF-8",
            "-XX:HeapDumpPath=MojangTricksIntelDriversForPerformance_javaw.exe_minecraft.exe.heapdump",
            "-Djava.library.path=$nativesDir",
            "-Djna.tmpdir=$nativesDir",
            "-Dorg.lwjgl.system.SharedLibraryExtractPath=$nativesDir",
            "-Dio.netty.native.workdir=$nativesDir",
            "-Dminecraft.launcher.brand=java-minecraft-launcher",
            "-Dminecraft.launcher.version=1.6.84-j",
            "-cp", $classpath,
            "-Djava.net.preferIPv6Addresses=system",
            "-DignoreList=bootstraplauncher,securejarhandler,asm-commons,asm-util,asm-analysis,asm-tree,asm,JarJarFileSystems,client-extra,fmlcore,javafmllanguage,lowcodelanguage,mclanguage,forge-,1.20.1-forge-47.4.0.jar",
            "-DmergeModules=jna-5.10.0.jar,jna-platform-5.10.0.jar",
            "-DlibraryDirectory=$gameDir\\libraries",
            "-p", $modulePath,
            "--add-modules", "ALL-MODULE-PATH",
            "--add-opens", "java.base/java.util.jar=cpw.mods.securejarhandler",
            "--add-opens", "java.base/java.lang.invoke=cpw.mods.securejarhandler",
            "--add-exports", "java.base/sun.security.util=cpw.mods.securejarhandler",
            "--add-exports", "jdk.naming.dns/com.sun.jndi.dns=java.naming",
            "-Xss2M",
            "cpw.mods.bootstraplauncher.BootstrapLauncher",
            "--username", $nickname,
            "--version", "1.20.1-forge-47.4.0",
            "--gameDir", $gameDir,
            "--assetsDir", $assetsDir,
            "--assetIndex", "5",
            "--uuid", $uuid,
            "--accessToken", $uuid,
            "--userType", "legacy",
            "--versionType", "release",
            "--width", "925",
            "--height", "530",
            "--launchTarget", "forgeclient",
            "--fml.forgeVersion", "47.4.0",
            "--fml.mcVersion", "1.20.1",
            "--fml.forgeGroup", "net.minecraftforge",
            "--fml.mcpVersion", "20230612.114412"
        ];

        // .bat
        $batFile = $gameDir . "\\run_mc.bat";
        $batLine = '"' . $javaw . '" ' . implode(' ', $args);
        $batContents = "@echo off\r\ncd /d \"$gameDir\"\r\n$batLine\r\npause\r\n";
        file_put_contents($batFile, $batContents);
        execute('cmd /c start "" "' . $batFile . '"');
    }

    /**
     * @event ModSettingsButton.click-Left
     */
    function doModSettingsButtonClickLeft(\php\gui\event\UXMouseEvent $e = null)
    {
        if (!$this->ModSettingsFragment->visible) {
            if ($this->ModSettingsFragment->form instanceof \app\forms\SettingsMods) {
                $this->ModSettingsFragment->form->syncCheckboxes();
            }
            $this->ModSettingsFragment->visible = true;
        } else {
            $this->ModSettingsFragment->visible = false;
        }
    }

    /**
     * @event LogoutButton.click-Left 
     */
    function doLogoutButtonClickLeft(UXMouseEvent $e = null)
    {
        $file = new \php\io\File("user.txt");
        if ($file->exists()) {
            $file->delete();
        }

        $form = app()->getForm('MainForm');
        $form->show();
        $this->hide();
    }

    /**
     * @event DiscordButton.click-Left 
     */
    function doDiscordButtonClickLeft(UXMouseEvent $e = null)
    {
$e = $event ?: $e; // legacy code from 16 rc-2

		browse('https://discord.gg/K5xeTx8xPR');

        // Открыть Discord ссылку или другое действие
    }

    /**
     * @event TelegramButton.click-Left 
     */
    function doTelegramButtonClickLeft(UXMouseEvent $e = null)
    {
$e = $event ?: $e; // legacy code from 16 rc-2

		browse('http://t.me/mscreate_ru');

        // Открыть Telegram ссылку или другое действие
    }

    /**
     * @event DonateButton.click-Left 
     */
    function doDonateButtonClickLeft(UXMouseEvent $e = null)
    {
$e = $event ?: $e; // legacy code from 16 rc-2

		browse('https://tbank.ru/cf/uSCxVtuotA');

        // Обработка пожертвований
    }

    /**
     * @event FolderButton.click-Left 
     */
    function doFolderButtonClickLeft(UXMouseEvent $e = null)
    {
        $gameFolder = getenv('APPDATA') . '\\.mineshit\\game\\';

        if (fs::isDir($gameFolder)) {
            execute('explorer "' . $gameFolder . '"');
        } else {
            alert("Папка не найдена:\n" . $gameFolder);
        }
    }

    /**
     * @event SettingsButton.click-Left 
     */
    function doSettingsButtonClickLeft(UXMouseEvent $e = null)
    {
        $form = app()->getForm('Settings');
        $form->setPreviousForm('MainFormLoginned');
        $form->show();
        $this->hide();
    }

    /**
     * @event ProfileButton.click-Left 
     */
    function doProfileButtonClickLeft(UXMouseEvent $e = null)
    {    
$e = $event ?: $e; // legacy code from 16 rc-2

		UXDialog::showAndWait('Раздел "Профиль" находится в разработке.  Функционал будет добавлен в будущих версиях лаунчера.');

        // ...
    }

    /**
     * Установить ник игрока в поле логина
     */
    function setPlayerName(string $login)
    {
        if (isset($this->LauncherLoginField)) {
            $this->LauncherLoginField->text = $login;
            $this->LauncherLoginField->editable = false;
        }
    }
}
