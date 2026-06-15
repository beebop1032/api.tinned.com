<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

use App\Infrastructure\Symfony\Builder\Console\InputParametersBuilder;
use App\Infrastructure\Symfony\Console\ConsoleViewer;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>Presenter;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>Request;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: '<?php echo $commandName; ?>',
    description: 'This command executes the <?php echo $useCaseName; ?> use case'
)]
class <?php echo $className; ?> extends Command
{
    public const int MAX_SIMULTANEOUS_PROCESSES = 1;

    private ConsoleViewer $consoleViewer;
    private <?php echo $useCaseName; ?> $useCase;

    public function __construct(
        ConsoleViewer $consoleViewer,
        <?php echo $useCaseName; ?> $useCase
    ) {
        parent::__construct();
        $this->consoleViewer = $consoleViewer;
        $this->useCase = $useCase;
    }

    protected function configure(): void
    {
        $this
            ->addOption('force-release', null, InputOption::VALUE_NONE, 'Force release of concurrent locks if needed');
    }

    /**
    * @throws \Throwable
    * @throws \JsonException
    */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = new <?php echo $useCaseName; ?>Request();
        $request->name = $this->getName();
        $request->parameters = InputParametersBuilder::build($input, $this->getDefinition());
        $request->force = $input->getOption('force-release');
        $request->max_simultaneous_processes = self::MAX_SIMULTANEOUS_PROCESSES;

        $presenter = new <?php echo $useCaseName; ?>Presenter();

        $this->useCase->execute($request, $presenter);

        return $this->consoleViewer->generateView($presenter->getViewModel(), $input, $output);
    }
}
