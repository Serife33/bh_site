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
            ->add('fabric', EntityType::class, [
                'class' => Fabric::class,
                'label' => 'Tissu',
                'placeholder' => 'Choisir un tissu',
            ])
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
                    new Assert\Image(
                        maxSize: '8M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP. Convertis ton HEIC en JPEG.',
                        maxSizeMessage: 'Image trop lourde ({{ size }} {{ suffix }}). Maximum : {{ limit }} {{ suffix }}.',
                    ),
                ],
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FabricColor::class,
        ]);
    }
}