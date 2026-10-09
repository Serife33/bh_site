<?php

namespace App\Command;

use Google\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Commande à usage unique : elle récupère le jeton de rafraîchissement YouTube.
 * On la lance une fois en local, on colle le jeton obtenu dans .env.local, et on n'y revient plus.
 *
 * Aucune route de rappel n'existe dans le site : Google renvoie vers localhost, dépose le code
 * dans la barre d'adresse, et la page affiche une 404. C'est voulu — seul le code compte.
 */
#[AsCommand(
    name: 'app:youtube-authorize',
    description: 'Obtient le jeton de rafraîchissement YouTube (une seule fois)',
)]
class YouTubeAuthorizeCommand extends Command
{
    private const SCOPES = [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.force-ssl',
    ];

    public function __construct(
        #[Autowire('%env(YOUTUBE_CLIENT_ID)%')] private string $clientId,
        #[Autowire('%env(YOUTUBE_CLIENT_SECRET)%')] private string $clientSecret,
        #[Autowire('%env(YOUTUBE_REDIRECT_URI)%')] private string $redirectUri,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->clientId === '' || $this->clientSecret === '') {
            $io->error('YOUTUBE_CLIENT_ID et YOUTUBE_CLIENT_SECRET sont vides. Renseigne-les dans .env.local.');

            return Command::FAILURE;
        }

        $client = new Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUri);
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');   // sans ça, Google ne délivre aucun jeton de rafraîchissement
        $client->setPrompt('consent');       // force son renvoi même si l'accès a déjà été accordé une fois

        $io->section('1. Ouvre cette adresse dans ton navigateur');
        $io->writeln($client->createAuthUrl());
        $io->newLine();
        $io->text([
            'Connecte-toi avec le compte Google de la chaîne Brillance Home.',
            "Google affichera « Google n'a pas validé cette application » : Paramètres avancés → Continuer.",
            'Tu arriveras sur une page en erreur 404. C\'est normal.',
        ]);

        // urldecode : dans la barre d'adresse, les barres obliques du code sont écrites %2F
        $code = urldecode(trim((string) $io->ask('2. Colle la valeur qui suit code= dans la barre d\'adresse')));

        if ($code === '') {
            $io->error('Aucun code saisi.');

            return Command::FAILURE;
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            $io->error(sprintf('Google a refusé : %s — %s', $token['error'], $token['error_description'] ?? ''));

            return Command::FAILURE;
        }

        if (!isset($token['refresh_token'])) {
            $io->error("Google n'a pas renvoyé de jeton de rafraîchissement. Relance la commande.");

            return Command::FAILURE;
        }

        $io->success('Jeton obtenu.');
        $io->section('3. Colle cette ligne dans .env.local');
        $io->writeln('YOUTUBE_REFRESH_TOKEN=' . $token['refresh_token']);
        $io->newLine();
        $io->warning('Ce jeton vaut un accès complet à ta chaîne. Il ne doit jamais entrer dans Git.');

        return Command::SUCCESS;
    }
}