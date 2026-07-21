<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Color;
use App\Entity\Fabric;
use App\Entity\Family;
use App\Entity\Product;
use App\Entity\SubCategory;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('name',TextType::class, [
            'label' => 'Nom du produit',
        ])
        ->add('description', TextareaType::class, [
            'label' => 'Description',
            'required' => false,
        ])
        ->add('dimension', TextareaType::class, [
            'label' => 'Dimensions',
            'required' => false

        ])
        ->add('initialPrice', MoneyType::class, [
            'label' => 'Prix initial',
            'currency' => 'EUR',  // affiche le symbole € 
        ])
        ->add('actualPrice', MoneyType::class, [
            'label' => 'Prix actuel (promo si < prix initial)',
            'currency' => 'EUR',
        ])
        ->add('stock', IntegerType::class, [
            'label' => 'Stock (0 = sur commande)',
        ])
        ->add('isCustomMade', CheckboxType::class, [
            'label' => 'Fabrication sur mesure',
            'required' => false,
        ])
        ->add('isModular', EnumType::class, [
            'label' => 'Modulable',
            'class' => ProductModular::class // la class enum 
        ])
        ->add('sideLr', EnumType::class, [
            'label' => 'Côté (angle)',
            'class' => ProductSide::class
        ])
        ->add('leadMinWeeks', IntegerType::class, [
            'label' => 'Délai mini (semaines)',
            'required' => false // nullable en base 
        ])
        ->add('leadMaxWeeks', IntegerType::class, [
            'label' => 'Délai maxi (semaines)',
            'required' => false
        ])

        // Relations 
        ->add('category', EntityType::class, [
            'label' => 'Categorie',
            'class' => Category::class,
            'choice_label' => 'name',
            'placeholder' => '- Choisir -'
        ])
        ->add('family', EntityType::class, [
            'label' => 'Famille', 
            'class' => Family::class,
            'choice_label' => 'name',
            'placeholder' => '- Aucune -',
            'required' => false,
        ])
        ->add('subCategories', EntityType::class, [
            'label' => 'Types de produit',
            'class' => SubCategory::class,
            'choice_label' => 'name',
            'multiple' => true,  // on peut en choisir PLUSIEURS
            'expanded' => true,   // affiche des cases à cocher (au lieu d'une liste)
            'required' => false, 
        ])
        ->add('fabrics', EntityType::class, [
            'label' => 'Tissus disponibles',
            'class' => Fabric::class,
            'choice_label' => 'name',
            'multiple' => true,
            'expanded' => true,
            'required' => false
        ])
        ->add('colors', EntityType::class, [
            'label' => 'Couleurs disponibles',
            'class' => Color::class,
            'choice_label' => 'name',
            'multiple' => true,
            'expanded' => true,
            'required' => false,
        ])
        ->add('modules', EntityType::class, [
            'label' => 'Modules composant ce produit',
            'class' => Product::class,
            'choice_label' => 'name', 
            'multiple' => true,
            'expanded' => false,
            'required' => false

        ])

        // Seo
        ->add('slug', TextType::class, [
            'label' => 'slug',
            'help' => 'En minuscules, sans accent ni espace. Ex : canape-oslo-3-places' // ffiche un petit texte d'aide sous le champ. Très utile en back-office pour rappeler une règle de saisie
        ])
        ->add('metaTitle', TextType::class, [
            'label' => 'Meta title (SEO)',
            'required' => false
        ])
        ->add('metaDescription', TextareaType::class, [
            'label' => 'Meta description (SEO)',
            'required' => false
        ])
        ->add('position', IntegerType::class, [
            'label' => 'Position (ordre d\'affichage)'
        ])
        ->add('isActive', CheckboxType::class, [
            'label' => 'Produit visible sur le site',
            'required' => false
        ])
        ;
    }

    // Relie ce formulaire à l'entité Product.
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class
        ]);
    }

}