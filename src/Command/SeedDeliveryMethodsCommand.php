<?php

namespace App\Command;

use App\Entity\Delivery\DeliveryMethod;
use App\Entity\Delivery\DeliveryPrice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * (Ré)initialise les méthodes de livraison : uniquement Bpost à domicile + Bpost Point
 * Colis (locker), pour BE et FR. Idempotent (purge puis réinsère).
 *
 *   php bin/console app:seed-delivery-methods
 *
 * À lancer sur le serveur : la prod déploie via `d:s:u` (schéma seulement), pas les
 * migrations — donc les données ne sont pas semées automatiquement.
 */
#[AsCommand(
    name: 'app:seed-delivery-methods',
    description: 'Initialise les méthodes de livraison (Bpost domicile + Bpost locker, BE/FR).',
)]
class SeedDeliveryMethodsCommand extends Command
{
    // Gratuit au-dessus de ce seuil (en cents).
    private const FREE_FROM_CENTS = 6000;

    /** @var list<array{code: string, method: string, name: string, recommended: bool, position: int, baseCents: int}> */
    private const METHODS = [
        ['code' => 'bpost-home', 'method' => DeliveryMethod::METHOD_HOME, 'name' => 'Bpost à domicile', 'recommended' => true, 'position' => 1, 'baseCents' => 595],
        // Bpost Point Colis (locker) : réactivé quand la sélection de point relais sera
        // intégrée (geowidget Bpost). Un locker sans choix de point n'a pas de sens.
    ];

    private const COUNTRIES = ['BE', 'FR'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Purge : on veut EXACTEMENT ces méthodes (retire dpd, mondial relay, etc.).
        // Les commandes passées gardent leur snapshot (carrierCode/carrierNameSnapshot),
        // donc supprimer les méthodes ne casse pas l'historique.
        $this->em->createQuery('DELETE FROM '.DeliveryPrice::class.' p')->execute();
        $this->em->createQuery('DELETE FROM '.DeliveryMethod::class.' m')->execute();

        $count = 0;
        foreach (self::METHODS as $spec) {
            foreach (self::COUNTRIES as $country) {
                $method = (new DeliveryMethod())
                    ->setCode($spec['code'])
                    ->setProvider('bpost')
                    ->setMethod($spec['method'])
                    ->setName($spec['name'])
                    ->setCountryCode($country)
                    ->setPosition($spec['position'])
                    ->setRecommended($spec['recommended'])
                    ->setActive(true);

                // Tarif de base, puis gratuit au-dessus du seuil.
                $method->addPrice((new DeliveryPrice())->setOrderPriceCents(0)->setPriceCents($spec['baseCents']));
                $method->addPrice((new DeliveryPrice())->setOrderPriceCents(self::FREE_FROM_CENTS)->setPriceCents(0));

                $this->em->persist($method);
                ++$count;
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            '%d méthodes créées : Bpost domicile (%.2f€) + Bpost Point Colis (%.2f€), BE/FR, gratuit ≥ %d€.',
            $count,
            self::METHODS[0]['baseCents'] / 100,
            self::METHODS[1]['baseCents'] / 100,
            self::FREE_FROM_CENTS / 100,
        ));

        return Command::SUCCESS;
    }
}
