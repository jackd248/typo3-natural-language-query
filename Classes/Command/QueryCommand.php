<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Command;

use Kmi\Typo3NaturalLanguageQuery\Entity\Query;
use Kmi\Typo3NaturalLanguageQuery\Service\Solver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class QueryCommand extends Command
{
    public function __construct(private readonly Solver $solver, ?string $name = null)
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
        $output->writeln('TYPO3 Natural Language Query');

        $question = $input->getArgument('question');
        if ($question === null) {
            $question = $io->ask('What do you want to know?');
        }

        $table = $input->getArgument('table');
        //        if ($table === null) {
        //            $tableSelection = new ChoiceQuestion(
        //                'Which table do you want to query?',
        //                $this->getTables(),
        //                0
        //            );
        //            $table = $io->askQuestion($tableSelection);
        //        }

        $progressIndicator = new ProgressIndicator($output);
        $query = $this->solver->solve($question, $table);

        if ($output->isVerbose()) {
            $table = new Table($output);
            $table
                ->setHeaders(['Question', 'Database Table', 'SQL Query', 'SQL Result', 'Answer'])
                ->setRows([
                    [$query->question, $query->table, $query->sqlQuery, $query->sqlResult, $query->answer],
                ])
            ;
            $table->setVertical();
            $table->render();
        }

        $progressIndicator->finish($query->answer);
        return Command::SUCCESS;
    }

    //    private function getTables(): array
    //    {
    //        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName('Default');
    //        $schemaManager = $connection->getSchemaInformation();
    //        $tables = $schemaManager->listTableNames();
    //
    //        foreach ($tables as $key => $table) {
    //            if (str_starts_with($table, 'sys_')) {
    //                unset($tables[$key]);
    //            }
    //        }
    //        ksort($tables);
    //
    //        return $tables;
    //    }
}
