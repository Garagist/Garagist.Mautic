<?php
namespace Garagist\Mautic\Command;

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
     * @var ApiService
     */
    protected $apiService;

    /**
     * @Flow\Inject
     * @var MauticService
     */
    protected $mauticService;



    public function getCommand(string $neosIdentifier) {
        \Neos\Flow\var_dump($this->apiService->findEmailByNeosIdentifier($neosIdentifier));
    }

    public function segmentsCommand(string $neosIdentifier) {
        $email = $this->mauticService->getByEmailIdentifier($neosIdentifier);
        \Neos\Flow\var_dump($this->mauticService->getSegmentsForEmail($email));
    }

    public function streamCommand(string $neosIdentifier) {
        $email = $this->mauticService->getByEmailIdentifier($neosIdentifier);
        \Neos\Flow\var_dump($this->mauticService->getAuditLog($email));
    }

}
