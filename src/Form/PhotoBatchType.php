<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Envoi groupé de photos : un seul champ fichier multiple, et un texte alternatif par fichier.
 *
 * Pas de data_class : ce formulaire ne correspond à aucune entité. Le JavaScript crée un champ
 * alt par fichier sélectionné, et le contrôleur apparie fichiers et textes par leur index.
 */
class PhotoBatchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('images', FileType::class, [
                'label' => 'Photos',
                'multiple' => true,
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Choisis au moins une photo.'),
                    // All : la contrainte s'applique à chaque fichier du tableau, pas au tableau
                    new Assert\All([
                        new Assert\File(
                            maxSize: '50M',
                            mimeTypes: [
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'image/heic',   // iPhone — converti en JPEG à l'upload
                                'image/heif',
                            ],
                            mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP, HEIC.',
                            maxSizeMessage: 'Image trop lourde ({{ size }} {{ suffix }}). Maximum : {{ limit }} {{ suffix }}.',
                        ),
                    ]),
                ],
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp,image/heic,image/heif'],
            ])
            ->add('alts', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'required' => false,
                    'empty_data' => null,
                ],
                'allow_add' => true,   // les champs viennent du navigateur, pas du serveur
                'prototype' => false,  // le JS construit son propre balisage, vignette comprise
                'required' => false,
                'label' => false,
            ]);
    }
}
