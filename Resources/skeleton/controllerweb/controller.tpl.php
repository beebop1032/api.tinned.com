<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

use App\Infrastructure\Symfony\Controller\web\KazidomiController;
use App\<?php echo $viewerHTMLNamespace.$useCaseName; ?>HTMLViewer;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>Presenter;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>Request;
use Symfony\Component\HttpFoundation\Response;

class <?php echo $className; ?> extends KazidomiController
{
    public function __invoke(
        <?php echo $useCaseName; ?>Request $request,
        <?php echo $useCaseName; ?> $useCase,
        <?php echo $useCaseName; ?>HTMLViewer $viewer
    ): Response {
        $presenter = new <?php echo $useCaseName; ?>Presenter();
        $useCase->execute($request, $presenter);

        return $viewer->generateView($presenter->getViewModel());
    }
}
