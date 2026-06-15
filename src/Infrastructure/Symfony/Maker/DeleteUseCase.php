<?php

namespace App\Infrastructure\Symfony\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

class DeleteUseCase extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'delete:use-case';
    }

    public static function getCommandDescription(): string
    {
        return 'Delete all files generated for a given use case.';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf): void
    {
        $command
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the use case (e.g. <fg=yellow>Account/CreateAccount</>)')
            ->setHelp('This command removes all generated files for a given use case (UseCase, Controller, Presenter, Viewers, etc).');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, $generator): void
    {
        $useCaseNamespace = str_replace('/', '\\', $input->getArgument('name'));
        $useCaseRoute = trim(str_replace('\\', '/', $input->getArgument('name')), '/') . '/';
        $useCaseExplode = explode('\\', $useCaseNamespace);
        $useCaseName = Str::asClassName(end($useCaseExplode));

        // même logique que MakeUseCase
        $filesToDelete = [];

        // UseCase
        $filesToDelete[] = MakeUseCase::USE_CASE_DIR . $useCaseRoute . $useCaseName . '.php';
        $filesToDelete[] = MakeUseCase::USE_CASE_DIR . $useCaseRoute . $useCaseName . 'Presenter.php';
        $filesToDelete[] = MakeUseCase::USE_CASE_DIR . $useCaseRoute . $useCaseName . 'Request.php';

        // Presentation
        $filesToDelete[] = MakeUseCase::PRESENTATION_DIR . $useCaseRoute . $useCaseName . 'ViewModel.php';

        // Controllers
        $filesToDelete[] = MakeUseCase::CONTROLLER_WEB_DIR . $useCaseRoute . $useCaseName . 'Controller.php';
        $filesToDelete[] = MakeUseCase::CONTROLLER_API_DIR . $useCaseRoute . $useCaseName . 'Controller.php';

        // Converter
        $filesToDelete[] = MakeUseCase::PARAM_VALUE_RESOLVER_DIR . $useCaseRoute . $useCaseName . 'ValueResolver.php';

        // Viewers
        $filesToDelete[] = MakeUseCase::VIEWER_DIR . $useCaseRoute . 'HTML/' . $useCaseName . 'HTMLViewer.php';
        $filesToDelete[] = MakeUseCase::VIEWER_DIR . $useCaseRoute . 'JSON/' . $useCaseName . 'JSONViewer.php';
        $filesToDelete[] = MakeUseCase::VIEWER_DIR . $useCaseRoute . 'PDF/' . $useCaseName . 'PDFViewer.php';

        // Exception
        $filesToDelete[] = MakeUseCase::DOMAIN_DIR . $useCaseRoute . 'Exception/Cannot' . $useCaseName . 'Exception.php';

        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
                $io->success('Deleted: ' . $file);
            } else {
                $io->comment('Not found: ' . $file);
            }
        }

        // Optionnel : supprimer les dossiers vides
        $this->removeEmptyDirs(dirname($filesToDelete[0]), $io);
    }

    private function removeEmptyDirs(string $dir, ConsoleStyle $io): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        $files = array_diff($files, ['.', '..']);
        if (count($files) === 0) {
            rmdir($dir);
            $io->comment('Removed empty dir: ' . $dir);
            // récursif vers le parent
            $this->removeEmptyDirs(dirname($dir), $io);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
