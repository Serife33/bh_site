<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

// Les listes rangées au glisser-déposer (assets/admin.js) envoient leurs identifiants dans le nouvel ordre :
// même contrôle et même réponse pour toutes, seule la table renumérotée change.
trait OrderSaveTrait
{
    private function saveOrder(Request $request, string $tokenId, callable $updatePositions): JsonResponse
    {
        $payload = $request->getPayload();

        if (!$this->isCsrfTokenValid($tokenId, $payload->getString('_token'))) {
            return new JsonResponse(['ok' => false, 'message' => 'Jeton invalide.'], 403);
        }

        // Les identifiants dans leur nouvel ordre, nettoyés
        $ids = array_values(array_filter(array_map('intval', $payload->all('ids'))));

        $updatePositions($ids);

        return new JsonResponse(['ok' => true, 'total' => count($ids)]);
    }
}
