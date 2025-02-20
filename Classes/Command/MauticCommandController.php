<?php

namespace Garagist\Mautic\Command;

use Garagist\Mautic\Service\NewsletterService;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\MauticService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use function Neos\Flow\var_dump;

#[Flow\Scope('singleton')]
class MauticCommandController extends CommandController
{
    #[Flow\Inject]
    protected NewsletterService $newsletterService;

    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected MauticService $mauticService;

    /**
     * Set up segments, forms, pages and emails for the newsletter
     *
     * @param string $domain The domain of the website, incl. protocol e.g. https://www.domain.tld
     * @param string $language The language of the website e.g. en or de
     * @param bool $informal Use informal language
     * @param bool $formal Use formal language. Wins over --informal
     * @param bool $singlePerson Sender is a single person
     * @param bool $group Sender is a group, e.g. a company or organization. Wins over --single-person
     * @param string|null $sender The name of the sender e.g. John Doe
     * @param string|null $no-sender Set no sender name. Wins over --sender
     * @return void
     */
    public function setupCommand(
        ?string $domain = null,
        ?string $language = null,
        ?bool $informal = null,
        ?bool $formal = null,
        ?bool $singlePerson = null,
        ?bool $group = null,
        ?string $sender = null,
        bool $noSender = false
    ): void {
        if (!$domain) {
            $domain = $this->output->askAndValidate(
                '<question> What is the domain of the website (incl. protocol) </question> ',
                function ($value) {
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new \InvalidArgumentException('Please enter a valid domain (incl. protocol)');
                    }
                    return $value;
                }
            );
        } elseif (!filter_var($domain, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Please enter a valid domain (incl. protocol)');
        }

        if (!$language || !in_array($language, ['de', 'en'])) {
            $language = $this->output->select(
                '<question> What is the language of the website </question> [<options=bold>German</>]',
                ['de' => 'German', 'en' => 'English'],
                'de'
            );
        }

        if ($formal === true) {
            $informal = false;
        } elseif (!isset($informal)) {
            $informal = $this->output->askConfirmation(
                '<question> Do you want to use informal language? </question> [y/<options=bold>N</>] ',
                false
            );
        }

        if ($group === true) {
            $singlePerson = false;
        } elseif (!isset($singlePerson)) {
            $singlePerson = $this->output->askConfirmation(
                '<question> Is the sender a single person? </question> [y/<options=bold>N</>] ',
                false
            );
        }

        if (!$noSender && !isset($sender)) {
            $sender = $this->output->ask('<question> What is the name of the sender? (optional) </question> ');
        }

        if (!$sender) {
            $sender = null;
        }

        $salutation = $informal ? 'informal' : 'formal';
        $typeOfContact = $singlePerson ? 'single' : 'group';

        $translationPostfix = sprintf('%s.%s', $informal ? 'informal' : 'formal', $singlePerson ? 'single' : 'group');

        $managedCategory = $this->newsletterService->setupManagedCategory($language);
        $this->outputLine('<info> ✅ Category </info>');

        $segments = $this->newsletterService->setupSegments($language, $managedCategory);
        $segmentIds = [
            'opt-in-pending' => $segments['opt-in-pending']['id'],
            'opt-in-confirmed' => $segments['opt-in-confirmed']['id'],
            'newsletter-default' => $segments['newsletter-default']['id'],
        ];
        $this->outputLine('<info> ✅ Segments </info>');

        $form = $this->newsletterService->setupForm($language, $salutation, $typeOfContact, $managedCategory);
        $this->outputLine('<info> ✅ Form </info>');

        $this->newsletterService->setupCampaign(
            $language,
            $salutation,
            $typeOfContact,
            $managedCategory,
            $form,
            $segmentIds
        );
        $this->outputLine('<info> ✅ Campaign </info>');
        return;

        $page = $this->newsletterService->setupPages($language, $translationPostfix, $managedCategory, $domain);
        $this->outputLine('<info> ✅ Pages </info>');

        $confirmPageId = $page['id'];

        return;
        $this->newsletterService->setupEmails(
            $language,
            $translationPostfix,
            $managedCategory,
            $confirmPageId,
            $sender
        );
        $this->outputLine('<info> ✅ Emails </info>');
        $this->outputLine('Setup command executed');
    }

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
