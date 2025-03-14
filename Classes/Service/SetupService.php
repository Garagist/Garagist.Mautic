<?php

namespace Garagist\Mautic\Service;

use Carbon\Newsletter\Service\NodeService;
use Carbon\Newsletter\Utils;
use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\EmailService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;

class SetupService
{
    #[Flow\Inject]
    protected ApiService $apiService;

    #[Flow\Inject]
    protected NodeService $nodeService;

    #[Flow\Inject]
    protected EmailService $emailService;

    protected array $fetchedForms = [];
    protected array $fetchedCampaigns = [];
    protected array $fetchedCategories = [];
    protected array $newsletterSystemConfig = [
        'systemCategory' => null,
        'systemSegments' => [],
    ];

    protected array $emails = [];

    protected string $language = 'en';

    protected string $salutation = 'formal';

    protected string $typeOfContact = 'group';

    protected string $domain = '';

    protected string $sender = '';

    /**
     * @var NodeInterface[]
     */
    protected array $nodes = [];

    /**
     * @var int[]
     */
    protected array $categories = [];

    protected array $segments = [];

    protected array $forms = [];

    public function __construct(
        string $language,
        string $salutation,
        string $typeOfContact,
        string $domain,
        string $sender,
        array $nodes
    ) {
        $this->language = $language;
        $this->salutation = $salutation;
        $this->typeOfContact = $typeOfContact;
        $this->domain = $domain;
        $this->sender = $sender;
        $this->nodes = $nodes;
    }

    public function setCategories(): void
    {
        $this->fetchedCategories = $this->apiService->getList(ApiService::ENDPOINT_CATEGORIES)['categories'];
        $systemId = $this->setCategory('system', '#d1ffcf');
        $newsletterId = $this->setCategory('newsletter', '#cfd5ff');
        $this->categories = [
            'system' => $systemId,
            'newsletter' => $newsletterId,
        ];
        $this->newsletterSystemConfig['systemCategory'] = $systemId;
    }

    /**
     * Setup managed category in mautic
     */
    private function setCategory(string $alias, string $color): int
    {
        foreach ($this->fetchedCategories as $category) {
            if ($category['alias'] == $alias) {
                return $category['id'];
            }
        }

        $data = [
            'title' => Utils::translate(['category', $alias], $this->language),
            'alias' => $alias,
            'description' => Utils::translate(['category', $alias, 'description'], $this->language),
            'color' => $color,
            'bundle' => 'global',
        ];

        $category = $this->apiService->create(ApiService::ENDPOINT_CATEGORIES, $data);
        return $category['category']['id'];
    }

    /**
     * Setup segments in mautic
     */
    public function setSegments(): void
    {
        $fetchedSegments = $this->apiService->getList(ApiService::ENDPOINT_SEGMENTS);
        $segmentNames = ['opt-in-pending', 'opt-in-confirmed', 'newsletter-default'];

        $segments = [];
        foreach ($fetchedSegments['lists'] as $segment) {
            if (in_array($segment['alias'], $segmentNames)) {
                $segments[$segment['alias']] = $segment;
            }
        }

        foreach ($segmentNames as $key) {
            if (!isset($segments[$key])) {
                $category = $key == 'newsletter-default' ? 'newsletter' : 'system';
                $segments[$key] = $this->segment($key, $category);
            }
        }

        // Save system segments into newsletter config
        foreach(['opt-in-pending', 'opt-in-confirmed'] as $key) {
            $this->newsletterSystemConfig['systemSegments'][$key] = $segments[$key]['id'];
        }

        $this->segments = $segments;
    }

    /**
     * Create a segment
     *
     * @param string $alias
     * @param string $category
     * @return array
     */
    private function segment(string $alias, string $category)
    {
        $data = [
            'name' => Utils::translate(['segment', $alias], $this->language),
            'alias' => $alias,
            'description' => Utils::translate(['segment', $alias, 'description'], $this->language),
            'isPublished' => true,
            'isPreferenceCenter' => $category !== 'system',
            'category' => $this->categories[$category],
        ];
        return $this->apiService->create(ApiService::ENDPOINT_SEGMENTS, $data)['list'];
    }

    /**
     * Setup forms in mautic
     */
    public function setForms(): void
    {
        $this->fetchedForms = $this->apiService->getList(ApiService::ENDPOINT_FORMS)['forms'];

        $settings = $this->settingsForm();
        $newsletter = $this->newsletterForm();

        $this->forms = [
            'settings' => $settings,
            'newsletter' => $newsletter,
        ];
    }

    /**
     * Create or edit a settings form
     */
    private function settingsForm(): array
    {
        $cycle = 0;
        $fields = [
            [
                'label' => Utils::translate('form.field.editOrDelete', $this->language),
                'showLabel' => false,
                'alias' => 'type',
                'type' => 'radiogrp',
                'defaultValue' => 'settings',
                'isRequired' => true,
                'order' => $cycle++,
                'properties' => [
                    'optionlist' => [
                        'list' => [
                            [
                                'label' => Utils::translate('form.field.editOrDelete.option.settings', $this->language),
                                'value' => 'settings',
                            ],
                            [
                                'label' => Utils::translate('form.field.editOrDelete.option.delete', $this->language),
                                'value' => 'delete',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'label' => Utils::translate('form.field.email', $this->language),
                'alias' => 'email',
                'type' => 'email',
                'leadField' => 'email',
                'isRequired' => true,
                'isAutoFill' => true,
                'inputAttributes' => 'autocomplete="email"',
                'order' => $cycle++,
                'validationMessage' => Utils::translate(
                    ['form.field.email.validationMessage', $this->salutation],
                    $this->language
                ),
            ],
            [
                'label' => Utils::translate('form.field.submit', $this->language),
                'alias' => 'submit',
                'type' => 'button',
                'order' => $cycle++,
            ],
        ];

        return $this->createOrEditForm('settings', $fields, 'system');
    }

    /**
     * Create or edit a newsletter form
     */
    private function newsletterForm(): array
    {
        $cycle = 0;
        $fields = [
            [
                'label' => Utils::translate('form.field.firstname', $this->language),
                'alias' => 'firstname',
                'leadField' => 'firstname',
                'type' => 'text',
                'isRequired' => false,
                'inputAttributes' => 'autocomplete="given-name"',
                'order' => $cycle++,
            ],
            [
                'label' => Utils::translate('form.field.lastname', $this->language),
                'alias' => 'lastname',
                'type' => 'text',
                'leadField' => 'lastname',
                'isRequired' => false,
                'inputAttributes' => 'autocomplete="family-name"',
                'order' => $cycle++,
            ],
            [
                'label' => Utils::translate('form.field.email', $this->language),
                'alias' => 'email',
                'type' => 'email',
                'leadField' => 'email',
                'isRequired' => true,
                'inputAttributes' => 'autocomplete="email"',
                'order' => $cycle++,
                'validationMessage' => Utils::translate(
                    ['form.field.email.validationMessage', $this->salutation],
                    $this->language
                ),
            ],
            [
                'label' => Utils::translate('form.field.subscribe', $this->language),
                'alias' => 'submit',
                'type' => 'button',
                'order' => $cycle++,
            ],
        ];

        return $this->createOrEditForm('newsletter', $fields, 'newsletter');
    }

    /**
     * Create or edit a form
     *
     * @param string $alias
     * @param array $fields
     * @param string $category
     * @return array
     */
    private function createOrEditForm(string $alias, array $fields, string $category): array
    {
        $availableForm = null;
        // You can't set the alias in the API, we have to depend it on the name
        $name = Utils::translate(['form', $alias], $this->language);
        foreach ($this->fetchedForms as $form) {
            if ($form['name'] == $name) {
                $availableForm = $form;
            }
        }

        if (!isset($availableForm)) {
            $data = [
                'name' => $name,
                'formType' => 'campaign',
                'description' => Utils::translate(['form', $alias, 'description'], $this->language),
                'isPublished' => true,
                'postAction' => 'message',
                'postActionProperty' => Utils::translate(
                    ['form', $alias, 'message', $this->salutation, $this->typeOfContact],
                    $this->language
                ),
                'category' => $this->categories[$category],
                'fields' => $fields,
                'language' => $this->language,
            ];

            return $this->apiService->create(ApiService::ENDPOINT_FORMS, $data)['form'];
        }

        // Form is already here, we merge the fields
        if (isset($availableForm['fields'])) {
            foreach ($fields as $key => $newField) {
                foreach ($availableForm['fields'] as $field) {
                    if ($field['alias'] === $newField['alias']) {
                        // merge the data
                        $fields[$key] = array_merge($field, $newField);
                    }
                }
            }
        }

        return $this->apiService->edit(
            ApiService::ENDPOINT_FORMS,
            $availableForm['id'],
            array_merge($availableForm, [
                'fields' => $fields,
                'category' => $this->categories[$category],
                'language' => $this->language,
            ])
        )['form'];
    }

    /**
     * Setup emails in mautic
     */
    public function setEmails(): void
    {
        $subscribe = $this->emailService->call(
            $this->domain,
            $this->nodes['mailSubscribe'],
            $this->categories['newsletter'],
            $this->sender
        );


        $subscribeRepeat = $this->emailService->call(
            $this->domain,
            $this->nodes['mailSubscribeRepeat'],
            $this->categories['newsletter'],
            $this->sender
        );

        $settings = $this->emailService->call(
            $this->domain,
            $this->nodes['mailSettings'],
            $this->categories['system'],
            $this->sender
        );

        $delete = $this->emailService->call(
            $this->domain,
            $this->nodes['mailDelete'],
            $this->categories['system'],
            $this->sender
        );

        $this->emails = [
            'subscribe' => $subscribe,
            'subscribeRepeat' => $subscribeRepeat,
            'settings' => $settings,
            'delete' => $delete,
        ];
    }

    public function setCampaigns(): void
    {
        $this->fetchedCampaigns = $this->apiService->getList(ApiService::ENDPOINT_CAMPAIGNS)['campaigns'];
        $this->setOptInCampaign();
        $this->setSettingsOrDeleteCampaign();
        $this->setNewsletterCampaign();
    }

    private function setSettingsOrDeleteCampaign(): void
    {
        $key = 'campaign.settingsOrDelete';
        $name = Utils::translate($key, $this->language);
        foreach ($this->fetchedCampaigns as $campaign) {
            if ($campaign['name'] == $name) {
                return;
            }
        }

        $data = [
            'name' => $name,
            'description' => Utils::translate([$key, 'description'], $this->language),
            'category' => $this->categories['system'],
            'isPublished' => true,
            'allowRestart' => false,
            'sourceType' => 'forms',
            'forms' => [$this->forms['settings']],
            'events' => [
                $this->checkFormValue('newHasSettingsChoosen', 'hasSettingsChoosen', 'settings', 'type', 'settings'),

                // If the contact has settings choosen
                $this->createActionEventSendEmail('newSendSettingsEmail', 'sendSettingsEmail', 'settings'),

                // If the contact has not settings choosen (aka delete)
                $this->createActionEventSendEmail('newSendDeletionEmail', 'sendDeletionEmail', 'delete'),
                $this->createDecisionEventClickEmail('newClickEmail', 'deleted'),
                $this->createActionEventDeleteContact('newDeleteContact'),
            ],
            'canvasSettings' => [
                'nodes' => [
                    $this->positionInCanvas('forms', 8, 1),
                    $this->positionInCanvas('newHasSettingsChoosen', 7, 3),
                    $this->positionInCanvas('newSendSettingsEmail', 4, 5),
                    $this->positionInCanvas('newSendDeletionEmail', 12, 5),
                    $this->positionInCanvas('newClickEmail', 11, 7),
                    $this->positionInCanvas('newDeleteContact', 9, 9),
                ],
                'connections' => [
                    [
                        'sourceId' => 'forms',
                        'targetId' => 'newHasSettingsChoosen',
                        'anchors' => [
                            'source' => 'leadsource',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newHasSettingsChoosen',
                        'targetId' => 'newSendSettingsEmail',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newHasSettingsChoosen',
                        'targetId' => 'newSendDeletionEmail',
                        'anchors' => [
                            'source' => 'no',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newSendDeletionEmail',
                        'targetId' => 'newClickEmail',
                        'anchors' => [
                            'source' => 'bottom',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newClickEmail',
                        'targetId' => 'newDeleteContact',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->create(ApiService::ENDPOINT_CAMPAIGNS, $data)['campaign'];
    }

    private function setNewsletterCampaign(): void
    {
        $key = 'campaign.newsletter';
        $name = Utils::translate($key, $this->language);
        foreach ($this->fetchedCampaigns as $campaign) {
            if ($campaign['name'] == $name) {
                return;
            }
        }

        $data = [
            'name' => $name,
            'description' => Utils::translate([$key, 'description'], $this->language),
            'category' => $this->categories['newsletter'],
            'isPublished' => true,
            'allowRestart' => false,
            'sourceType' => 'forms',
            'forms' => [$this->forms['newsletter']],
            'events' => [
                $this->createActionEventChangeList(
                    'newAddToNewsletterList',
                    'addToNewsletterList',
                    add: ['newsletter-default']
                ),
                $this->createConditionEventIsContactConfirmed('newCheckIsContactConfirmed'),

                // If the contact is already confirmed
                $this->createActionEventChangePoints('newAddPoints', 10),

                // If the contact is not confirmed
                $this->createActionEventChangeList(
                    'newAddToUnconfirmedList',
                    'addToUnconfirmedList',
                    add: ['opt-in-pending']
                ),
            ],
            'canvasSettings' => [
                'nodes' => [
                    $this->positionInCanvas('forms', 8, 1),
                    $this->positionInCanvas('newAddToNewsletterList', 2, 4),
                    $this->positionInCanvas('newCheckIsContactConfirmed', 11, 4),
                    $this->positionInCanvas('newAddPoints', 8, 6),
                    $this->positionInCanvas('newAddToUnconfirmedList', 15, 6),
                ],
                'connections' => [
                    [
                        'sourceId' => 'forms',
                        'targetId' => 'newAddToNewsletterList',
                        'anchors' => [
                            'source' => 'leadsource',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'forms',
                        'targetId' => 'newCheckIsContactConfirmed',
                        'anchors' => [
                            'source' => 'leadsource',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newCheckIsContactConfirmed',
                        'targetId' => 'newAddPoints',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newCheckIsContactConfirmed',
                        'targetId' => 'newAddToUnconfirmedList',
                        'anchors' => [
                            'source' => 'no',
                            'target' => 'top',
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->create(ApiService::ENDPOINT_CAMPAIGNS, $data)['campaign'];
    }

    private function setOptInCampaign(): void
    {
        $key = 'campaign.optIn';
        $name = Utils::translate($key, $this->language);
        foreach ($this->fetchedCampaigns as $campaign) {
            if ($campaign['name'] == $name) {
                return;
            }
        }

        $data = [
            'name' => $name,
            'description' => Utils::translate([$key, 'description'], $this->language),
            'category' => $this->categories['system'],
            'isPublished' => true,
            'allowRestart' => false,
            'sourceType' => 'lists',
            'lists' => [$this->segments['opt-in-pending']],
            'events' => [
                $this->createConditionEventIsContactConfirmed('newCheckIsContactConfirmed'),

                // If newCheckIsContactConfirmed is true
                $this->createActionEventChangeList(
                    'newContactIsConfirmed',
                    'removeFromUnconfirmedList',
                    remove: ['opt-in-pending']
                ),

                // If newCheckIsContactConfirmed is false
                $this->createActionEventDoNotContact('newDoNotContact', true),
                $this->createActionEventSendEmail('newSendConfirmationEmail', 'sendConfirmationEmail', 'subscribe'),
                $this->createConditionEventDoNotContact('newFirstCheckDoNotContact', 7, 'days'),
                $this->createConditionEventDoNotContact('newSecondCheckDoNotContact', 3, 'months'),

                // After newSendConfirmationEmail
                $this->createDecisionEventClickEmail('newClickEmail', 'confirmed'),
                $this->createActionEventDoNotContact('newDoContact', false),
                $this->createActionEventChangeList(
                    'newContactConfirmed',
                    'removeFromUnconfirmedList',
                    add: ['opt-in-confirmed'],
                    remove: ['opt-in-pending']
                ),
                $this->createActionEventChangePoints('newAddPoints', 10),

                // After newFirstCheckDoNotContact
                $this->createActionEventSendEmail(
                    'newSendConfirmationEmailRepeat',
                    'sendConfirmationEmailRepeat',
                    'subscribeRepeat'
                ),
                $this->createDecisionEventClickEmail('newClickEmailRepeat', 'confirmed'),
                $this->createActionEventDoNotContact('newDoContactRepeat', false),
                $this->createActionEventChangeList(
                    'newContactConfirmedRepeat',
                    'removeFromUnconfirmedList',
                    add: ['opt-in-confirmed'],
                    remove: ['opt-in-pending']
                ),
                $this->createActionEventChangePoints('newAddPointsRepeat', 5),

                // After newSecondCheckDoNotContact
                $this->createActionEventDeleteContact('newDeleteContact'),
            ],
            'canvasSettings' => [
                'nodes' => [
                    $this->positionInCanvas('lists', 10, 1),
                    $this->positionInCanvas('newCheckIsContactConfirmed', 9, 3),
                    $this->positionInCanvas('newContactIsConfirmed', 1, 5),

                    // Do not contact
                    $this->positionInCanvas('newDoNotContact', 14, 5),

                    // Send confirmation email
                    $this->positionInCanvas('newSendConfirmationEmail', 8, 5),
                    $this->positionInCanvas('newClickEmail', 7, 7),
                    $this->positionInCanvas('newDoContact', 1, 9),
                    $this->positionInCanvas('newContactConfirmed', 1, 11),
                    $this->positionInCanvas('newAddPoints', 1, 13),

                    // Check after 7 days
                    $this->positionInCanvas('newFirstCheckDoNotContact', 11, 10),
                    $this->positionInCanvas('newSendConfirmationEmailRepeat', 9, 12),
                    $this->positionInCanvas('newClickEmailRepeat', 9, 14),
                    $this->positionInCanvas('newDoContactRepeat', 6, 16),
                    $this->positionInCanvas('newContactConfirmedRepeat', 6, 18),
                    $this->positionInCanvas('newAddPointsRepeat', 6, 20),

                    // Check after 3 months
                    $this->positionInCanvas('newSecondCheckDoNotContact', 22, 10),
                    $this->positionInCanvas('newDeleteContact', 21, 12),
                ],
                'connections' => [
                    [
                        'sourceId' => 'lists',
                        'targetId' => 'newCheckIsContactConfirmed',
                        'anchors' => [
                            'source' => 'leadsource',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newCheckIsContactConfirmed',
                        'targetId' => 'newContactIsConfirmed',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newCheckIsContactConfirmed',
                        'targetId' => 'newDoNotContact',
                        'anchors' => [
                            'source' => 'no',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newCheckIsContactConfirmed',
                        'targetId' => 'newSendConfirmationEmail',
                        'anchors' => [
                            'source' => 'no',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newCheckIsContactConfirmed',
                        'targetId' => 'newFirstCheckDoNotContact',
                        'anchors' => [
                            'source' => 'no',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newCheckIsContactConfirmed',
                        'targetId' => 'newSecondCheckDoNotContact',
                        'anchors' => [
                            'source' => 'no',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newSendConfirmationEmail',
                        'targetId' => 'newClickEmail',
                        'anchors' => [
                            'source' => 'bottom',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newClickEmail',
                        'targetId' => 'newDoContact',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newClickEmail',
                        'targetId' => 'newContactConfirmed',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newClickEmail',
                        'targetId' => 'newAddPoints',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newFirstCheckDoNotContact',
                        'targetId' => 'newSendConfirmationEmailRepeat',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newSendConfirmationEmailRepeat',
                        'targetId' => 'newClickEmailRepeat',
                        'anchors' => [
                            'source' => 'bottom',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newClickEmailRepeat',
                        'targetId' => 'newDoContactRepeat',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newClickEmailRepeat',
                        'targetId' => 'newContactConfirmedRepeat',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newClickEmailRepeat',
                        'targetId' => 'newAddPointsRepeat',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newSecondCheckDoNotContact',
                        'targetId' => 'newDeleteContact',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->create(ApiService::ENDPOINT_CAMPAIGNS, $data)['campaign'];
    }

    private function positionInCanvas(string $id, int $x, int $y): array
    {
        $gridSize = 65;
        return [
            'id' => $id,
            'positionX' => $x * $gridSize,
            'positionY' => $y * $gridSize,
        ];
    }

    private function checkFormValue(
        string $id,
        string $i18nKey,
        string $formKey,
        string $field,
        mixed $value,
        string $operator = '='
    ): array {
        $formId = $this->forms[$formKey]['id'];
        return $this->createEvent([
            'id' => $id,
            'name' => Utils::translate(['campaign.condition', $i18nKey], $this->language),
            'eventType' => 'condition',
            'type' => 'form.field_value',
            'properties' => [
                'form' => $formId,
                'field' => $field,
                'value' => $value,
                'operator' => $operator,
            ],
        ]);
    }

    /**
     * Check if contact is confirmed
     *
     * @param string $id
     * @return array
     */
    private function createConditionEventIsContactConfirmed(string $id): array
    {
        return $this->createEvent([
            'id' => $id,
            'name' => Utils::translate('campaign.condition.isContactConfirmed', $this->language),
            'eventType' => 'condition',
            'type' => 'lead.segments',
            'properties' => [
                'segments' => [$this->segments['opt-in-confirmed']['id']],
            ],
        ]);
    }

    /**
     * Change list of contact
     *
     * @param string $id
     * @param string $i18nKey
     * @param array $add
     * @param array $remove
     * @return array
     */
    private function createActionEventChangeList(
        string $id,
        string $i18nKey,
        array $add = [],
        array $remove = []
    ): array {
        $properties = [
            'addToLists' => [],
            'removeFromLists' => [],
        ];

        foreach ($this->segments as $key => $segment) {
            if (in_array($key, $add)) {
                $properties['addToLists'][] = $segment['id'];
            }
            if (in_array($key, $remove)) {
                $properties['removeFromLists'][] = $segment['id'];
            }
        }

        return $this->createEvent([
            'id' => $id,
            'name' => Utils::translate(['campaign.action', $i18nKey], $this->language),
            'eventType' => 'action',
            'type' => 'lead.changelist',
            'properties' => $properties,
        ]);
    }

    private function createActionEventSendEmail(string $id, string $i18nKey, string $emailKey): array
    {
        $emailId = $this->emails[$emailKey]['id'];
        return $this->createEvent([
            'id' => $id,
            'name' => Utils::translate(['campaign.action', $i18nKey], $this->language),
            'eventType' => 'action',
            'type' => 'email.send',
            'channel' => 'email',
            'channelId' => $emailId,
            'properties' => [
                'email' => $emailId,
                'email_type' => 'transactional',
            ],
        ]);
    }

    private function createActionEventDoNotContact(string $id, bool $doNotContact = true): array
    {
        $type = $doNotContact ? 'adddnc' : 'removednc';
        return $this->createEvent([
            'id' => $id,
            'name' => Utils::translate(['campaign.action', $type], $this->language),
            'eventType' => 'action',
            'type' => sprintf('lead.%s', $type),
            'properties' => [
                'channels' => ['email'],
            ],
        ]);
    }

    /**
     * @param string $id
     * @param string[]|string $targetNodesKeys
     * @return array
     */
    private function createDecisionEventClickEmail(string $id, array|string $targetNodesKeys = []): array
    {
        $list = [];
        if (is_string($targetNodesKeys)) {
            $targetNodesKeys = [$targetNodesKeys];
        }
        foreach ($targetNodesKeys as $key) {
            $node = $this->nodes[$key] ?? null;
            if (isset($node)) {
                $list[] = $this->nodeService->getNodeUri($node, $this->domain);
            }
        }

        return $this->createEvent([
            'id' => $id,
            'name' => Utils::translate('campaign.decision.clickEmail', $this->language),
            'eventType' => 'decision',
            'type' => 'email.click',
            'properties' => [
                'urls' => [
                    'list' => $list,
                ],
            ],
        ]);
    }

    private function createConditionEventDoNotContact(
        string $id,
        int $triggerInterval = 0,
        string $triggerIntervalUnit = 'day'
    ): array {
        return $this->createEvent(
            [
                'id' => $id,
                'name' => Utils::translate('campaign.condition.doNotContact', $this->language),
                'eventType' => 'condition',
                'type' => 'lead.dnc',
                //'decisionPath' => 'no',
                'properties' => [
                    'channels' => ['email'],
                ],
            ],
            $triggerInterval,
            $triggerIntervalUnit
        );
    }

    private function createActionEventChangePoints(string $id, int $points): array
    {
        $translationKey = $points > 0 ? 'addpoints' : 'removepoints';
        return $this->createEvent([
            'id' => $id,
            'name' => Utils::translate(['campaign.action', $translationKey], $this->language),
            'eventType' => 'action',
            'type' => 'lead.changepoints',
            //'decisionPath' => 'yes',
            'properties' => [
                'points' => $points,
            ],
        ]);
    }

    private function createActionEventDeleteContact(string $id): array
    {
        return $this->createEvent([
            'id' => $id,
            'name' => Utils::translate('campaign.action.deleteContact', $this->language),
            'eventType' => 'action',
            'type' => 'lead.deletecontact',
        ]);
    }

    /**
     * Setup events in mautic
     */
    private function createEvent(array $eventData, int $triggerInterval = 0, string $triggerIntervalUnit = 'day'): array
    {
        if ($triggerInterval) {
            $triggerMode = 'interval';
            switch ($triggerIntervalUnit) {
                case 'min':
                case 'minute':
                case 'minutes':
                    $triggerIntervalUnit = 'i';
                    break;
                case 'hour':
                case 'hours':
                    $triggerIntervalUnit = 'h';
                    break;
                case 'day':
                case 'days':
                    $triggerIntervalUnit = 'd';
                    break;
                case 'month':
                case 'months':
                    $triggerIntervalUnit = 'm';
                    break;
                case 'year':
                case 'years':
                    $triggerIntervalUnit = 'y';
                    break;
            }
        } else {
            $triggerMode = 'immediate';
            $triggerInterval = 1;
            $triggerIntervalUnit = 'd';
        }

        return array_merge(
            [
                'triggerMode' => $triggerMode,
                'triggerInterval' => $triggerInterval,
                'triggerIntervalUnit' => $triggerIntervalUnit,
            ],
            $eventData
        );
    }

    public function setFormId(): void
    {
        if ($this->nodes['container']) {
            $flowQueryContainer = new FlowQuery([$this->nodes['container']]);
            $containerFormNode = $flowQueryContainer
                ->children('main')
                ->find('[instanceof Garagist.Mautic:Mixin.Form]')
                ->get(0);
            if ($containerFormNode) {
                $containerFormNode->setProperty('mauticFormId', $this->forms['newsletter']['id']);
            }
        }

        if ($this->nodes['settings']) {
            $flowQuerySettings = new FlowQuery([$this->nodes['settings']]);
            $settingsFormNode = $flowQuerySettings
                ->children('main')
                ->find('[instanceof Garagist.Mautic:Mixin.Form]')
                ->get(0);
            if ($settingsFormNode) {
                $settingsFormNode->setProperty('mauticFormId', $this->forms['settings']['id']);
            }
        }
    }

    public function saveConfig(): void
    {
        $this->nodes['container']->setProperty('newsletterSystemConfig', $this->newsletterSystemConfig);
    }
}
