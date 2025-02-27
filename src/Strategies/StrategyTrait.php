<?php

namespace BotTemplateFramework\Strategies;

use BotMan\BotMan\BotMan;
use BotMan\Drivers\VK\VkCommunityCallbackDriver;

trait StrategyTrait {
    /**
     * @var IComponentsStrategy
     */
    protected $strategy;

    public function strategy(BotMan $bot) {
        if ($this->strategy) {
            return $this->strategy;
        }

        return $this->strategy = StrategyTrait::initStrategy($bot);
    }

    public static function initStrategy(BotMan $bot) {
        $driveName = self::driverName($bot);

        if ($driveName) {
            $clazz = "App\\Strategies\\" . $driveName;
            $componentsStrategy = "BotTemplateFramework\\Strategies\\" . $driveName . "ComponentsStrategy";

            $is_class_exists = class_exists($clazz);
            if ($is_class_exists) {
                $instance = new $clazz($bot);
                if ($instance instanceof Strategy) {
                    $instance->setComponentsStrategy(new $componentsStrategy($bot));
                }
            } else {
                $instance = new $componentsStrategy($bot);
            }
            return $instance;
        }
        return null;
    }

    public static function driverName(BotMan $bot) {
        $driver = $bot->getDriver();
        $driveName = null;
        if ($driver instanceof \BotMan\Drivers\BotFramework\BotFrameworkDriver) {
            $driveName = 'Skype';
        } elseif ($driver instanceof \BotMan\Drivers\Facebook\FacebookDriver) {
            $driveName = 'Facebook';
        } elseif ($driver instanceof \BotMan\Drivers\Telegram\TelegramDriver) {
            $driveName = 'Telegram';
        } elseif ($driver instanceof \TheArdent\Drivers\Viber\ViberDriver) {
            $driveName = 'Viber';
        } elseif ($driver instanceof \BotMan\Drivers\AmazonAlexa\AmazonAlexaDriver) {
            $driveName = 'Alexa';
        } elseif ($driver instanceof \BotMan\Drivers\Web\WebDriver) {
            $driveName = 'Web';
        } elseif ($driver instanceof VkCommunityCallbackDriver) {
            $driveName = 'Vk';
        }
        return $driveName;

    }
}