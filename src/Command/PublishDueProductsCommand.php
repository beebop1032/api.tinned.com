<?php

namespace App\Command;

use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Met en ligne automatiquement les produits dont la date de lancement (releaseAt) est
 * atteinte : coming_soon / preorder -> available. Le passage en available déclenche le
 * listener ProductAvailabilityListener, donc les inscrits « prévenez-moi » sont notifiés
 * dans la foulée. La « mise en route » devient entièrement automatique.
 *
 * À lancer sur un cron (ex. toutes les 5 min), comme app:expire-stale-payments :
 *   * /5 * * * *  php /chemin/api/bin/console app:publish-due-products
 */
#[AsCommand(
    name: 'app:publish-due-products',
    description: 'Passe en ligne les produits dont la date de lancement est atteinte (+ notifie les inscrits).',
)]
class PublishDueProductsCommand extends Command
{
    private const PRELAUNCH = ['coming_soon', 'preorder'];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les produits éligibles sans les publier.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $now = new \DateTimeImmutable();

        /** @var list<Product> $due */
        $due = $this->em->getRepository(Product::class)->createQueryBuilder('p')
            ->where('p.availability IN (:pre)')
            ->andWhere('p.releaseAt IS NOT NULL')
            ->andWhere('p.releaseAt <= :now')
            ->setParameter('pre', self::PRELAUNCH)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        if ($due === []) {
            $io->info('Aucun produit à mettre en ligne.');

            return Command::SUCCESS;
        }

        foreach ($due as $product) {
            $io->writeln(sprintf(
                ' • %s (%s, lancement %s)',
                $product->getName(),
                $product->getAvailability(),
                $product->getReleaseAt()?->format('Y-m-d H:i') ?? '?',
            ));
            if (!$dryRun) {
                $product->setAvailability('available');
            }
        }

        if ($dryRun) {
            $io->note(sprintf('%d produit(s) éligible(s) — dry-run, rien publié.', \count($due)));

            return Command::SUCCESS;
        }

        // Un seul flush : le listener onFlush/postFlush voit toutes les bascules et notifie les inscrits.
        $this->em->flush();

        $io->success(sprintf('%d produit(s) mis en ligne (inscrits notifiés).', \count($due)));

        return Command::SUCCESS;
    }
}
