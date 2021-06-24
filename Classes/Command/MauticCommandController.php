<?php
namespace Garagist\Mautic\Command;

use Emk\Church\Service\ChurchLetterService;
use Garagist\Mautic\Domain\Model\MauticEmail;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\MauticService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 *
 * @Flow\Scope("singleton")
 */
class MauticCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var ChurchLetterService
     */
    protected $churchLetterService;

    /**
     * @Flow\Inject
     * @var MauticService
     */
    protected $mauticService;

    /**
     * @throws \Neos\ContentRepository\Exception\NodeException
     */
    function updateEmailCommand() {
        $result = $this->churchLetterService->updateEmail('20cfbec0-03f8-4143-882c-95f4fb6f9b29');
        $this->outputLine($result);
    }

    function createEmailCommand() {
        $this->mauticService->createEmail('114b2100-bae6-4af7-b7a0-6cc9e4fd83e3', 'http://dev.neos.emk.loc/de/gemeinden/wien-fuenfhaus/gemeinde-news/2021/150-jahre-emk-oesterreich.maizzle');
    }

    function publishEmailCommand(\DateTime $date = null) {
        $this->mauticService->publishEmail('20cfbec0-03f8-4143-882c-95f4fb6f9b29');
    }

    function unPublishEmailCommand(\DateTime $date = null) {
        $this->mauticService->unPublishEamil('20cfbec0-03f8-4143-882c-95f4fb6f9b29');
    }

    function sendEmailCommand(\DateTime $date = null) {
        $this->mauticService->sendEmail('20cfbec0-03f8-4143-882c-95f4fb6f9b29');
    }

}