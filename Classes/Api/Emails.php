<?php

namespace Garagist\Mautic\Api;

use Mautic\Api\Emails as MauticEmails;

/**
 * Emails Context.
 */
class Emails extends MauticEmails
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
        return $this->makeRequest($this->endpoint . '/' . $id . '/example', ['recipients' => $recipients], 'POST');
    }
}
