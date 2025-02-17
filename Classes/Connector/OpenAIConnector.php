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

        if ($this->configuration['api.key'] === '') {
            throw new \Exception('OpenAI API key is missing in extension configuration "api.key"', 2139736709);
        }

        $this->client = OpenAI::client($this->configuration['api.key']);
    }

    public function chat(Query &$query, QueryType $type): void
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
            'model' => $this->configuration['api.model'],
            'prompt' => $prompt,
            'temperature' => $temperature,
            'max_tokens' => 100,
            'logprobs' => 0, // https://github.com/openai-php/client/issues/522
        ]);

        return $completions->choices[0]->text;
    }

    protected function parseResponse(string $response, Query &$query, QueryType $type): void
    {
        if (str_contains($response, 'SQLResult')) {
            $response = explode('SQLResult', $response)[0];
        }
        $response = rtrim(str_replace(["\r", "\n"], ' ', $response));

        if (str_ends_with($response, '"')) {
            $response= substr($response, 0, -1);
        }
        $query->{$type->value} = $response;

        //        preg_match('/Question: "(.*?)"/', $response, $question);
        //        preg_match('/SQLQuery: "(.*?)"/', $response, $sqlQuery);
        //        preg_match('/SQLResult: "(.*?)"/', $response, $sqlResult);
        //        preg_match('/Answer: "(.*?)"/', $response, $answer);
        //
        //        $query->question = $question[1];
        //        $query->sqlQuery = $sqlQuery[1];
        //        $query->sqlResult = $sqlResult[1];
        //        $query->answer = $answer[1];
    }
}
