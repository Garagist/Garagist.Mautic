<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\UserService;

#[Flow\Scope('singleton')]
class TestEmailService
{
    /**
     * @var array|string
     */
    #[Flow\InjectConfiguration('testMail.recipients')]
    protected $recipientsFromSettings;

    #[Flow\Inject]
    protected UserService $userService;

    public function getTestEmailRecipients(): array
    {
        $testEmailRecipients = [];
        if ($this->userService->getCurrentUser()) {
            foreach ($this->userService->getCurrentUser()->getElectronicAddresses() as $electronicAddress) {
                if ($electronicAddress->getType() == 'Email') {
                    $testEmailRecipients[] = $electronicAddress->getIdentifier();
                }
            }
        }
        if (is_string($this->recipientsFromSettings)) {
            $testEmailRecipients[] = $this->recipientsFromSettings;
        }
        if (is_array($this->recipientsFromSettings)) {
            $testEmailRecipients = array_merge($testEmailRecipients, $this->recipientsFromSettings);
        }

        return array_unique(array_map('strtolower', $testEmailRecipients));
    }
}
