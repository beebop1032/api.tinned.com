<?php

namespace App\Command;

use App\Entity\Product\Product;
use App\Service\Marketing\LaunchNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Notifie manuellement les inscrits « prévenez-moi » d'un produit.
 *
 *   php bin/console app:notify:launch <slug|id>                  # email « c'est en ligne »
 *   php bin/console app:notify:launch <slug|id> --back-in-stock  # email « de retour en stock »
 *
 * Idempotent (notifiedAt) : relancer la commande ne renvoie rien aux déjà-notifiés.
 */
#[AsCommand(
    name: 'app:notify:launch',
    description: 'Envoie l\'email de mise en ligne / retour en stock aux inscrits d\'un produit.',
)]
class NotifyLaunchCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LaunchNotifier $launchNotifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('product', InputArgument::REQUIRED, 'Slug ou ID du produit')
            ->addOption('back-in-stock', null, InputOption::VALUE_NONE, 'Envoyer l\'email « retour en stock » au lieu de « mise en ligne »');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ref = (string) $input->getArgument('product');
        $backInStock = (bool) $input->getOption('back-in-stock');

        $repo = $this->em->getRepository(Product::class);
        $product = ctype_digit($ref)
            ? $repo->find((int) $ref)
            : $repo->findOneBy(['slug' => $ref]);

        if (!$product instanceof Product) {
            $io->error(sprintf('Produit introuvable : %s', $ref));

            return Command::FAILURE;
        }

        $count = $backInStock
            ? $this->launchNotifier->notifyBackInStock($product)
            : $this->launchNotifier->notifyProductLive($product);

        $io->success(sprintf(
            '%d inscrit(s) notifié(s) pour « %s » (%s).',
            $count,
            $product->getName(),
            $backInStock ? 'retour en stock' : 'mise en ligne',
        ));

        return Command::SUCCESS;
    }
}
