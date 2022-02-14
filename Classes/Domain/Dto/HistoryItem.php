<?php

namespace Garagist\Mautic\Domain\Dto;

use Neos\Flow\Annotations as Flow;
use DateTime;

class HistoryItem
{

    /**
     * @var string,
     */
    private $type;

    /**
     * @var DateTime,
     */
    private $date;

    /**
     * @var string
     */
    private $message;

    /**
     * @var bool
     */
    private $error;

    public function __construct($type, $date, $message, $error = false)
    {
        $this->type = $type;
        $this->date = $date;
        $this->message = $message;
        $this->error = $error;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return DateTime
     */
    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * @param DateTime $date
     */
    public function setDate(DateTime $date): void
    {
        $this->date = $date;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * @return bool
     */
    public function isError()
    {
        return $this->error;
    }

    /**
     * @param bool $error
     */
    public function setError($error): void
    {
        $this->error = $error;
    }
}
