<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

interface <?php echo $className; ?>
{
    public function present(<?php echo $useCaseName; ?>Response $response): void;
}
