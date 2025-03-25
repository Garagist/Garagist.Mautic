<?php

namespace Garagist\Mautic\DataSource;

use Garagist\Mautic\Service\ApiService;
use Garagist\Mautic\Service\EmailService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;

final class EmailReportDataSource extends AbstractDataSource
{
    /**
    * @var string
    */
    static protected $identifier = 'garagist-mautic-email-report';

    #[Flow\Inject]
    protected EmailService $emailService;

    #[Flow\Inject]
    protected ApiService $apiService;

    /**
    * Get data
    *
    * {@inheritdoc}
    */
    public function getData(NodeInterface $node = NULL, array $arguments = [])
    {
        $ping = $this->apiService->ping();
        if (!$ping) {
            return null;
        }

        $email = $this->emailService->getEmail($node);
        if (!isset($email['id'])) {
            return null;
        }

        $useVariant = $arguments['variant'] ? json_decode($arguments['variant']) : false;
        $sentCount = $email[$useVariant ? 'variantSentCount' : 'sentCount'] ?: 0;
        $readCount = $email[$useVariant ? 'variantReadCount' : 'readCount'] ?: 0;

        return [
            'sentCount' => $sentCount,
            'readCount' => $readCount,
        ];
    }
}
