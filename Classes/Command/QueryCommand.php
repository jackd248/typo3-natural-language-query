<?php

declare(strict_types=1);

namespace KonradMichalik\Typo3NaturalLanguageQuery\Command;

use KonradMichalik\Typo3NaturalLanguageQuery\Service\SchemaService;
use KonradMichalik\Typo3NaturalLanguageQuery\Service\Solver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class QueryCommand extends Command
{
    public function __construct(private readonly Solver $solver, private readonly SchemaService $schemaService, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setHelp('')
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
        $query = $this->solver->solve(question: $question, table: $table);
        $progressIndicator->finish($query->answer);

        if ($output->isVerbose()) {
            $table = new Table($output);
            $table
                ->setHeaders(['Question', 'Database Table', 'SQL Query', 'SQL Result', 'Result Set', 'Answer'])
                ->setRows([
                    [$query->question, $query->table, $query->sqlQuery, $this->cutString($query->sqlResult), $this->cutString(json_encode($query->resultSet)), $query->answer],
                ])
            ;
            $table->setVertical();
            $table->render();
        }

        if ($query->resultSet !== null) {
            $rows = $this->schemaService->prepareResultSet($query);
            $table = new Table($output);
            $table
                ->setHeaders(['UID', 'Label', 'Link'])
                ->setRows($rows)
            ;
            $table->render();
        }

        return Command::SUCCESS;
    }

    private function cutString(string $string, int $length = 150): string
    {
        return (mb_strlen($string) > $length) ? mb_substr($string, 0, $length) . '…' : $string;
    }
}
