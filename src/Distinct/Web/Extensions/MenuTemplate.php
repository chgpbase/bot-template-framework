<?php

namespace BotTemplateFramework\Distinct\Web\Extensions;

use BotMan\BotMan\Interfaces\WebAccess;
use BotMan\BotMan\Messages\Attachments\Attachment;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use JsonSerializable;

class MenuTemplate implements JsonSerializable, OutgoingMessage
{
    /** @var string */
    protected $text;

    /** @var array */
    protected $buttons = [];

    public function __construct($message = null, Attachment $attachment = null)
    {
        $this->message = $message;
        $this->attachment = $attachment;
    }

    /**
     * @param string $message
     * @param Attachment $attachment
     * @return OutgoingMessage
     */
    public static function create($message = null, Attachment $attachment = null)
    {
        return new static($message, $attachment);
    }

    /**
     * @param  ElementButton  $button
     * @return $this
     */
    public function addButton(ElementButton $button)
    {
        $this->buttons[] = $button->toArray();

        return $this;
    }

    /**
     * @param  array  $buttons
     * @return $this
     */
    public function addButtons(array $buttons)
    {
        foreach ($buttons as $button) {
            if ($button instanceof ElementButton) {
                $this->buttons[] = $button->toArray();
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => $this->text,
                    'buttons' => $this->buttons,
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Get the instance as a web accessible array.
     * This will be used within the WebDriver.
     *
     * @return array
     */
    public function toWebDriver()
    {
        return [
            'type' => 'buttons',
            'text' => $this->text,
            'buttons' => $this->buttons,
        ];
    }
}