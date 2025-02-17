<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Connector;

use Kmi\Typo3NaturalLanguageQuery\Configuration;
use Kmi\Typo3NaturalLanguageQuery\Entity\Query;
use Kmi\Typo3NaturalLanguageQuery\Service\DatabaseService;
use Kmi\Typo3NaturalLanguageQuery\Service\PromptGenerator;
use Kmi\Typo3NaturalLanguageQuery\Service\SchemaService;
use Kmi\Typo3NaturalLanguageQuery\Type\QueryType;
use OpenAI;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class OpenAIConnector
{
    protected OpenAI\Client $client;

    protected array $configuration;

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly SchemaService $schemaService,
        private readonly PromptGenerator $promptGenerator,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
        $this->configuration = $this->extensionConfiguration->get(Configuration::EXT_KEY);

        if ($this->configuration['api']['key'] === '') {
            throw new \Exception('OpenAI API key is missing in extension configuration "api.key"', 2139736709);
        }

        $this->client = OpenAI::client($this->configuration['api']['key']);
    }

    public function chat(Query &$query, QueryType $type = QueryType::QUERY): void
    {
        $parameters = $this->prepareParameters($query, $type);
        $prompt = $this->promptGenerator->renderPrompt($parameters);

        $response = $this->queryOpenAi($prompt);
        $this->parseResponse($response, $query, $type);
    }

    protected function prepareParameters(Query $query, QueryType $type): array
    {
        $parameters = [
            'dialect' => $this->databaseService->getDatabasePlatformAndVersion(),
            'query' => $query,
        ];

        switch ($type) {
            case QueryType::TABLE:
                $parameters['tables'] = $this->schemaService->describeTables();
                break;
            case QueryType::QUERY:
                $parameters['tables'] = [$this->schemaService->describeTable($query->table)];
                break;
        }

        return $parameters;
    }

    protected function queryOpenAi(string $prompt, float $temperature = 0.0): string
    {
        $completions = $this->client->completions()->create([
            'model' => $this->configuration['api']['model'],
            'prompt' => $prompt,
            'temperature' => $temperature,
            'max_tokens' => 100,
            'logprobs' => 0, // https://github.com/openai-php/client/issues/522
        ]);

        return $completions->choices[0]->text;
    }

    protected function parseResponse(string $response, Query &$query, QueryType $type): void
    {
        $separator = null;
        $explodeKey = 0;
        switch ($type) {
            case QueryType::TABLE:
                $separator = 'SQLQuery';
                break;
            case QueryType::QUERY:
                $separator = 'SQLResult';
                break;
            case QueryType::ANSWER:
                $separator = 'Answer: "';
                $explodeKey = 1;
                break;
        }

        // We just want a part of the response
        if (str_contains($response, $separator)) {
            $response = explode($separator, $response)[$explodeKey];
        }

        // If the table is responded with the description brackets, remove them
        if ($type === QueryType::TABLE) {
            $response = trim(preg_replace('/\s*\(.*?\)\s*/', '', $response));
        }

        // Remove newlines and carriage returns
        $response = rtrim(str_replace(["\r", "\n"], ' ', $response));

        // Remove trailing quotes
        if (str_ends_with($response, '"')) {
            $response= substr($response, 0, -1);
        }

        // Fetch UIDs from answer
        if ($type === QueryType::ANSWER) {
            if (str_contains($response, 'UID=')) {
                preg_match_all('/\(UID=\s*([\d,\s]+)\)/', $response, $matches);
                $uids = isset($matches[1][0]) ? preg_split('/\s*,\s*/', $matches[1][0]) : [];
                $response = preg_replace('/\(UID=\s*[\d,\s]+\)/', '', $response);
                $query->resultSet = $uids;
            } else {
                $response = trim(preg_replace('/\s*\(.*?\)\s*/', '', $response));
            }
        }
        $query->{$type->value} = $response;
    }
}
