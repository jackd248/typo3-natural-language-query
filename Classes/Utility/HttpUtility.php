<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Utility;

use Psr\Http\Message;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class HttpUtility
{
    public static function getServerRequest(): Message\ServerRequestInterface
    {
        $serverRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;

        if ($serverRequest instanceof Message\ServerRequestInterface) {
            return $serverRequest;
        }

        return self::fakeTypo3Request();
    }

    public static function fakeTypo3Request(?Site $site = null, ?SiteLanguage $siteLanguage = null): ServerRequestInterface
    {
        if (!$site instanceof Site || !$siteLanguage instanceof SiteLanguage) {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $array = $siteFinder->getAllSites();
            $site = reset($array);
            $siteLanguage = $site->getLanguageById(0);
        }

        $request = new ServerRequest(new Uri((string)$siteLanguage->getBase()));
        $request = $request->withAttribute('site', $site);
        $request = $request->withAttribute('language', $siteLanguage);
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $request = $request->withQueryParams(['id' => $site->getRootPageId()]);

        $GLOBALS['TYPO3_REQUEST'] = $request;
        return $request;
    }

    public static function buildAbsoluteUrlFromRoute(string $name, array $parameters): string
    {
        $request = self::getServerRequest();
        Bootstrap::initializeBackendUser(request: $request);
        $baseUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        $path = GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute($name, $parameters)->__toString();
        return $baseUrl . $path;
    }
}
