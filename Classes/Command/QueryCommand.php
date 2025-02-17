<?php

declare(strict_types=1);

namespace Kmi\Typo3NaturalLanguageQuery\Command;

use Kmi\Typo3NaturalLanguageQuery\Service\Solver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class QueryCommand extends Command
{
    public function __construct(private readonly Solver $solver, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Ask a natural language question for querying database records')
            ->addArgument(
                'question',
                InputArgument::OPTIONAL,
            )
            ->addArgument(
                'table',
                InputArgument::OPTIONAL,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('TYPO3 Natural Language Query');

        $question = $input->getArgument('question');
        if ($question === null) {
            $question = $io->ask('What do you want to know?');
        }

        $table = $input->getArgument('table');

        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('Thinking...');
        $progressIndicator->advance();
        $query = $this->solver->solve($question, $table);
        $progressIndicator->finish($query->answer);

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

        return Command::SUCCESS;
    }
}
