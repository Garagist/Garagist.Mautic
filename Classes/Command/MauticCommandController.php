<?php

namespace Garagist\Mautic\Command;

use Carbon\Newsletter\Service\NodeService;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\MauticService;
use Garagist\Mautic\Service\SetupService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use InvalidArgumentException;

#[Flow\Scope('singleton')]
class MauticCommandController extends CommandController
{
    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected MauticService $mauticService;

    #[Flow\Inject]
    protected NodeService $nodeService;

    /**
     * Set up segments, forms, pages and emails for the newsletter interactively
     * @return void
     * @throws CommandException
     */
    public function setupInteractiveCommand()
    {
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

        $keyedOptions = [];
        $options = [];
        foreach ($this->nodeService->getSites() as $site) {
            $description = sprintf('%s (%s)', $site['name'], $site['domain'] ?? 'No domain set');
            $keyedOptions[$description] = $site;
            $options[] = $description;
        }

        switch (count($options)) {
            case 0:
                $this->quitOnError('No sites found');
            // no break
            case 1:
                $key = array_key_first($keyedOptions);
                $this->successMessage('Found one configured Neos site', null, $key);
                break;
            default:
                $key = $this->output->select(
                    ' For which site you want to configure Mautic? [<info>0</info>]',
                    $options,
                    0
                );
                break;
        }

        $siteNode = $keyedOptions[$key]['node'];
        $domain = $keyedOptions[$key]['domain'] ?? null;

        // Get Newsletter container
        if ($siteNode->getNodeType()->isOfType('Carbon.Newsletter:Document.HomePage')) {
            $newsletterNode = $siteNode;
            $this->successMessage('The site node is a newsletter container');
        } else {
            $newsletterKeyedOptions = [];
            $newsletterOptions = [];
            foreach (
                $this->nodeService->findNodesByNodeTypeNameAndPathEnd($siteNode, 'Carbon.Newsletter:Mixin.Container')
                as $node
            ) {
                $description = sprintf('%s (%s)', $node->getProperty('title'), $node->getIdentifier());
                $newsletterKeyedOptions[$description] = $node;
                $newsletterOptions[] = $description;
            }

            switch (count($newsletterOptions)) {
                case 0:
                    $this->quitOnError('No newsletter page found. Please create it first in the Neos Backened.');
                // no break
                case 1:
                    $newsletterNode = array_key_first($newsletterKeyedOptions);
                    $this->successMessage('Found following newsletter container:', null, $newsletterNode);
                    break;

                default:
                    $newsletterNode = $this->output->select(
                        ' What is the container for newsletter? [<info>0</info>]',
                        $newsletterOptions,
                        0
                    );
                    break;
            }
            $newsletterNode = $newsletterKeyedOptions[$newsletterNode];
        }

        $nodes = $this->getNewsletterNodes($newsletterNode);

        if (!$domain) {
            $domain = $this->output->askAndValidate(' What is the domain of the website? ', function ($value) {
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('Please enter a valid domain (incl. protocol)');
                }
                return $value;
            });
        }

        $this->successMessage('Set domain to %s', [$domain], marginBottom: true);

        $deleteThemes = $this->output->askConfirmation(' Do you want to delete all themes? [<info>Y</info>/n] ', true);

        $language = $this->output->select(' What is the language of the website? ', [
            'de' => 'German',
            'en' => 'English',
        ]);
        $informal = $this->output->askConfirmation(' Do you want to use informal language? [y/<info>N</info>] ', false);
        $singlePerson = $this->output->askConfirmation(' Is the sender a single person? [y/<info>N</info>] ', false);
        $sender = $this->output->ask(' What is the name of the sender? (optional) ');
        $this->outputLine('');
        $this->outputLine('');
        $this->outputLine(' Thank you for your input. Let me setup Mautic for you.');
        $this->outputLine('');
        $this->outputLine('');
        $this->setupWithNode($domain, $nodes, $language, $informal, $singlePerson, $deleteThemes, $sender);
    }

    /**
     * Get nodes for newsletter
     *
     * @param NodeInterface $node
     * @return array
     */
    private function getNewsletterNodes(NodeInterface $node): array
    {
        $confirmed = $this->nodeService->findNodeByNodeTypeNameAndPathEnd(
            $node,
            'Carbon.Newsletter:Document.Page.Generated',
            '/system/confirmed'
        );
        $mailSubscribe = $this->nodeService->findNodeByNodeTypeNameAndPathEnd(
            $confirmed,
            'Carbon.Newsletter:Document.Transactional.Generated',
            '/mail-subscribe'
        );
        $mailSubscribeRepeat = $this->nodeService->findNodeByNodeTypeNameAndPathEnd(
            $confirmed,
            'Carbon.Newsletter:Document.Transactional.Generated',
            '/mail-subscribe-repeat'
        );

        $settings = $this->nodeService->findNodeByNodeTypeNameAndPathEnd(
            $node,
            'Carbon.Newsletter:Document.Page.Generated',
            '/system/settings'
        );
        $mailSettings = $this->nodeService->findNodeByNodeTypeNameAndPathEnd(
            $settings,
            'Carbon.Newsletter:Document.Transactional.Generated',
            '/mail-settings'
        );

        $deleted = $this->nodeService->findNodeByNodeTypeNameAndPathEnd(
            $node,
            'Carbon.Newsletter:Document.Page.Generated',
            '/system/deleted'
        );
        $mailDelete = $this->nodeService->findNodeByNodeTypeNameAndPathEnd(
            $deleted,
            'Carbon.Newsletter:Document.Transactional.Generated',
            '/mail-delete'
        );

        return [
            'container' => $node,
            'confirmed' => $confirmed,
            'mailSubscribe' => $mailSubscribe,
            'mailSubscribeRepeat' => $mailSubscribeRepeat,
            'settings' => $settings,
            'mailSettings' => $mailSettings,
            'deleted' => $deleted,
            'mailDelete' => $mailDelete,
        ];
    }

    /**
     * Output a success message
     *
     * @param string $message
     * @param array|null $arguments
     * @param string $messageOutsideInfoBlock
     * @return void
     */
    private function successMessage(
        $message,
        ?array $arguments = null,
        string $messageOutsideInfoBlock = '',
        bool $marginBottom = false
    ): void {
        if ($arguments && count($arguments)) {
            $message = vsprintf($message, $arguments);
        }
        $this->outputLine('');
        $this->outputLine('<info> ✅ %s </info> %s', [$message, $messageOutsideInfoBlock]);
        if ($marginBottom) {
            $this->outputLine('');
        }
    }

    /**
     * Quit the command with an error message
     *
     * @param string|null $message
     * @return void
     */
    private function quitOnError(?string $message = null): void
    {
        if ($message) {
            $this->outputLine('');
            $this->outputLine('');
            $this->outputLine('<error> ✖ %s </error>', [$message]);
        }
        $this->outputLine('');
        $this->outputLine('');
        $this->quit(1);
    }

    /**
     * Delete all themes
     *
     * @return void
     */
    public function deleteThemesCommand(): void
    {
        $themes = $this->apiService->getList(ApiService::ENDPOINT_THEMES);
        $numberOfThemes = count($themes['themes']);
        if ($numberOfThemes === 0) {
            $this->successMessage('No themes found');
        } else {
            $this->output->progressStart($numberOfThemes);
            foreach ($themes['themes'] as $theme) {
                $this->output->progressAdvance();
                $this->apiService->delete(ApiService::ENDPOINT_THEMES, $theme['key']);
            }
            $this->output->progressFinish();
            $this->successMessage('%s Themes deleted', [$numberOfThemes]);
        }
    }

    /**
     * Make API calls to mautic
     *
     * @param string $domain
     * @param NodeInterface[] $nodes
     * @param string $language
     * @param boolean $informal
     * @param boolean $singlePerson
     * @param boolean $deleteThemes
     * @param string|null $sender
     * @return void
     */
    private function setupWithNode(
        string $domain,
        array $nodes,
        string $language,
        bool $informal,
        bool $singlePerson,
        bool $deleteThemes,
        ?string $sender = null
    ): void {
        $salutation = $informal ? 'informal' : 'formal';
        $typeOfContact = $singlePerson ? 'single' : 'group';

        $service = new SetupService($language, $salutation, $typeOfContact, $domain, $sender, $nodes);

        if ($deleteThemes) {
            $this->deleteThemesCommand();
        }

        $service->setCategory();
        $this->successMessage('Configure system category in Mautic');

        $service->setSegments();
        $this->successMessage('Configure segments in Mautic');

        $service->setForms();
        $this->successMessage('Configure forms in Mautic');

        $service->setEmails();
        $this->successMessage('Configure emails in Mautic');

        $service->setCampaigns();
        $this->successMessage('Configure campaigns in Mautic');

        $service->setFormId();
        $this->successMessage('Configure forms in Neos', marginBottom: true);
    }

    /**
     * Set up segments, forms, pages and emails for the newsletter
     *
     * @param string $identifier Identifier of the newsletter container
     * @param string $language Language of the website (en or de)
     * @param string $domain Domain of the website, incl. protocol e.g. https://www.domain.tld
     * @param bool $informal Use informal language
     * @param bool $formal Use formal language. Wins over --informal
     * @param bool $singlePerson Sender is a single person
     * @param bool $group Sender is a group, e.g. a company or organization. Wins over --single-person
     * @param bool $deleteAllThemes Delete all themes
     * @param string|null $sender The name of the sender e.g. John Doe
     * @return void
     */
    public function setupCommand(
        string $identifier,
        string $language,
        ?string $domain = null,
        ?bool $informal = null,
        ?bool $formal = null,
        ?bool $singlePerson = null,
        ?bool $group = null,
        ?bool $deleteAllThemes = null,
        ?string $sender = null
    ): void {
        if (!in_array($language, ['de', 'en'])) {
            throw new InvalidArgumentException('Please provide a valid language (de or en)');
        }

        $sites = $this->nodeService->getSites();
        $domainFromSite = null;
        $newsletterNode = null;
        foreach ($sites as $siteNode) {
            $nodeInSite = $this->nodeService->findNodeById($siteNode['node'], $identifier, true);
            if ($nodeInSite) {
                $domainFromSite = $siteNode['domain'];
                $newsletterNode = $nodeInSite;
                break;
            }
        }

        if (!$newsletterNode) {
            throw new InvalidArgumentException('No newsletter container found');
        }
        if (!$newsletterNode->getNodeType()->isOfType('Carbon.Newsletter:Mixin.Container')) {
            throw new InvalidArgumentException('The provided node is not a newsletter container');
        }

        $domain = $domain ?? $domainFromSite;
        if (!$domain) {
            throw new InvalidArgumentException('Please provide a domain');
        }
        if (!filter_var($domain, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Please provide a valid domain (inclusive protocol)');
        }

        if ($formal === true) {
            $informal = false;
        }

        if ($group === true) {
            $singlePerson = false;
        }

        if (!isset($informal)) {
            $informal = false;
        }

        if (!isset($singlePerson)) {
            $singlePerson = false;
        }
        if (!isset($deleteAllThemes)) {
            $deleteAllThemes = false;
        }

        $nodes = $this->getNewsletterNodes($newsletterNode);

        $this->setupWithNode($domain, $nodes, $language, $informal, $singlePerson, $deleteAllThemes, $sender);
    }
}
