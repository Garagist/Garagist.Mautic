<?php

namespace Garagist\Mautic\Command;

use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\MauticService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use function \Neos\Flow\var_dump;

// TODO Remove var_dump and replace with proper logging
#[Flow\Scope('singleton')]
class MauticCommandController extends CommandController
{
    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected MauticService $mauticService;

    public function getCommand(string $emailIdentifier)
    {
        var_dump($this->apiService->findMauticRecordByEmailIdentifier($emailIdentifier));
    }

    public function segmentsCommand(string $emailIdentifier)
    {
        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        var_dump($this->mauticService->getSegmentsForEmail($email));
    }

    public function streamCommand(string $emailIdentifier)
    {
        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        var_dump($this->mauticService->getAuditLog($email));
    }

    public function sendTestEmailCommand(string $emailIdentifier, string $recipients)
    {
        $email = $this->mauticService->getByEmailIdentifier($emailIdentifier);
        $this->mauticService->sendExampleEmail($email, explode(',', $recipients));

        var_dump($email);
    }
}
