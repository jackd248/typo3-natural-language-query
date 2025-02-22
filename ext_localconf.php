<?php

declare(strict_types=1);

use KonradMichalik\Typo3NaturalLanguageQuery\Configuration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\Writer\FileWriter;

defined('TYPO3') || die();

$GLOBALS['TYPO3_CONF_VARS']['LOG']['KonradMichalik'][Configuration::EXT_NAME]['Connector']['writerConfiguration'] = [
    LogLevel::DEBUG => [
        FileWriter::class => [
            'logFile' => Environment::getVarPath() . '/log/' . Configuration::EXT_KEY . '.log',
        ],
    ],
];
