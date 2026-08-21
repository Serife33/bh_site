<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Form\Type\VichImageType;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('seoText', TextareaType::class, [
                'label' => 'Texte de référencement',
                'required' => false,
                'attr' => ['rows' => 8],
                'help' => "Paragraphe affiché en bas de la page catégorie. Décrit l'univers pour les moteurs de recherche.",
            ])
            ->add('metaTitle')
            ->add('metaDescription')
                        ->add('imageFile', VichImageType::class, [
                'label' => 'Image de la catégorie',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => false,
                'image_uri' => true,
                'delete_label' => 'Retirer l’image',
                'help' => "Affichée dans le rond de la page d'accueil. Facultative — sans image, le dégradé actuel reste.",
                'constraints' => [
                    new Assert\File(
                        maxSize: '50M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/heic',
                            'image/heif',
                        ],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP, HEIC.',
                        maxSizeMessage: 'Image trop lourde ({{ size }} {{ suffix }}). Maximum : {{ limit }} {{ suffix }}.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
