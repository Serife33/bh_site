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


use function PHPUnit\Framework\isTrue;

class MediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void // array : options tableau des options du formulaire
    {
        $builder
            ->add('imageFile', VichImageType::class, [
                'label' => 'Photo',
                'required' => $options['require_image'], // true en création 
                'allow_delete' => false, // la suppression passe par le bouton dédié
                'download_uri' => false, // pas de lien de téléchargement
                'image_uri' => true,     // affiche un aperçu de l'image existante
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
            ])
            ->add('alt', TextType::class, [
                'label' => 'Texte alternatif',
                'required' => true,
                'help' => "Décrit l'image pour l'accessibilité et le référencement.",
                'constraints' => [
                    new Assert\NotBlank(
                        message: "Le texte alternatif est obligatoire (accessibilité et référencement).",
                    ),
                ],
            ])
            ->add('isMain', CheckboxType::class, [
                'label' => 'Photo principale du produit',
                'required' => false, // case à cocher = jamais required
            ])
            ->add('position', IntegerType::class, [
                'label' => "Ordre d'affichage",
                'attr' => ['min' => 1],
                'constraints' => [
                    new Assert\Positive(
                        message: " L'ordre doit être un nombre positif."
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Media::class,
            'require_image' => true,  // true a la création, false en edit 
        ]);
        $resolver->setAllowedTypes('require_image', 'bool');
    }
}