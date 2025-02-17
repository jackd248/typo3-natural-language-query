<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Service;

use Kmi\Typo3NaturalLanguageQuery\Configuration;
use Kmi\Typo3NaturalLanguageQuery\Utility\GeneralHelper;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

final class PromptGenerator
{
    public function __construct(private readonly ViewFactoryInterface $viewFactory)
    {
    }

    public function renderPrompt(array $parameters, string $templateName = 'Query'): string
    {
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: ['EXT:' . Configuration::EXT_KEY . '/Resources/Private/Prompts'],
            request: GeneralHelper::createTypo3Request(),
        );
        $view = $this->viewFactory->create($viewFactoryData);
        $view->assignMultiple($parameters);
        return $view->render($templateName);
    }
}
