<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\SubCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('metaTitle', null, [
                'label' => 'Titre pour Google',
                'required' => false,
            ])
            ->add('metaDescription', null, [
                'label' => 'Description pour Google',
                'required' => false,
            ])
            ->add('seoText', TextareaType::class, [
                'label' => 'Texte de référencement',
                'required' => false,
                'attr' => ['rows' => 8],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SubCategory::class,
        ]);
    }
}
