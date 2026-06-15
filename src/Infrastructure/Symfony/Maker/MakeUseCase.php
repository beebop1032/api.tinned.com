<?php

/** @noinspection ClassConstantCanBeUsedInspection */

namespace App\Infrastructure\Symfony\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

class MakeUseCase extends AbstractMaker
{
    public const string ACTION_USE_CASE = 'UseCase';
    public const string ACTION_CONTROLLER_WEB = 'ControllerWeb';
    public const string ACTION_CONTROLLER_BACKOFFICE = 'ControllerBackoffice';
    public const string ACTION_CONTROLLER_API = 'ControllerApi';
    public const string ACTION_COMMAND = 'Command';
    public const string ACTION_PARAM_VALUE_RESOLVER = 'ValueResolver';
    public const string ACTION_PRESENTATION = 'Presentation';
    public const string ACTION_VIEWER_HTML = 'HTMLViewer';
    public const string ACTION_VIEWER_JSON = 'JSONViewer';
    public const string ACTION_VIEWER_PDF = 'PDFViewer';
    public const string ACTION_EXCEPTION = 'Exception';

    protected array $classSuffix = [
        self::ACTION_USE_CASE => [
            '',
            'Presenter',
            'Request',
        ],
    ];

    public const string API_ANSWER = 'API';
    public const string COMMAND_ANSWER = 'COMMAND';
    public const string BACKOFFICE_CONTROLLER_ANSWER = 'BACKOFFICE_CONTROLLER';

    public const array TYPE_ANSWER = [
        self::API_ANSWER,
        self::COMMAND_ANSWER,
        self::BACKOFFICE_CONTROLLER_ANSWER,
    ];

    public const string PROJECT_DIR = __DIR__ . '/../../../../';
    public const string USE_CASE_DIR = self::PROJECT_DIR . 'src/UseCase/';
    public const string CONTROLLER_WEB_DIR = self::PROJECT_DIR . 'src/Infrastructure/Symfony/Controller/web/';
    public const string CONTROLLER_BACKOFFICE_DIR = self::PROJECT_DIR . 'src/Infrastructure/Symfony/Controller/backoffice/';
    public const string CONTROLLER_API_DIR = self::PROJECT_DIR . 'src/Infrastructure/Symfony/Controller/api/';
    public const string COMMAND_API_DIR = self::PROJECT_DIR . 'src/Infrastructure/Symfony/Command/';

    public const string PARAM_VALUE_RESOLVER_DIR = self::PROJECT_DIR . 'src/Infrastructure/Symfony/ValueResolver/';
    public const string PRESENTATION_DIR = self::PROJECT_DIR . 'src/Presentation/';
    public const string VIEWER_DIR = self::PROJECT_DIR . 'src/SharedKernel/Viewer/';
    public const string DOMAIN_DIR = self::PROJECT_DIR . 'src/Domain/';
    public const string TEMPLATE_DIR = self::PROJECT_DIR . 'Resources/skeleton/';
    public const string USE_CASE_NAMESPACE_PREFIX = 'UseCase\\';
    public const string CONTROLLER_WEB_NAMESPACE_PREFIX = 'Infrastructure\\Symfony\\Controller\\web\\';
    public const string CONTROLLER_BACKOFFICE_NAMESPACE_PREFIX = 'Infrastructure\\Symfony\\Controller\\backoffice\\';

    public const string CONTROLLER_API_NAMESPACE_PREFIX = 'Infrastructure\\Symfony\\Controller\\api\\';
    public const string COMMAND_NAMESPACE_PREFIX = 'Infrastructure\\Symfony\\Command\\';
    public const string PARAM_VALUE_RESOLVER_NAMESPACE_PREFIX = 'Infrastructure\\Symfony\\ValueResolver\\';
    public const string PRESENTATION_NAMESPACE_PREFIX = 'Presentation\\';
    public const string VIEWER_NAMESPACE_PREFIX = 'SharedKernel\\Viewer\\';
    public const string DOMAIN_NAMESPACE_PREFIX = 'Domain\\';

    protected $namespace;

    public static function getCommandName(): string
    {
        return 'make:use-case';
    }

    public static function getCommandDescription(): string
    {
        return 'create all basics files for a new use case.';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the use case (e.g. <fg=yellow>Account/Security/CreateUser</>)')
            ->setHelp(file_get_contents(__DIR__ . '/../../../../Resources/help/MakeUseCase.txt'))
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $typeChoose = $io->choice(
            'What kind of use case you want to create ?',
            self::TYPE_ANSWER,
            self::COMMAND_ANSWER
        );

        $useCaseNamespace = str_replace('/', '\\', $input->getArgument('name'));
        $useCaseRoute = trim(str_replace('\\', '/', $input->getArgument('name')), '/') . '/';
        $useCaseExplode = explode('\\', $useCaseNamespace);
        $useCaseName = Str::asClassName(end($useCaseExplode));

        $useCaseFilesName = [];

        foreach ($this->classSuffix[self::ACTION_USE_CASE] as $useCaseSuffix) {
            $useCaseFilesName[self::ACTION_USE_CASE][] = $useCaseName . $useCaseSuffix;
        }

        $useCaseFilesName[self::ACTION_PRESENTATION][] = $useCaseName . 'ViewModel';

        $viewerKey = null;
        $viewerType = null;

        if ($typeChoose === self::COMMAND_ANSWER) {
            $useCaseFilesName[self::ACTION_COMMAND][] = $useCaseName . 'Command';
        }

        if ($typeChoose === self::API_ANSWER) {

            $viewerKey = self::ACTION_VIEWER_JSON;
            $viewerType = self::ACTION_VIEWER_JSON;
            $useCaseFilesName[self::ACTION_CONTROLLER_API][] = $useCaseName . 'Controller';
            $useCaseFilesName[self::ACTION_PARAM_VALUE_RESOLVER][] = $useCaseName . 'ValueResolver';
        }

        if ($typeChoose === self::BACKOFFICE_CONTROLLER_ANSWER) {

            $viewerKey = self::ACTION_VIEWER_HTML;
            $viewerType = self::ACTION_VIEWER_HTML;
            $useCaseFilesName[self::ACTION_CONTROLLER_BACKOFFICE][] = $useCaseName . 'Controller';
            $useCaseFilesName[self::ACTION_PARAM_VALUE_RESOLVER][] = $useCaseName . 'ValueResolver';
        }

        if($viewerKey) {
            $useCaseFilesName[$viewerKey][] = $useCaseName . $viewerType;
        }

        $useCaseFilesName[self::ACTION_EXCEPTION][] = 'Cannot' . $useCaseName . 'Exception';

        $commandName = $this->toCommandName($useCaseNamespace);

        foreach ($useCaseFilesName as $action => $filesName) {

            $nameSpacePrefix = $this->getNamespacePrefix($action);
            $nameSpaceSuffix = $this->getNamespaceSuffix($action);

            $dirPrefix = $this->getDir($action);
            $dirSuffix = $this->getDirSuffix($action);
            $dir = $dirPrefix . $this->resolveUseCaseRoute($useCaseRoute, $action) . $dirSuffix;

            foreach ($filesName as $fileName) {

                $fileSuffix = strtolower(str_replace($useCaseName, '', $fileName));

                if ($fileSuffix === '') {
                    $fileSuffix = 'usecase';
                }

                if($fileSuffix === 'request' && $typeChoose === self::COMMAND_ANSWER) {
                    $fileSuffix = 'request-for-command';
                }

                if($fileSuffix === 'viewmodel' && $typeChoose === self::COMMAND_ANSWER) {
                    $fileSuffix = 'viewmodel-for-command';
                }

                $template = self::TEMPLATE_DIR . strtolower($action) . '/' . $fileSuffix . '.tpl.php';

                try {

                    $generator->generateFile(
                        $dir . $fileName . '.php',
                        $template,
                        [
                            'className' => $fileName,
                            'namespace' => $this->resolveNamespace($nameSpacePrefix, $useCaseNamespace, $nameSpaceSuffix, $action),
                            'useCaseName' => $useCaseName,
                            'commandName' => $commandName,
                            'presentationNamespace' => 'App\\' . self::PRESENTATION_NAMESPACE_PREFIX . $useCaseNamespace . '\\',
                            'useCaseNamespace' => 'App\\' . self::USE_CASE_NAMESPACE_PREFIX . $useCaseNamespace . '\\',
                            'viewerHTMLNamespace' => 'App\\' . self::VIEWER_NAMESPACE_PREFIX . $useCaseNamespace . '\\HTML\\',
                            'viewerJSONNamespace' => 'App\\' . self::VIEWER_NAMESPACE_PREFIX . $useCaseNamespace . '\\JSON\\',
                            'exceptionNamespace' => 'App\\' . self::DOMAIN_NAMESPACE_PREFIX . $useCaseNamespace . '\\Exception\\',
                        ]
                    );
                } catch (\Exception $e) {
                    $io->comment('<fg=green>no change</>: ' . $e->getMessage());
                }
            }
        }

        $generator->writeChanges();
    }

    private function getNamespacePrefix(string $action): string
    {
        switch ($action) {
            case self::ACTION_USE_CASE:
                return self::USE_CASE_NAMESPACE_PREFIX;
            case self::ACTION_PRESENTATION:
                return self::PRESENTATION_NAMESPACE_PREFIX;
            case self::ACTION_CONTROLLER_WEB:
                return self::CONTROLLER_WEB_NAMESPACE_PREFIX;
            case self::ACTION_CONTROLLER_BACKOFFICE:
                return self::CONTROLLER_BACKOFFICE_NAMESPACE_PREFIX;
            case self::ACTION_CONTROLLER_API:
                return self::CONTROLLER_API_NAMESPACE_PREFIX;
            case self::ACTION_COMMAND:
                return self::COMMAND_NAMESPACE_PREFIX;
            case self::ACTION_PARAM_VALUE_RESOLVER:
                return self::PARAM_VALUE_RESOLVER_NAMESPACE_PREFIX;
            case self::ACTION_VIEWER_HTML:
            case self::ACTION_VIEWER_JSON:
                return self::VIEWER_NAMESPACE_PREFIX;
            case self::ACTION_EXCEPTION:
                return self::DOMAIN_NAMESPACE_PREFIX;
            default:
                return '';
        }
    }

    public function getNamespaceSuffix(string $action): string
    {
        switch ($action) {
            case self::ACTION_VIEWER_HTML:
                return '\\HTML';
            case self::ACTION_VIEWER_JSON:
                return '\\JSON';
            case self::ACTION_VIEWER_PDF:
                return '\\PDF';
            case self::ACTION_EXCEPTION:
                return '\\Exception';
            default:
                return '';
        }
    }

    private function getDir(string $action): string
    {
        switch ($action) {
            case self::ACTION_USE_CASE:
                return self::USE_CASE_DIR;
            case self::ACTION_PRESENTATION:
                return self::PRESENTATION_DIR;
            case self::ACTION_CONTROLLER_WEB:
                return self::CONTROLLER_WEB_DIR;
            case self::ACTION_CONTROLLER_BACKOFFICE:
                return self::CONTROLLER_BACKOFFICE_DIR;
            case self::ACTION_CONTROLLER_API:
                return self::CONTROLLER_API_DIR;
            case self::ACTION_COMMAND:
                return self::COMMAND_API_DIR;
            case self::ACTION_PARAM_VALUE_RESOLVER:
                return self::PARAM_VALUE_RESOLVER_DIR;
            case self::ACTION_VIEWER_HTML:
            case self::ACTION_VIEWER_JSON:
                return self::VIEWER_DIR;
            case self::ACTION_EXCEPTION:
                return self::DOMAIN_DIR;
            default:
                return '';
        }
    }

    private function getDirSuffix(string $action): string
    {
        switch ($action) {
            case self::ACTION_VIEWER_HTML:
                return 'HTML/';
            case self::ACTION_VIEWER_JSON:
                return 'JSON/';
            case self::ACTION_VIEWER_PDF:
                return 'PDF/';
            case self::ACTION_EXCEPTION:
                return 'Exception/';
            default:
                return '';
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    private function resolveNamespace(string $prefix, string $useCaseNamespace, string $suffix, string $action): string
    {
        // Pour les commandes : retirer le dernier segment du namespace (ex: "Search\\CreateIndex" → "Search")
        if ($action === self::ACTION_COMMAND) {
            $segments = explode('\\', $useCaseNamespace);
            array_pop($segments);
            $useCaseNamespace = implode('\\', $segments);
        }

        return 'App\\'
            . trim($prefix, '\\')
            . '\\'
            . trim($useCaseNamespace, '\\')
            . ($suffix ? '\\' . trim($suffix, '\\') : '');
    }

    private function resolveUseCaseRoute(string $useCaseRoute, string $action): string
    {
        if ($action === self::ACTION_COMMAND) {
            return $useCaseRoute;
        }

        return $useCaseRoute;
    }

    public function toCommandName(string $input): string
    {
        return 'app:' . strtolower(
                preg_replace(
                    '/([a-z])([A-Z])/',
                    '$1-$2',
                    str_replace('\\', ':', $input)
                )
            );
    }
}
