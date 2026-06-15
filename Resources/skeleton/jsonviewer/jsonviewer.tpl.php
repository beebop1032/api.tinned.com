<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

use App\Domain\Log\Model\LogInterface;
use App\SharedKernel\Viewer\AbstractControllerViewer;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    ): JsonResponse {
        try {
            $view = $this->prepareApiView($viewModel);

            if ($view instanceof JsonResponse) {
                return $view;
            }

            return new JsonResponse();
        } catch (HttpException $e) {
            $this->logger->log(LogInterface::INFO, $e->getTraceAsString());
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->log(LogInterface::CRITICAL, $e->getTraceAsString());
            throw new Cannot<?php echo $useCaseName; ?>Exception($e->getMessage());
        }
    }
}
