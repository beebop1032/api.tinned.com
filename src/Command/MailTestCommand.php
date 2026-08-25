<?php

namespace App\Command;

use App\Service\Marketing\ResendMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Envoie un email de test réel via Resend pour vérifier la config (clé + domaine vérifié).
 *
 *   php bin/console app:mail:test toi@exemple.com
 *
 * Retourne un échec si l'envoi n'a pas abouti (clé absente, domaine non vérifié, 4xx/5xx).
 */
#[AsCommand(
    name: 'app:mail:test',
    description: 'Envoie un email de test via Resend pour valider la configuration.',
)]
class MailTestCommand extends Command
{
    public function __construct(
        private readonly ResendMailer $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Adresse destinataire du test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('email');

        $ok = $this->mailer->sendEmail(
            $to,
            'Test Resend — Tinned',
            '<div style="font-family:Arial,sans-serif;line-height:1.6">'
            .'<h2>Test réussi ✓</h2><p>Si vous lisez cet email, Resend est correctement configuré.</p></div>',
        );

        if ($ok) {
            $io->success(sprintf('Email envoyé à %s. Vérifiez la réception (et les spams).', $to));

            return Command::SUCCESS;
        }

        $io->error(
            'Envoi échoué. Vérifiez : RESEND_API_KEY renseignée dans .env.local, '
            .'domaine de RESEND_FROM vérifié dans le dashboard Resend, et les logs (var/log).'
        );

        return Command::FAILURE;
    }
}
