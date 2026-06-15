<?php echo "<?php\n"; ?>

namespace App\<?php echo $namespace; ?>;

use App\<?php echo $presentationNamespace.$useCaseName; ?>ViewModel;

class <?php echo $className; ?>
{
    protected <?php echo $useCaseName; ?>ViewModel $viewModel;

    public function present(<?php echo $useCaseName; ?>ViewModel $viewModel): void
    {
        $this->viewModel = $viewModel;
    }

    public function getViewModel(): <?php echo $useCaseName; ?>ViewModel
    {
        return $this->viewModel;
    }
}
