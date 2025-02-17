<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Command;

use Kmi\Typo3NaturalLanguageQuery\Connector\OpenAIConnector;
use Kmi\Typo3NaturalLanguageQuery\Entity\Query;
use Kmi\Typo3NaturalLanguageQuery\Service\DatabaseService;
use OpenAI;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class QueryCommand extends Command
{

    public function __construct(protected DatabaseService $databaseService, protected OpenAIConnector $openAIConnector, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setHelp('')
            ->addArgument(
                'table',
                InputArgument::OPTIONAL,
                '',
            )
            ->addArgument(
                'question',
                InputArgument::OPTIONAL,
                '',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $output->writeln('Hello world!');

        $table = $input->getArgument('table');
        if ($table === null) {
            $tableSelection = new ChoiceQuestion(
                'Which table do you want to query?',
                $this->getTables(),
                0
            );
            $table = $io->askQuestion($tableSelection);
        }

        $question = $input->getArgument('question');
        if ($question === null) {
            $question = $io->ask('What do you want to know?');
        }

        $query = new Query($table, $question);

        $output->writeln('Table: ' . $query->table);
        $output->writeln('Question: ' . $query->question);
        $output->writeln('SQL Query: ' . $query->sqlQuery);
        $output->writeln('SQL Result: ' . $query->sqlResult);
        $output->writeln('Answer: ' . $query->answer);

        $output->writeln('> First Request');

        $this->openAIConnector->chat($query);
        $this->databaseService->runDatabaseQuery($query);

        $output->writeln('Table: ' . $query->table);
        $output->writeln('Question: ' . $query->question);
        $output->writeln('SQL Query: ' . $query->sqlQuery);
        $output->writeln('SQL Result: ' . $query->sqlResult);
        $output->writeln('Answer: ' . $query->answer);

        $output->writeln('> Second Request');

        $this->openAIConnector->chat($query, 'answer');

        $output->writeln('Table: ' . $query->table);
        $output->writeln('Question: ' . $query->question);
        $output->writeln('SQL Query: ' . $query->sqlQuery);
        $output->writeln('SQL Result: ' . $query->sqlResult);
        $output->writeln('Answer: ' . $query->answer);


        $io->info($query->answer);

        return Command::SUCCESS;
    }

    private function getTables(): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName('Default');
        $schemaManager = $connection->getSchemaInformation();
        $tables = $schemaManager->listTableNames();

        foreach ($tables as $key => $table) {
            if (str_starts_with($table, 'sys_')) {
                unset($tables[$key]);
            }
        }
        ksort($tables);

        return $tables;
    }
}
