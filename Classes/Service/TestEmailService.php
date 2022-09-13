<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\UserService;

/**
 * @Flow\Scope("singleton")
 */
class TestEmailService
{
    /**
     * @var array|string
     * @Flow\InjectConfiguration(path="testMail.addresses")
     */
    protected $addressesFromSettings;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    public function getTestEmailAdresses(): array
    {
        $testEmailAdresses = [];
        foreach ($this->userService->getCurrentUser()->getElectronicAddresses() as $electronicAddress) {
            if ($electronicAddress->getType() == 'Email') {
                $testEmailAdresses[] = $electronicAddress->getIdentifier();
            }
        }
        if (is_string($this->addressesFromSettings)) {
            $testEmailAdresses[] = $this->addressesFromSettings;
        }
        if (is_array($this->addressesFromSettings)) {
            $testEmailAdresses = array_merge($testEmailAdresses, $this->addressesFromSettings);
        }

        return array_unique(array_map('strtolower', $testEmailAdresses));
    }
}
