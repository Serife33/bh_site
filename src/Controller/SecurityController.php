<?php


namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name:'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // récupére la derniere erreur de connexion 
        $error = $authenticationUtils->getLastAuthenticationError();
        // récupere le denier email saisi 
        $lastUserName = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUserName,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name:'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Interceptée par la clé logout du pare-feu.');
    }

}