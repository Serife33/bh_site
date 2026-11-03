<?php

namespace App\Form;

use App\Entity\Fabric;
use App\Entity\ProductVariant;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Une ligne du bloc « Variantes » de ProductType. La position n'est pas saisie : elle suit l'ordre des lignes.
class ProductVariantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('size', TextType::class, [
                'label' => 'Taille',
                'required' => false,
                'attr' => ['placeholder' => '160x200'],
            ])
            ->add('fabric', EntityType::class, [
                'label' => 'Matière',
                'class' => Fabric::class,
                'required' => false,
                'placeholder' => '- Aucune -',
            ])
            ->add('initialPrice', MoneyType::class, ProductType::PRICE_OPTIONS + [
                'label' => 'Prix barré',
            ])
            ->add('actualPrice', MoneyType::class, ProductType::PRICE_OPTIONS + [
                'label' => 'Prix',
            ])
            ->add('stock', IntegerType::class, ProductType::STOCK_OPTIONS + [
                'label' => 'Stock',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductVariant::class,
            // « Ni taille ni matière » s'affiche sur sa ligne, au lieu de remonter en haut du formulaire
            'error_bubbling' => false,
        ]);
    }
}
