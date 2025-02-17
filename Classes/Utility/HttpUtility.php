<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Utility;

use Psr\Http\Message;
use TYPO3\CMS\Core;

final class HttpUtility
{
    public static function getServerRequest(): Message\ServerRequestInterface
    {
        $serverRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;

        if ($serverRequest instanceof Message\ServerRequestInterface) {
            return $serverRequest;
        }

        return Core\Http\ServerRequestFactory::fromGlobals();
    }
}
