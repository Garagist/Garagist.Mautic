<?php

namespace Garagist\Mautic\Command;

use Garagist\Mautic\Service\NewsletterService;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\MauticService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use function Neos\Flow\var_dump;
use InvalidArgumentException;

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
     * Set up segments, forms, pages and emails for the newsletter interactively
     * @return void
     * @throws CommandException
     */
    public function setupInteractiveCommand() {
        $this->outputLine('');
        $this->outputLine('');
        $this->outputLine('         __  __             _   _');
        $this->outputLine('        |  \/  | __ _ _   _| |_(_) ___');
        $this->outputLine('        | |\/| |/ _` | | | | __| |/ __|');
        $this->outputLine('        | |  | | (_| | |_| | |_| | (__');
        $this->outputLine('        |_|  |_|\__,_|\__,_|\__|_|\___|');
        $this->outputLine('');
        $this->outputLine('          Integrated into Neos. Easy.');
        $this->outputLine('');
        $this->outputLine('');
        $this->outputLine('');
        $this->outputLine(' Let\'s start with a few questions to setup Mautic');
        $this->outputLine('');

        $deleteThemes = $this->output->askConfirmation(
            ' Do you want to delete all themes? [<info>Y</info>/n] ',
            true
        );
        $domain = $this->output->askAndValidate(
            ' What is the domain of the website? ',
            function ($value) {
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('Please enter a valid domain (incl. protocol)');
                }
                return $value;
            }
        );
        $language = $this->output->select(
            ' What is the language of the website? ',
            ['de' => 'German', 'en' => 'English']
        );
        $informal = $this->output->askConfirmation(
            ' Do you want to use informal language? [y/<info>N</info>] ',
            false
        );
        $singlePerson = $this->output->askConfirmation(
            ' Is the sender a single person? [y/<info>N</info>] ',
            false
        );
        $sender = $this->output->ask(' What is the name of the sender? (optional) ');
        $this->outputLine('');
        $this->outputLine('');
        $this->outputLine(' Thank you for your input. Let me setup Mautic for you.');
        $this->outputLine('');
        $this->outputLine('');

        $this->setupCommand($domain, $language, $informal, null, $singlePerson, null, $deleteThemes, $sender);
    }

    /**
     * Set up segments, forms, pages and emails for the newsletter
     *
     * @param string $domain The domain of the website, incl. protocol e.g. https://www.domain.tld
     * @param string $language The language of the website e.g. en or de
     * @param bool $informal Use informal language
     * @param bool $formal Use formal language. Wins over --informal
     * @param bool $singlePerson Sender is a single person
     * @param bool $group Sender is a group, e.g. a company or organization. Wins over --single-person
     * @param bool $deleteAllThemes Delete all themes
     * @param string|null $sender The name of the sender e.g. John Doe
     * @return void
     */
    public function setupCommand(
        string $domain,
        string $language,
        ?bool $informal = null,
        ?bool $formal = null,
        ?bool $singlePerson = null,
        ?bool $group = null,
        ?bool $deleteAllThemes = null,
        ?string $sender = null,
    ): void {
        if (!filter_var($domain, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Please provide a valid domain (inclusive protocol)');
        }
        if (!in_array($language, ['de', 'en'])) {
            throw new InvalidArgumentException('Please provide a valid language (de or en)');
        }

        if ($formal === true) {
            $informal = false;
        }

        if ($group === true) {
            $singlePerson = false;
        }

        $salutation = $informal ? 'informal' : 'formal';
        $typeOfContact = $singlePerson ? 'single' : 'group';

        $translationPostfix = sprintf('%s.%s', $informal ? 'informal' : 'formal', $singlePerson ? 'single' : 'group');

        if ($deleteAllThemes) {
            $themes = $this->apiService->getList(ApiService::ENDPOINT_THEMES);
            $numberOfThemes = count($themes['themes']);
            $this->output->progressStart($numberOfThemes);
            foreach ($themes['themes'] as $theme) {
                $this->output->progressAdvance();
                $this->apiService->delete(ApiService::ENDPOINT_THEMES, $theme['key']);
            }
            $this->output->progressFinish();
            $this->outputLine('');
            $this->outputLine('<info> ✅ %s Themes deleted </info>', [$numberOfThemes]);
        }

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
