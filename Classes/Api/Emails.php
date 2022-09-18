<?php

namespace Garagist\Mautic\Api;

/**
 * Emails Context.
 */
class Emails extends \Mautic\Api\Emails
{
    /**
     * Send test email to the assigned lists.
     *
     * @param int $id
     * @param array $recipients
     * @return array|mixed
     * @throws \Exception
     */
    public function sendExample(int $id, array $recipients)
    {
        return $this->makeRequest($this->endpoint.'/'.$id.'/example', ['recipients' => $recipients], 'POST');
    }
}