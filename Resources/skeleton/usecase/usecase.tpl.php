<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

use App\Domain\Monitoring\Entity\MonitoringUseCase;
use App\Domain\Monitoring\Service\MonitoringUseCaseBackgroundService;
use App\<?php echo $presentationNamespace . $useCaseName; ?>ViewModel;
use App\UseCase\AbstractUseCase;

class <?php echo $className; ?> extends AbstractUseCase
{
    public function __construct(
        private readonly MonitoringUseCaseBackgroundService $monitoringUseCaseBackgroundService
    ) {}

    public function execute(
        <?php echo $useCaseName; ?>Request $request,
        <?php echo $useCaseName; ?>Presenter $presenter
    ): void {
        $viewModel = new <?php echo $useCaseName; ?>ViewModel();

        $monitoringUseCase = new MonitoringUseCase();
        $monitoringUseCase->setName(__CLASS__);
        $monitoringUseCase->setParameters((array) $request);
        $monitoringUseCase->start();

        try {
            //TODO write your use case here
        } catch (\Throwable $e) {
            $monitoringUseCase->enable();
            $this->monitoringUseCaseBackgroundService->exceptionThrown($monitoringUseCase, $e);

            $viewModel->exceptions[] = $e;
        }

        $monitoringUseCase->end();
        $this->monitoringUseCaseBackgroundService->addToBackground($monitoringUseCase);

        $presenter->present($viewModel);
    }
}
