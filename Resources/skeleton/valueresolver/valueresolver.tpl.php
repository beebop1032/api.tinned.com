<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use App\<?php echo $useCaseNamespace.$useCaseName; ?>Request;


class <?php echo $className; ?> implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== <?php echo $useCaseName; ?>Request::class) {
            return [];
        }

        $<?php echo lcfirst($useCaseName); ?>Request = new <?php echo $useCaseName; ?>Request();

        yield $<?php echo lcfirst($useCaseName); ?>Request;
    }
}