<?php

namespace App\Form;

use App\Entity\Fabric;
use App\Entity\FabricColor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Form\Type\VichImageType;

class FabricColorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la couleur',
                'attr' => ['placeholder' => 'Moutarde'],
            ])
            ->add('hex', ColorType::class, [
                'label' => 'Couleur',
                'help' => 'Sert de pastille de repli tant qu’il n’y a pas de photo.',
            ])
            ->add('imageFile', VichImageType::class, [
                'label' => 'Photo macro',
                'required' => false,   // ⭐ facultatif : le front replie sur la pastille
                'allow_delete' => true,
                'download_uri' => false,
                'image_uri' => true,
                'help' => 'Facultatif. Sans photo, la pastille de couleur est affichée.',
                'constraints' => [
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
                ],
                'delete_label' => 'Retirer la photo',
            ])
            ->add('position', IntegerType::class, [
                'label' => "Ordre d'affichage",
                'attr' => ['min' => 1],
                'constraints' => [
                    new Assert\Positive(
                        message: "L'ordre doit être un nombre positif (1 ou plus).",
                    ),
                ],
            ])
            ;
        // Le tissu ne s'affiche que dans le formulaire autonome.
        // Imbriqué dans la page d'un tissu, il est déjà connu.
        if ($options['show_fabric']) {
        $builder->add('fabric', EntityType::class, [
            'class' => Fabric::class,
            'label' => 'Tissu',
            'placeholder' => 'Choisir un tissu',
        ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FabricColor::class,
            'show_fabric' => true,   // true = formulaire autonome ; false = imbriqué dans le tissu
        ]);
        $resolver->setAllowedTypes('show_fabric', 'bool');
    }
}