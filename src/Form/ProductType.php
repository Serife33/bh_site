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
use App\Repository\ProductRepository;
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
            'label' => 'Type de produit',
            'class' => ProductModular::class,
            // L'énumération stocke 'no'/'yes'/'module' — on traduit pour l'affichage.
            'choice_label' => fn (ProductModular $cas) => match ($cas) {
                ProductModular::No     => 'Produit simple',
                ProductModular::Yes    => 'Ensemble modulable (composé de modules)',
                ProductModular::Module => 'Module (élément d\'un ensemble)',
            },
        ])
        ->add('sideLr', EnumType::class, [
            'label' => 'Côté (pour un angle)',
            'class' => ProductSide::class,
            'choice_label' => fn (ProductSide $cas) => match ($cas) {
                ProductSide::None  => 'Sans objet',
                ProductSide::Left  => 'Gauche',
                ProductSide::Right => 'Droite',
            },
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
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'help' => 'Pour les produits en tissu. Les coloris viennent du nuancier de chaque tissu.',
        ])
        ->add('colors', EntityType::class, [
            'label' => 'Finitions',
            'class' => Color::class,
            'choice_label' => 'name',
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'help' => 'Pour les produits sans tissu : bois, laque, métal.',
        ])
        // Seo
        ->add('metaTitle', TextType::class, [
            'label' => 'Meta title (SEO)',
            'required' => false
        ])
        ->add('metaDescription', TextareaType::class, [
            'label' => 'Meta description (SEO)',
            'required' => false
        ])
        ->add('slug', TextType::class, [
            'label' => 'Adresse de la page',
            'required' => false,
            'empty_data' => '',
            'help' => "Laisse vide pour la générer depuis le nom. Attention : la modifier change l'adresse publique du produit.",
        ])
        ->add('position', IntegerType::class, [
            'label' => 'Position (ordre d\'affichage)'
        ])
        ->add('isActive', CheckboxType::class, [
            'label' => 'Produit visible sur le site',
            'required' => false
        ])
        ;

        // Le champ Modules n'a de sens que pour un ENSEMBLE modulable.
        // Un champ qu'on ne doit pas remplir ne s'affiche pas.
        $produitCourant = $builder->getData();

        if ($produitCourant !== null && $produitCourant->getIsModular() === ProductModular::Yes) {
            $builder->add('modules', EntityType::class, [
                'label' => 'Modules composant ce produit',
                'class' => Product::class,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => "Seuls les modules de la même famille sont proposés.",
                'query_builder' => function (ProductRepository $repo) use ($produitCourant) {
                    $qb = $repo->createQueryBuilder('p')
                        ->andWhere('p.isModular = :module')
                        ->setParameter('module', ProductModular::Module)
                        ->andWhere('p != :courant')
                        ->setParameter('courant', $produitCourant)
                        ->orderBy('p.name', 'ASC');

                    if ($produitCourant->getFamily() !== null) {
                        $qb->andWhere('p.family = :famille')
                           ->setParameter('famille', $produitCourant->getFamily());
                    }

                    return $qb;
                },
            ]);
        }   
    }

    // Relie ce formulaire à l'entité Product.
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class
        ]);
    }

}