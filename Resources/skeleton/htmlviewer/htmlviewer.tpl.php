<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

use App\Domain\Log\Model\LogInterface;
use App\SharedKernel\Viewer\AbstractControllerViewer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\<?php echo $exceptionNamespace; ?>Cannot<?php echo $useCaseName; ?>Exception;
use App\<?php echo $presentationNamespace.$useCaseName; ?>ViewModel;

class <?php echo $className; ?> extends AbstractControllerViewer
{
    /**
     * @throws Cannot<?php echo $useCaseName; ?>Exception
     */
    public function generateView(
        <?php echo $useCaseName; ?>ViewModel $viewModel
    ): Response {
        try {
            $view = $this->prepareView($viewModel);

            if ($view instanceof RedirectResponse) {
                return $view;
            }

            return new Response($this->getContent($viewModel));
        } catch (HttpException $e) {
            $this->logger->log(LogInterface::INFO, $e->getTraceAsString());
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->log(LogInterface::CRITICAL, $e->getTraceAsString());
            throw new Cannot<?php echo $useCaseName; ?>Exception($e->getMessage());
        }
    }

    protected function getContent(<?php echo $useCaseName; ?>ViewModel $viewModel): string
    {
        try {
            //TODO Build your content
            return '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
