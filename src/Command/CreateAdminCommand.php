<?php

namespace App\Command;

use App\Entity\AdminUser; // notre entité 
use Doctrine\ORM\EntityManagerInterface; // outil Doctrine pour enregistrer en BDD 
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface; // outil des hashage des mots de passe 

#[AsCommand( // AsCommand est une étiquette qui déclare cette commande comme une commande console 
    name: 'app:create-admin', // le nom de la commande pour créer des admins
    description: 'Crée un compte administrateur',
)]
class CreateAdminCommand extends Command
{
    // constructeur : réclame tous les outils nécessaires 
    // private : pour encapsuler mes dépendances (outils internes) et promouvoir parametres en propriété
    public function __construct(
        private EntityManagerInterface $em,   // pour parler a la base de données 
        private UserPasswordHasherInterface $hasher, // pour hasher le mot de passe 
    ) 
    {
        parent::__construct();
    }

    // 
    protected function execute(InputInterface $input, OutputInterface $output): int // ce que la commande fait 
    {
        // SymfonyStyle un assistant pour afficher joliment et poser des questions dans le terminal 
        $io = new SymfonyStyle($input, $output);

        // les infos qu'On demande a l'utilisateur, en intéractif
        $email = $io->ask("Email de l'admin"); 
        $password = $io->askHidden("Mot de passe"); // saisie masquée (sécurité)

        // on construit le nouvel admin 
        $admin = new AdminUser();   // un objet AdminUser vide
        $admin->setEmail($email);    // on lui met l'email saisi 
        $admin->setRoles(['ROLE_ADMIN']);  // on lui donne le rôle 

        // hachage du mot de passe
        $admin->setPassword($this->hasher->hashPassword($admin, $password));  

        // on enregistre en base 
        $this->em->persist($admin);
        $this->em->flush();

        $io->success('Admin créé : ' . $email);


        return Command::SUCCESS; // on signale au terminal que tout s'est bien passé
    }
}
