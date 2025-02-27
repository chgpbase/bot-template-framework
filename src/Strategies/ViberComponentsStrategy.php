<?php

namespace BotTemplateFramework\Strategies;


use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use TheArdent\Drivers\Viber\Extensions\CarouselTemplate;
use TheArdent\Drivers\Viber\Extensions\FileTemplate;
use TheArdent\Drivers\Viber\Extensions\KeyboardTemplate;
use TheArdent\Drivers\Viber\Extensions\MenuTemplate;
use TheArdent\Drivers\Viber\Extensions\PictureTemplate;
use Exception;
use TheArdent\Drivers\Viber\Extensions\VideoTemplate;

class ViberComponentsStrategy implements IComponentsStrategy, IStrategy {
    protected $bot;

    public function __construct(BotMan $bot) {
        $this->bot = $bot;
    }

    public function getBot() {
        return $this->bot;
    }

    public function reply($message, $additionalParameters = []) {
        return $this->bot->reply($message, $additionalParameters);
    }

    public function sendImage($imageUrl, $text = null, $options = null) {
        if ($text) {
            $this->reply(new PictureTemplate($imageUrl, $text));
        } else {
            $message = OutgoingMessage::create()->withAttachment(new Image($imageUrl));
            $this->reply($message);
        }
    }

    public function sendMenu($text, array $markup, $options = null) {
        $menu = new KeyboardTemplate($text, $options['DefaultHeight'] ?? false);
        $this->buildMenu($markup, $menu, $options);
        $this->reply($menu);
    }

    public function sendMenuAndImage($imageUrl, $text, array $markup, $options = null) {
        $menu = new MenuTemplate($text, $imageUrl, $options['DefaultHeight'] ?? false);
        $this->buildMenu([$markup], $menu, $options);

        $this->reply($menu);
    }

    public function sendText($text, $options = null) {
        $this->reply($text);
    }

    public function sendList(array $elements, array $globalButton = null, $options = null) {
        foreach ($elements as $item) {
            if (array_key_exists('buttons', $item)) {
                $this->sendMenuAndImage($item['url'], $item['title'], $item['buttons'], $options);
            } else {
                $this->sendImage($item['url'], $item['title'], $options);
            }
        }

        if ($globalButton) {
            $this->sendMenu('', $globalButton, $options);
        }
    }

    public function sendCarousel(array $elements, $options = null) {
        foreach ($elements as $key=>$item) {
            if (!array_key_exists('buttons', $item)) {
                $elements[$key]['buttons']=[$item['url']=>$item['title']];
            }
        }
            $this->reply(new CarouselTemplate($elements));
    }

    public function sendQuickButtons($text, array $markup, $options = null) {
        $this->sendMenu($text, $markup, $options);
    }

    public function sendAudio($url, $text = null, $options = null) {
        $this->reply(OutgoingMessage::create($text, new Audio($url)), $options ?? []);
    }

    public function sendVideo($url, $text = null, $options = null) {
//        $this->reply(OutgoingMessage::create($text, new Video($url)));
        $this->reply(new VideoTemplate($url, $text));
    }

    public function sendFile($url, $text = null, $options = null) {
       // $this->reply(OutgoingMessage::create($text, new File($url)), $options ?? []);
        $this->reply(new FileTemplate($url, $text));
    }

    public function sendPayload($payload){
        $parameters = array_merge_recursive([
            'receiver' => $this->bot->getMessage()->getSender(),
        ], $payload);

        $this->bot->sendPayload($parameters);
    }

    public function requireLocation($text, $options = null) {
        $this->reply((new KeyboardTemplate($text, $options['DefaultHeight'] ?? false))->addButton(
            $options['title'] ?? trans('bot.share_location_btn'),
            'location-picker', 'location-picker', $options['TextSize'] ?? 'regular',
            $options['BgColor'] ?? null, 6, $options['Silent'] ?? false
        ));
    }

    public function requireLocationPayload($text, $options = null) {
        return (new KeyboardTemplate($text, $options['DefaultHeight'] ?? false))->addButton(
            $options['title'] ?? trans('bot.share_location_btn'),
            'location-picker', 'location-picker', $options['TextSize'] ?? 'regular',
            $options['BgColor'] ?? null, 6, $options['Silent'] ?? false
        );
    }

    public function requirePhonePayload($text, $options = null) {
        return (new KeyboardTemplate($text, $options['DefaultHeight'] ?? false))->addButton(
            $options['title'] ?? trans('bot.share_phone_btn'),
            'share-phone', 'share-phone', $options['TextSize'] ?? 'regular',
            $options['BgColor'] ?? null, 6, $options['Silent'] ?? false
        );
    }

    public function requireEmailPayload($text, $options = null) {
        return null;
    }

    protected function buildMenu(array $markup, $keyboard, $options = null) {
        foreach ($markup as $submenu) {
            $count = count($submenu);
            if ($count > 6 || $count == 5 || $count == 4) {
                $width =  6;
            } else {
                $width = 6 / $count;
            }
            foreach ($submenu as $callback => $title) {
                $schema = parse_url($callback, PHP_URL_SCHEME);
                if (in_array($schema, ['mailto', 'http', 'https', 'tel', 'share'])) {
                    if ($schema == 'share') {
                        $keyboard->addButton($title, 'open-url', 'viber://forward?text='.substr($callback, 8), $options['TextSize'] ?? 'regular', $options['BgColor'] ?? null, $width, true);
                    } else {
                        $keyboard->addButton($title, 'open-url', $callback, $options['TextSize'] ?? 'regular', $options['BgColor'] ?? null, $width, $options['Silent'] ?? false);
                    }
                    continue;
                }
                $keyboard->addButton($title, 'reply', $callback, $options['TextSize'] ?? 'regular', $options['BgColor'] ?? null, $width, $options['Silent'] ?? false);
            }
        }

        return $keyboard;
    }
}