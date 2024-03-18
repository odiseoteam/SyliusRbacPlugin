<?php

declare(strict_types=1);

namespace Odiseo\SyliusRbacPlugin\Cli;

use Odiseo\SyliusRbacPlugin\Cli\Granter\AdministratorAccessGranterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class GrantAccessCommand extends Command
{
    public function __construct(
        private AdministratorAccessGranterInterface $administratorAccessGranter,
    ) {
        parent::__construct('odiseo:rbac:grant-access');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Grants access to chosen sections for administrator')
            ->addArgument('roleName', InputOption::VALUE_REQUIRED)
            ->addArgument('sections', InputArgument::IS_ARRAY | InputOption::VALUE_REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $administratorEmail = $this->getAdministratorEmail($input, $output);

        /** @var string $roleName */
        $roleName = $input->getArgument('roleName');
        /** @var array $sections */
        $sections = $input->getArgument('sections');

        try {
            $this->administratorAccessGranter->__invoke(
                $administratorEmail,
                $roleName,
                $sections,
            );
        } catch (\InvalidArgumentException $exception) {
            $output->writeln($exception->getMessage());
        }

        return 0;
    }

    private function getAdministratorEmail(InputInterface $input, OutputInterface $output): string
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $message = 'In order to permit access to admin panel sections for given administrator, ';
        $message .= 'please provide administrator\'s email address: ';

        $question = new Question($message);

        /** @var string $administratorEmail */
        $administratorEmail = $helper->ask($input, $output, $question);

        return $administratorEmail;
    }
}
