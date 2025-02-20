<?php

namespace Garagist\Mautic\Service;

use Garagist\Mautic\Service\ApiService;
use Neos\Flow\I18n\EelHelper\TranslationParameterToken;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
class NewsletterService
{
    #[Flow\Inject]
    protected ApiService $apiService;

    /**
     * Setup managed category in mautic
     *
     * @param string $language
     * @return integer The id of the managed category
     */
    public function setupManagedCategory(string $language): int
    {
        $alias = 'newsletter';
        $categories = $this->apiService->getList(ApiService::ENDPOINT_CATEGORIES);

        $availableCategory = false;
        foreach ($categories['categories'] as $category) {
            if ($category['alias'] == $alias) {
                $availableCategory = $category;
            }
        }

        if ($availableCategory) {
            return $availableCategory['id'];
        }

        $data = [
            'title' => $this->getTranslation('category', $language),
            'alias' => $alias,
            'description' => $this->getTranslation('category.description', $language),
            'color' => '#ff0000',
            'bundle' => 'global',
        ];

        $category = $this->apiService->create(ApiService::ENDPOINT_CATEGORIES, $data);

        return $category['category']['id'];
    }

    /**
     * Setup segments in mautic
     *
     * @param string $language
     * @param integer $managedCategory
     * @return array
     */
    public function setupSegments(string $language, int $managedCategory): array
    {
        $segments = $this->apiService->getList(ApiService::ENDPOINT_SEGMENTS);
        $segmentsAvailable = [];
        foreach ($segments['lists'] as $segment) {
            if (in_array($segment['alias'], ['opt-in-pending', 'opt-in-confirmed', 'newsletter-default'])) {
                $segmentsAvailable[$segment['alias']] = $segment;
            }
        }

        if (!isset($segmentsAvailable['opt-in-pending'])) {
            $data = [
                'name' => $this->getTranslation('segment.optInPending', $language),
                'alias' => 'opt-in-pending',
                'description' => $this->getTranslation('segment.optInPending.description', $language),
                'isPublished' => true,
                'category' => $managedCategory,
            ];
            $segmentsAvailable['opt-in-pending'] = $this->apiService->create(ApiService::ENDPOINT_SEGMENTS, $data)[
                'list'
            ];
        }

        if (!isset($segmentsAvailable['opt-in-confirmed'])) {
            $data = [
                'name' => $this->getTranslation('segment.optInConfirmed', $language),
                'alias' => 'opt-in-confirmed',
                'description' => $this->getTranslation('segment.optInConfirmed.description', $language),
                'isPublished' => true,
                'category' => $managedCategory,
            ];
            $segmentsAvailable['opt-in-confirmed'] = $this->apiService->create(ApiService::ENDPOINT_SEGMENTS, $data)[
                'list'
            ];
        }

        if (!isset($segmentsAvailable['newsletter-default'])) {
            $data = [
                'name' => $this->getTranslation('segment.newsletterDefault', $language),
                'alias' => 'newsletter-default',
                'description' => $this->getTranslation('segment.newsletterDefault.description', $language),
                'isPublished' => true,
                'isPreferenceCenter' => true,
                'category' => $managedCategory,
            ];
            $segmentsAvailable['newsletter-default'] = $this->apiService->create(ApiService::ENDPOINT_SEGMENTS, $data)[
                'list'
            ];
        }
        return $segmentsAvailable;
    }

    /**
     * Setup form in mautic
     *
     * @param string $language
     * @param string $salutation informal or group
     * @param string $typeOfContact single or group
     * @param integer $managedCategory
     * @return array
     */
    public function setupForm(string $language, string $salutation, string $typeOfContact, int $managedCategory): array
    {
        $forms = $this->apiService->getList(ApiService::ENDPOINT_FORMS);
        $alias = 'newsletter';

        $availableForm = false;
        foreach ($forms['forms'] as $form) {
            if ($form['alias'] == $alias) {
                $availableForm = $form;
            }
        }

        // blueprint for the form fields
        $dataNewsletterSignupFormFields = [
            [
                'label' => $this->getTranslation('email', $language),
                'alias' => 'email',
                'type' => 'email',
                'leadField' => 'email',
                'isRequired' => true,
                'order' => 1,
                'validationMessage' => $this->getTranslation(['email.validationMessage', $salutation], $language),
            ],
            [
                'label' => $this->getTranslation('firstname', $language),
                'alias' => 'firstname',
                'leadField' => 'firstname',
                'type' => 'text',
                'isRequired' => false,
                'order' => 2,
            ],
            [
                'label' => $this->getTranslation('lastname', $language),
                'alias' => 'lastname',
                'type' => 'text',
                'leadField' => 'lastname',
                'isRequired' => false,
                'order' => 3,
            ],
            [
                'label' => $this->getTranslation('subscribe', $language),
                'alias' => 'submit',
                'type' => 'button',
                'order' => 4,
            ],
        ];

        if (!$availableForm) {
            $data = [
                'name' => $this->getTranslation('signup.name', $language),
                'alias' => $alias,
                'formType' => 'campaign',
                'description' => $this->getTranslation('signup.form', $language),
                'isPublished' => true,
                'postAction' => 'message',
                'postActionProperty' => $this->getTranslation(
                    ['signup.message', $salutation, $typeOfContact],
                    $language
                ),
                'category' => $managedCategory,
                'fields' => $dataNewsletterSignupFormFields,
                'language' => $language,
                // 'actions' => [
                //     [
                //         'name' => $this->getTranslation('subscribe', $language),
                //         'type' => 'submit',
                //         'order' => 1,
                //     ],
                // ],
            ];

            return $this->apiService->create(ApiService::ENDPOINT_FORMS, $data)['form'];
        }

        // Form is already here, we merge the fields
        if (isset($availableForm['fields'])) {
            foreach ($dataNewsletterSignupFormFields as $key => $dataNewsletterSignupFormField) {
                foreach ($availableForm['fields'] as $field) {
                    if ($field['alias'] === $dataNewsletterSignupFormField['alias']) {
                        // merge the data
                        $dataNewsletterSignupFormFields[$key] = array_merge($field, $dataNewsletterSignupFormField);
                    }
                }
            }
        }

        return $this->apiService->edit(
            ApiService::ENDPOINT_FORMS,
            $availableForm['id'],
            array_merge($availableForm, [
                'fields' => $dataNewsletterSignupFormFields,
                'category' => $managedCategory,
                'language' => $language,
            ])
        )['form'];
    }

    /**
     * Setup campain in Mautic
     *
     * @param string $language
     * @param string $salutation informal or group
     * @param string $typeOfContact single or group
     * @param integer $managedCategory
     * @param integer $formId
     * @return array
     */
    public function setupCampaign(
        string $language,
        string $salutation,
        string $typeOfContact,
        int $managedCategory,
        array $form,
        array $segmentIds
    ): array {
        $campaigns = $this->apiService->getList(ApiService::ENDPOINT_CAMPAIGNS);
        $name = $this->getTranslation('signup.name', $language);

        foreach ($campaigns['campaigns'] as $campaign) {
            if ($campaign['name'] == $name) {
                $this->apiService->delete(ApiService::ENDPOINT_CAMPAIGNS, $campaign['id']);
                return [];
                // return $campaign;
            }
        }

        // Setup campain
        $data = [
            'name' => $name,
            'description' => $this->getTranslation('signup.campain', $language),
            'category' => $managedCategory,
            'isPublished' => true,
            'allowRestart' => false,
            'sourceType' => 'forms',
            'forms' => [$form],
            'canvasSettings' => [
                'nodes' => [
                    [
                        'id' => 'forms',
                        'positionX' => '200',
                        'positionY' => '100',
                    ],
                    [
                        'id' => 'newContactIsNotConfirmed',
                        'positionX' => '200',
                        'positionY' => '200',
                    ],
                    [
                        'id' => 'newSendConfirmationEmail',
                        'positionX' => '50',
                        'positionY' => '300',
                    ],
                    [
                        'id' => 'newAddContactToPendingList',
                        'positionX' => '500',
                        'positionY' => '300',
                    ],
                    [
                        'id' => 'newPageHit',
                        'positionX' => '500',
                        'positionY' => '400',
                    ],
                    [
                        'id' => 'newAdjustSegments',
                        'positionX' => '300',
                        'positionY' => '500',
                    ],
                ],
                'connections' => [
                    [
                        'sourceId' => 'forms',
                        'targetId' => 'newContactIsNotConfirmed',
                        'anchors' => [
                            'source' => 'leadsource',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newContactIsNotConfirmed',
                        'targetId' => 'newSendConfirmationEmail',
                        'anchors' => [
                            'source' => 'no',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newContactIsNotConfirmed',
                        'targetId' => 'newAddContactToPendingList',
                        'anchors' => [
                            'source' => 'no',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newAddContactToPendingList',
                        'targetId' => 'newPageHit',
                        'anchors' => [
                            'source' => 'bottom',
                            'target' => 'top',
                        ],
                    ],
                    [
                        'sourceId' => 'newPageHit',
                        'targetId' => 'newAdjustSegments',
                        'anchors' => [
                            'source' => 'yes',
                            'target' => 'top',
                        ],
                    ],
                ],
            ],
            'events' => [
                $this->createEvent(
                    [
                        'id' => 'newContactIsNotConfirmed',
                        'name' => 'campain.condition.contactIsNotConfirmed',
                        'eventType' => 'condition',
                        'type' => 'lead.segments',
                        'anchor' => 'leadsource',
                        'anchorEventType' => 'source',
                        'properties' => [
                            'segments' => [$segmentIds['opt-in-confirmed']],
                        ],
                    ],
                    true
                ),
                $this->createEvent(
                    [
                        'id' => 'newSendConfirmationEmail',
                        'name' => 'campain.action.sendConfirmationEmail',
                        'eventType' => 'action',
                        'type' => 'lead.changelist',
                        'anchor' => 'no',
                        'decisionPath' => 'yes',
                        'parent' => [
                            'id' => 'newContactIsNotConfirmed',
                        ],
                    ],
                    true
                ),
                $this->createEvent(
                    [
                        'id' => 'newAddContactToPendingList',
                        'name' => 'campain.action.addContactToPendingList',
                        'eventType' => 'action',
                        'type' => 'lead.changelist',
                        'anchor' => 'no',
                        'decisionPath' => 'yes',
                        'parent' => [
                            'id' => 'newContactIsNotConfirmed',
                        ],
                    ],
                    true
                ),
                $this->createEvent(
                    [
                        'id' => 'newPageHit',
                        'name' => 'campain.condition.pageHit',
                        'eventType' => 'condition',
                        'type' => 'lead.pageHit',
                        'anchor' => 'bottom',
                        'anchorEventType' => 'action',
                        'properties' => [
                            // TODO make this dynamic
                            'page_url' => 'https://newsletter.ddev.site/confirm ',
                            'accumulative_time' => 0,
                            'returns_within_unit' => 's',
                            'accumulative_time_unit' => 's',
                        ],
                        'decisionPath' => 'yes',
                        'parent' => [
                            'id' => 'newAddContactToPendingList',
                        ],
                    ],
                    true
                ),
                $this->createEvent(
                    [
                        'id' => 'newAdjustSegments',
                        'name' => 'campain.action.adjustSegments',
                        'eventType' => 'action',
                        'type' => 'lead.changelist',
                        'anchor' => 'yes',
                        'anchorEventType' => 'condition',
                        'properties' => [
                            'addToLists' => [$segmentIds['opt-in-confirmed'], $segmentIds['newsletter-default']],
                            'removeFromLists' => [$segmentIds['opt-in-pending']],
                        ],
                        'decisionPath' => 'yes',
                        'parent' => [
                            'id' => 'newPageHit',
                        ],
                    ],
                    true
                ),
            ],
        ];

        return $this->apiService->create(ApiService::ENDPOINT_CAMPAIGNS, $data)['campaign'];
    }

    private function createEvent(array $eventData, bool $immediate = false): array
    {
        if ($immediate) {
            $eventData = array_merge(
                [
                    'triggerMode' => 'immediate',
                    'triggerInterval' => 1,
                    'triggerIntervalUnit' => 'd',
                    'triggerWindow' => 0,
                ],
                $eventData
            );
        }

        if (isset($eventData['name']) && function_exists('ray')) {
            ray()->toJson($eventData)->label($eventData['name']);
        }

        return $eventData;
    }

    /**
     * Setup pages in Mautic
     *
     * @param string $language
     * @param string $translationPostfix informal.single, informal.group, formal.single, formal.group
     * @param integer $managedCategory
     * @param string $domain The domain of the website, incl. protocol e.g. https://www.domain.tld
     * @return array
     */
    public function setupPages(
        string $language,
        string $translationPostfix,
        int $managedCategory,
        string $domain
    ): array {
        $pages = $this->apiService->getList(ApiService::ENDPOINT_PAGES);
        $alias = 'opt-in-confirm';
        $salutation = explode('.', $translationPostfix)[0];

        $availablePage = false;
        foreach ($pages['pages'] as $page) {
            if ($page['alias'] == $alias) {
                $availablePage = $page;
            }
        }

        $data = [
            'name' => $this->getTranslation('page.name', $language),
            'description' => $this->getTranslation('page.description', $language),
            'title' => $this->getTranslation(['page.title', $salutation], $language),
            'alias' => $alias,
            'isPublished' => 1,
            'template' => 'mautic_code_mode',
            'category' => $managedCategory,
            'language' => $language,
        ];

        if (!$availablePage) {
            $markup =
                '<!DOCTYPE html><html lang="%s"><head><title>{pagetitle}</title><meta name="description" content="{pagemetadescription}" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><meta http-equiv="refresh" content="0; url=%s"></head><body></body></html>';
            $data['customHtml'] = sprintf($markup, $language, $domain);
            return $this->apiService->create(ApiService::ENDPOINT_PAGES, $data)['page'];
        }

        return $this->apiService->edit(
            ApiService::ENDPOINT_PAGES,
            $availablePage['id'],
            array_merge($availablePage, $data)
        )['page'];
    }

    /**
     * Setup emails
     *
     * @param string $language
     * @param string $translationPostfix informal.single, informal.group, formal.single, formal.group
     * @param integer $managedCategory
     * @param integer $confirmPageId
     * @param string|null $from The name of the sender e.g. John Doe
     * @return array
     */
    public function setupEmails(
        string $language,
        string $translationPostfix,
        int $managedCategory,
        int $confirmPageId,
        ?string $from = null
    ): array {
        $emails = $this->apiService->getList(ApiService::ENDPOINT_EMAILS);
        $link = sprintf('{pagelink=%s}', $confirmPageId);
        $alias = 'opt-in-welcome';

        $availableEmail = false;
        foreach ($emails['emails'] as $email) {
            if ($email['alias'] == $alias) {
                $availableEmail = $email;
            }
        }

        if (!$availableEmail) {
            $data = [
                'name' => $this->getTranslation('email.name', $language),
                'description' => $this->getTranslation('email.description', $language),
                'subject' => $this->getTranslation(['email.subject', $translationPostfix], $language),
                'preheaderText' => $this->getTranslation(['email.preheaderText', $translationPostfix], $language),
                'plainText' => $this->getTranslation(['email.plainText', $translationPostfix], $language, $link),
                'isPublished' => 1,
                'fromName' => $from,
                'category' => $managedCategory,
                'language' => $language,
            ];
        }

        if (!isset($emailsAvailable['Opt-in welcome'])) {
            $data = [
                'name' => 'Opt-in welcome',
                'subject' => 'Welcome!',
                'description' => 'Email for opt-in confirmation.',
                'isPublished' => 1,
                'fromName' => $from,
                'category' => $managedCategory,
            ];

            $emailsAvailable['Opt-in welcome'] = $this->apiService->create('emails', $data)['email'];
        }

        $emailsAvailable['Opt-in welcome']['customHtml'] =
            '
            <!DOCTYPE html>
                <html xmlns="http://www.w3.org/1999/xhtml">
                  <head>
                    <title>{pagetitle}</title>
                    <meta name="description" content="{pagemetadescription}" />
                    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
                    <link rel="stylesheet" href="' .
            $domain .
            '/_Resources/Static/Packages/Litefyr.Presentation/Styles/Main.css" />
                  </head>
                  <body>
                    <div class="container">
                        <div class="row">
                            <div class="col-md-12">
                                <h1>Thank you for signing up!</h1>
                                <p>We will send you an email to confirm your subscription.</p>
                                {pagelink=3}
                            </div>
                        </div>
                    </div>
                  </body>
                </html>';

        $emailsAvailable['Opt-in welcome']['customText'] =
            'Thank you for signing up! We will send you an email to confirm your subscription. {pagelink=3}';

        $this->apiService->edit('emails', $emailsAvailable['Opt-in welcome']['id'], $emailsAvailable['Opt-in welcome']);

        return $emailsAvailable;
    }

    /**
     * Returns the translation for the given key
     *
     * @param array|string $key The key to translate
     * @param string $language The language of the translation
     * @param array $arguments The arguments to replace in the translation
     * @return integer The id of the managed category
     */
    private function getTranslation(array|string $key, string $language, string|array $arguments = []): string
    {
        if (is_array($key)) {
            $key = implode('.', $key);
        }
        if (is_string($arguments)) {
            $arguments = [$arguments];
        }
        $token = new TranslationParameterToken($key, null);
        return $token
            ->source('Setup')
            ->package('Carbon.Newsletter')
            ->arguments($arguments)
            ->locale($language)
            ->translate();
    }
}
