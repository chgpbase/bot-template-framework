<?php
namespace BotTemplateFramework\Strategies;


use BotMan\BotMan\BotMan;
use BotMan\BotMan\Http\Curl;
use BotMan\BotMan\Messages\Attachments\Attachment;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\VK\Extensions\VKCarousel;
use BotMan\Drivers\VK\Extensions\VKKeyboard;
use BotMan\Drivers\VK\Extensions\VKKeyboardButton;
use BotMan\Drivers\VK\Extensions\VKKeyboardRow;
use BotMan\Drivers\VK\VkCommunityCallbackDriver;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Collection;

class VkComponentsStrategy implements IComponentsStrategy, IStrategy
{
    protected $bot;

    public function __construct(BotMan $bot)
    {
        $this->bot = $bot;
    }

    public function getBot()
    {
        return $this->bot;
    }

    public function reply($message, $additionalParameters = []) {
        return $this->bot->reply($message, $additionalParameters);
    }

    public function sendImage($imageUrl, $text = null, $options = null) {
        if ($text) {
            $this->sendMenuAndImage($imageUrl, $text, [], $options);
        } else {
            $message = OutgoingMessage::create()->withAttachment(new Image($imageUrl));
            $this->reply($message, $options);
        }
    }

    public function sendMenuAndImage($imageUrl, $text, array $markup, $options = null) {
        $this->reply(OutgoingMessage::create($text)->withAttachment(Image::url($imageUrl)), [ 'keyboard'=>$this->buildMenu([$markup], $options, true, true) ]);
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
            $this->sendMenu('', $globalButton, $option, false, true);
        }
    }


    public function sendText($text, $options = null) {
        return $this->reply($text, [], $options);
    }

    public function sendMenu($text, array $markup, $options = null, $inline=false, $onetime=true) {
        return $this->reply(OutgoingMessage::create($text), ['keyboard'=>$this->buildMenu($markup, $options, $inline, $onetime)]);
    }

    public function buildMenu(array $markup, $options = null, $inline=true, $onetime=true) {
        $keyboard = new VKKeyboard();
        $keyboard->setInline($inline);
        $keyboard->setOneTime($onetime);
        foreach ($markup as $submenu) {
            $buttons=[];
            foreach ($submenu as $callback => $title) {
                $button = new VKKeyboardButton();
                $schema = parse_url($callback, PHP_URL_SCHEME);
                $button->setText($title);

                if (in_array($schema, ['mailto', 'http', 'https', 'tel', 'share'])) {
                    $button->setType(VKKeyboardButton::TYPE_OPEN_LINK);
                    $button->setLink($callback);
                } else  $button->setValue($callback);
                $buttons[]=$button;
            }
            $keyboard->addRows( new VKKeyboardRow($buttons));
        }

        return $keyboard->toJSON();
    }
    public function sendCarousel(array $elements, $options = null) {
        $carousel=[];
        $message=OutgoingMessage::create('Carousel');
        $matchingMessage=$this->bot->getMessage();
        $driver= $this->bot->getDriver();
        $peer_id = (!empty($matchingMessage->getRecipient())) ? $matchingMessage->getRecipient() : $matchingMessage->getSender();
        $driver->types($matchingMessage);
        $getUploadUrl = $driver->api("photos.getMessagesUploadServer", [
            'peer_id' => ($driver->isConversation() ? 0 : $peer_id)
        ], true);


        foreach ($elements as $element) {
            $saveImg=null;
            $photo='';
            if(key_exists('url', $element)) {
                $attachment=Image::url($element['url']);
                $photo = $element['url'];
                $uploadImg = $driver->upload($getUploadUrl["response"]['upload_url'], $attachment->getUrl());

                if(!isset($uploadImg["photo"]) || $uploadImg["photo"] == "[]")
                    throw new VKDriverException("Can't upload image to VK. Please, be sure the photo has correct extension.");

                $saveImg = $driver->api('photos.saveMessagesPhoto', [
                    'photo' => $uploadImg['photo'],
                    'server' => $uploadImg['server'],
                    'hash' => $uploadImg['hash']
                ], true);

            }
            $buttons=[];
            if (array_key_exists('buttons', $element)) {
                foreach ($element['buttons'] as $callback => $title) {
                    $schema = parse_url($callback, PHP_URL_SCHEME);
                    if (in_array($schema, ['mailto', 'http', 'https', 'tel', 'share'])) {
                        $action=[
                            'type'=>'open_link',
                            'label'=>$title ,
                            'link'=>$callback,
                        ];
                    } else  $action=[
                        'type'=>'text',
                        'label'=>$title,
                        'payload'=>json_encode(["__message" => $callback], JSON_UNESCAPED_UNICODE)
                    ];
                    $buttons[]=['action'=>$action];
                }
            } else {
                $schema = parse_url($photo, PHP_URL_SCHEME);
                if (in_array($schema, ['mailto', 'http', 'https', 'tel', 'share']))
                $action=[
                    'type'=>'open_link',
                    'label'=>$element['title'] ,
                    'link'=>$photo,
                ];
                else
                    $action=[
                        'type'=>'text',
                        'label'=>$element['title'],
                    ];

                $buttons[]=['action'=>$action];

            }



            $carousel_item=[
                'title'=>$element['title'],
                'action'=>[
                    'type'=>($photo!='')?'open_link':'text',
                    'link'=>($photo!='')?$photo:$element['title']
                ],
                'buttons'=> $buttons

            ];
            //add descrition
            if($saveImg!=null) {
                $carousel_item['photo_id']=$saveImg['response'][0]['owner_id'].'_'.$saveImg['response'][0]['id'];
                if(key_exists('description', $element)) $carousel_item['description']=!empty($element['description'])?$element['description']:'';
            }
            else $carousel_item['description']=(key_exists('description', $element))?$element['description']:'';

            $carousel[]=$carousel_item;
        }

        return $this->reply($message, ['template'=>json_encode([
            'type'=>'carousel',
            'elements'=>$carousel
        ], JSON_FORCE_OBJECT)
            ]);
    }

    public function sendAudio($url, $text = null, $options = null) {
//        $this->reply(OutgoingMessage::create($text, new Audio($url)), [], $options);
//        $this->reply(OutgoingMessage::create($text)->withAttachment(new Audio($url)), [], $options);
        $this->reply(OutgoingMessage::create(' Uploading audio is restricted by VK API'), []);

    }

    public function sendVideo($url, $text = null, $options = null) {
//        $this->reply(OutgoingMessage::create($text, new Video($url)), [], $options);
//        $this->reply(OutgoingMessage::create($text)->withAttachment(new Videodeo($url)), [], $options);
        $this->reply(OutgoingMessage::create(' Uploading video is restricted by VK API'), []);


    }

    public function sendFile($url, $text = null, $options = null) {
//        $this->reply(OutgoingMessage::create($text, new File($url)), [], $options);
        $this->reply(OutgoingMessage::create($text)->withAttachment(new File($url)), [], $options);

    }

    public function sendPayload($payload) {
        // TODO: Implement sendPayload() method.
    }

    public function requireLocation($text, $options = null) {
        $this->reply(Question::create($text)->addAction(QuickReplyButton::create()->type('location')));
    }

    public function requireLocationPayload($text, $options = null) {
//        return Question::create($text)->addAction(QuickReplyButton::create()->type('location'));
        return null;

    }

    public function requirePhonePayload($text, $options = null) {
//        return Question::create($text)->addAction(QuickReplyButton::create()->type('user_phone_number'));
        return null;

    }

    public function requireEmailPayload($text, $options = null) {
//        return Question::create($text)->addAction(QuickReplyButton::create()->type('user_email'));
        return null;

    }

    public function sendQuickButtons($text, array $markup, $options = null) {
        $question = new Question($text);
        foreach($markup as $submenu) {
            foreach($submenu as $callback=>$title) {
                $question->addButton((new Button($title))->value($callback));
            }
        }
        $this->reply($question);
    }


}