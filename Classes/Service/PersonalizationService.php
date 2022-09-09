<?php

declare(strict_types=1);

namespace Garagist\Mautic\Service;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class PersonalizationService
{

    /**
     * @var string
     */
    protected string $regexPattern = '/{#ifNewsletter}([\S\s]*){\/if}/U';


    /**
     * Replace the personalization tags with the webview fallback
     *
     * @param string $content
     * @param boolean $enable
     * @return string
     */
    public function webview(string $content, bool $enable = true): string
    {
        if ($this->dontChangeContent($content, $enable)) {
            return $content;
        }
        return preg_replace_callback(
            $this->regexPattern,
            function ($matches) {
                $split = explode('{:else}', $matches[1]);
                return $split[1] ?? '';
            },
            $content
        );
    }

    /**
     * Replace the personalization tags with the mautic tags
     *
     * @param string $content
     * @param boolean $inBackend
     * @return string
     */
    public function mautic(string $content, bool $enable = true): string
    {
        if ($this->dontChangeContent($content, $enable)) {
            return $content;
        }
        return preg_replace_callback(
            $this->regexPattern,
            function ($matches) {
                $split = explode('{:else}', $matches[1]);
                return preg_replace_callback('/#(\S*)#/U', function ($matches) {
                    return strtolower('{contactfield=' . $matches[1] . '}');
                }, $split[0]);
            },
            $content
        );
    }

    /**
     * Check if searchPattern is present and enabled
     *
     * @param string $content
     * @param boolean $enable
     * @return boolean
     */
    protected function dontChangeContent(string $content, bool $enable): bool
    {
        return !$enable || strpos($content, '{#ifNewsletter}') === false;
    }
}
