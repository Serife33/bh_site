<?php
namespace App\Form;

use App\Entity\Media;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Component\Validator\Constraints as Assert;

class MediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('imageFile', VichImageType::class, [
                'label' => 'Photo',
                'allow_delete' => false, // la suppression passe par notre bouton dédié
                'download_uri' => false, // pas de lien de téléchargement
                'image_uri' => true,     // affiche un aperçu de l'image existante
                'constraints' => [
                    new Assert\Image(
                        maxSize: '8M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP. Convertis ton HEIC en JPEG.',
                        maxSizeMessage: 'Image trop lourde ({{ size }} {{ suffix }}). Maximum : {{ limit }} {{ suffix }}.',
                    ),
                ],
            ])
            ->add('alt', TextType::class, [
                'label' => 'Texte alternatif',
                'required' => false,
                'help' => "Décrit l'image pour l'accessibilité et le référencement.",
            ])
            ->add('isMain', CheckboxType::class, [
                'label' => 'Photo principale du produit',
                'required' => false, // case à cocher = jamais required
            ])
            ->add('position', IntegerType::class, [
                'label' => "Ordre d'affichage",
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Media::class,
        ]);
    }
}