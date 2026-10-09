<?php

namespace App\Service;

use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Envoie une vidéo sur la chaîne YouTube du magasin et renvoie son identifiant.
 *
 * L'envoi est « reprenable » : le fichier part par tranches au lieu d'une seule grosse
 * requête. Sur un hébergement mutualisé, c'est ce qui évite de saturer la mémoire de PHP.
 */
class YouTubeUploader
{
    // Le protocole de Google impose un multiple de 256 Ko. 1 Mo est le compromis habituel.
    private const CHUNK_SIZE = 1024 * 1024;

    private const SCOPES = [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.force-ssl',
    ];

    public function __construct(
        #[Autowire('%env(YOUTUBE_CLIENT_ID)%')] private string $clientId,
        #[Autowire('%env(YOUTUBE_CLIENT_SECRET)%')] private string $clientSecret,
        #[Autowire('%env(YOUTUBE_REFRESH_TOKEN)%')] private string $refreshToken,
    ) {
    }

    /**
     * @param string $privacy 'public', 'unlisted' ou 'private'
     *
     * @return string l'identifiant YouTube de la vidéo créée
     */
    public function upload(string $filePath, string $title, string $description = '', string $privacy = 'public'): string
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException(sprintf('Fichier introuvable : %s', $filePath));
        }

        $client = $this->createClient();
        $youtube = new YouTube($client);

        $snippet = new VideoSnippet();
        $snippet->setTitle(mb_substr($title, 0, 100));              // limite imposée par YouTube
        $snippet->setDescription(mb_substr($description, 0, 5000));
        $snippet->setCategoryId('26');                              // « Astuces et style »

        $status = new VideoStatus();
        $status->setPrivacyStatus($privacy);

        $video = new Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // setDefer : la requête est construite mais pas envoyée. C'est MediaFileUpload
        // qui l'enverra, tranche par tranche.
        $client->setDefer(true);
        $request = $youtube->videos->insert('snippet,status', $video);

        $media = new MediaFileUpload($client, $request, 'video/*', null, true, self::CHUNK_SIZE);
        $media->setFileSize(filesize($filePath));

        $handle = fopen($filePath, 'rb');
        $result = false;

        try {
            // nextChunk renvoie false tant qu'il reste des tranches, puis la vidéo créée.
            while ($result === false && !feof($handle)) {
                $result = $media->nextChunk(fread($handle, self::CHUNK_SIZE));
            }
        } finally {
            fclose($handle);
            $client->setDefer(false);   // on remet le client en mode normal, quoi qu'il arrive
        }

        $videoId = $result instanceof Video ? $result->getId() : ($result['id'] ?? null);

        if (!$videoId) {
            throw new \RuntimeException("L'envoi s'est interrompu : YouTube n'a pas renvoyé d'identifiant.");
        }

        return $videoId;
    }

    // Remplace la miniature choisie par YouTube par une image à soi. JPEG ou PNG, 2 Mo maximum.
    public function setThumbnail(string $videoId, string $imagePath): void
    {
        if (!is_file($imagePath)) {
            throw new \RuntimeException(sprintf('Image introuvable : %s', $imagePath));
        }

        $youtube = new YouTube($this->createClient());

        $youtube->thumbnails->set($videoId, [
            'data' => file_get_contents($imagePath),
            'mimeType' => mime_content_type($imagePath),
            'uploadType' => 'media',
        ]);
    }

    // Un client authentifié par le jeton de rafraîchissement : aucune intervention humaine.
    private function createClient(): Client
    {
        if ($this->refreshToken === '') {
            throw new \RuntimeException('YOUTUBE_REFRESH_TOKEN est vide. Lance app:youtube-authorize.');
        }

        $client = new Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setScopes(self::SCOPES);

        $token = $client->fetchAccessTokenWithRefreshToken($this->refreshToken);

        if (isset($token['error'])) {
            throw new \RuntimeException(sprintf(
                'Authentification YouTube refusée : %s',
                $token['error_description'] ?? $token['error'],
            ));
        }

        return $client;
    }
}