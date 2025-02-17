<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Connector;

use Kmi\Typo3NaturalLanguageQuery\Entity\Query;
use Kmi\Typo3NaturalLanguageQuery\Service\DatabaseService;
use Kmi\Typo3NaturalLanguageQuery\Service\PromptGenerator;
use OpenAI;

final class OpenAIConnector
{
    protected OpenAI\Client $client;

    public function __construct(protected DatabaseService $databaseService, protected PromptGenerator $promptGenerator)
    {
        $this->client = OpenAI::client('ToDo');
    }

    public function chat(Query &$query, string $desiredField = 'sqlQuery'): void
    {
        $parameters = [
            'dialect' => $this->databaseService->getDatabasePlatformAndVersion(),
            'tables' => [
                0 => [
                    'name' => $query->table,
                    'columns' => $this->databaseService->describeFieldsOfTable($query->table),
                ],
            ],
            'question' => $query->question,
        ];

        if ($desiredField === 'answer') {
            $parameters['query'] = $query->sqlQuery;
            $parameters['result'] = $query->sqlResult;
        }
        $prompt = $this->promptGenerator->renderPrompt($parameters);

        $response = $this->queryOpenAi($prompt);
        $this->parseResponse($response, $query, $desiredField);
    }

    protected function queryOpenAi(string $prompt, float $temperature = 0.0): string
    {
        $completions = $this->client->completions()->create([
            'model' => 'gpt-3.5-turbo-instruct',
            'prompt' => $prompt,
            'temperature' => $temperature,
            'max_tokens' => 100,
            'logprobs' => 0,
        ]);

        return $completions->choices[0]->text;
    }

    protected function parseResponse(string $response, Query &$query, ?string $desiredField = null): void
    {
        if ($desiredField) {
            if (str_contains($response, 'SQLResult')) {
                $response = explode('SQLResult', $response)[0];
            }
            $response = rtrim(str_replace(["\r", "\n"], ' ', $response));

            if (substr($response, -1) === '"') {
                $response= substr($response, 0, -1);
            }
            $query->{$desiredField} = $response;
            return;
        }

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
