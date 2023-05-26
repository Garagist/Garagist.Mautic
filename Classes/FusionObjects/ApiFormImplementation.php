<?php

namespace Garagist\Mautic\FusionObjects;

use Garagist\Mautic\Service\ApiService;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\FusionObjects\AbstractFusionObject;

class ApiFormImplementation extends AbstractFusionObject
{

    /**
     * @Flow\Inject
     * @var ApiService
     */
    protected $apiService;

    /**
     * @return string
     */
    public function evaluate()
    {
        $id = (int)$this->fusionValue('id');
        $url = $this->fusionValue('url');

        if (!isset($id) || !$url) {
            return [];
        }

        $data = $this->apiService->getForm($id);
        $parentsMap = [];
        foreach ($data['fields'] as $field) {
            $parentsMap[$field['id']] = $field['alias'];
        }
        $page = 1;
        $fields = [
            1 => []
        ];
        $hiddenFields = [
            ['formId', $data['id']],
            ['formName', $data['alias']],
            ['messenger', 1]
        ];
        $defaultValues = [];
        foreach ($data['fields'] as $field) {
            $type = $field['type'];
            $name = $field['alias'];
            $value =  $field['defaultValue'];

            if ($type === 'hidden') {
                $hiddenFields[] = [$name, $value];
                continue;
            }

            $tagName = null;
            if (in_array($type, ['email', 'password', 'text', 'file', 'date', 'datetime', 'number', 'captcha', 'url', 'tel'])) {
                $tagName = 'input';
            } else if (in_array($type, ['select', 'country'])) {
                $tagName = 'select';
            } else if (in_array($type, ['radiogrp', 'checkboxgrp'])) {
                $tagName = 'inputGroup';
                $value = $value ? array_map('trim', explode(',', $value)) : [];
            } else if ($type == 'textarea') {
                $tagName = $type;
            }

            if ($tagName) {
                $defaultValues[] = [$name, $value];
            }

            $fields[$page][] = array_filter([
                'name' => $name,
                'label' => $field['label'],
                'showLabel' =>  $field['showLabel'],
                'type' =>  $type == 'datetime' ? 'datetime-local' : $type,
                'tagName' =>  $tagName,
                'value' =>  $field['defaultValue'],
                'required' => $field['isRequired'],
                'validation' => $field['validationMessage'],
                'help' => $field['helpMessage'],
                'placeholder' => $field['properties']['placeholder'] ?? null,
                'options' => $field['properties']['list']['list'] ?? $field['properties']['optionlist']['list'] ?? [],
                'multiple' => !!($field['properties']['multiple'] ?? null),
                'text' => $field['properties']['text'] ?? null,
                'nextPageLabel' => $field['properties']['next_page_label'] ?? null,
                'prevPageLabel' => $field['properties']['prev_page_label'] ?? null,
                'captcha' => $field['properties']['captcha'] ?? null,
                'errorMessage' => $field['properties']['errorMessage'] ?? null,
                'dependOn' => $field['parent'] ? [
                    'name' => $parentsMap[$field['parent']],
                    'conditions' => $field['conditions']
                ] : null
            ]);
            if ($type === 'pagebreak') {
                $page++;
                $fields[$page] = [];
            }
        }

        return [
            'form' => [
                'id' => $data['id'],
                'name' => $data['alias'],
                'action' => $url . '/form/submit',
                'origin' => $url,
                'showMessage' => $data['postAction'] === 'message',
            ],
            'fields' => $fields,
            'hiddenFields' => $hiddenFields,
            'defaults' => $defaultValues,
        ];
    }
}
