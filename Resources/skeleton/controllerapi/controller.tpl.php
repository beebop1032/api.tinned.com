<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

use App\Infrastructure\Symfony\Controller\api\KazidomiAPIController;
use App\<?php echo $viewerJSONNamespace.$useCaseName; ?>JSONViewer;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>Presenter;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>Request;
use Symfony\Component\HttpFoundation\Response;

class <?php echo $className; ?> extends KazidomiAPIController
{
    public function __invoke(
        <?php echo $useCaseName; ?>Request $request,
        <?php echo $useCaseName; ?> $useCase,
        <?php echo $useCaseName; ?>JSONViewer $viewer
    ): Response {
        $presenter = new <?php echo $useCaseName; ?>Presenter();
        $useCase->execute($request, $presenter);

        return $viewer->generateView($presenter->getViewModel());
    }
}
