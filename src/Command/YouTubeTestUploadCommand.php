<?php

namespace App\Command;

use App\Service\YouTubeUploader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Vérification de bout en bout : envoie un fichier sur la chaîne et affiche son adresse.
 * Ne touche ni à la base, ni au site. Si elle passe, tout le reste n'est que de la mise en forme.
 */
#[AsCommand(
    name: 'app:youtube-test-upload',
    description: 'Envoie une vidéo de test sur la chaîne YouTube',
)]
class YouTubeTestUploadCommand extends Command
{
    public function __construct(private YouTubeUploader $uploader)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier vidéo')
            ->addOption('privacy', null, InputOption::VALUE_REQUIRED, 'public, unlisted ou private', 'public');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $file = (string) $input->getArgument('file');
        $privacy = (string) $input->getOption('privacy');

        if (!is_file($file)) {
            $io->error(sprintf('Fichier introuvable : %s', $file));

            return Command::FAILURE;
        }

        $io->text(sprintf(
            'Envoi de %s (%s Mo) en « %s »…',
            basename($file),
            round(filesize($file) / 1048576, 1),
            $privacy,
        ));

        try {
            $videoId = $this->uploader->upload($file, 'Test Brillance Home', 'Vidéo de test, à supprimer.', $privacy);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Vidéo envoyée.');
        $io->writeln('Identifiant : ' . $videoId);
        $io->writeln('Adresse : https://www.youtube.com/watch?v=' . $videoId);

        return Command::SUCCESS;
    }
}