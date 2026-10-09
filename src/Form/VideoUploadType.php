<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envoi d'une vidéo. Pas de data_class : le fichier vidéo part sur YouTube et ne
 * reste jamais sur le serveur, seule l'image de couverture devient une ligne de media.
 *
 * Le titre et la description sont préremplis par le contrôleur depuis le produit.
 */
class VideoUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('video', FileType::class, [
                'label' => 'Fichier vidéo',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Choisis une vidéo.'),
                    new Assert\File(
                        maxSize: '60M',
                        mimeTypes: ['video/mp4', 'video/quicktime', 'video/webm'],
                        mimeTypesMessage: 'Formats acceptés : MP4, MOV, WebM.',
                        maxSizeMessage: 'Vidéo trop lourde ({{ size }} {{ suffix }}). Maximum : {{ limit }} {{ suffix }}.',
                    ),
                ],
                'attr' => ['accept' => 'video/mp4,video/quicktime,video/webm'],
                'help' => "MP4, MOV ou WebM, 60 Mo maximum.",
            ])
            ->add('cover', FileType::class, [
                'label' => 'Image de couverture',
                'required' => false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG.',
                    ),
                ],
                'attr' => ['accept' => 'image/jpeg,image/png'],
                'help' => "Facultative, et pour le site uniquement. Laissée vide, c'est la photo principale du produit qui sert de couverture.",
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre sur YouTube',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre est obligatoire.'),
                    new Assert\Length(max: 100, maxMessage: 'YouTube limite le titre à {{ limit }} caractères.'),
                ],
                'help' => "100 caractères maximum. C'est le premier critère de la recherche YouTube.",
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description sur YouTube',
                'required' => false,
                'constraints' => [
                    new Assert\Length(max: 5000, maxMessage: 'YouTube limite la description à {{ limit }} caractères.'),
                ],
                'attr' => ['rows' => 12],
                'help' => "Indexée par YouTube et par Google. Le magasin, l'adresse et le site sont déjà en bas.",
            ]);
    }
}
