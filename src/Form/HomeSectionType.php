<?php

namespace App\Form;

use App\Entity\HomeSection;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HomeSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'empty_data' => '',   // vidé en modification : « obligatoire » s'affiche au lieu d'une erreur 500
                'attr' => ['placeholder' => 'Nouveautés'],
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'Sous-titre',
                'required' => false,
                'attr' => ['placeholder' => 'Nos dernières pièces arrivées'],
            ])
            ->add('isVisible', CheckboxType::class, [
                'label' => "Section visible sur l'accueil",
                'required' => false,
            ])
            // Comme les modules d'un ensemble (ProductType) : des pastilles à cocher.
            // HomeSection::addProduct() et removeProduct() tiennent à jour les lignes de liaison.
            ->add('products', EntityType::class, [
                'label' => 'Produits de la section',
                'class' => Product::class,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => "Un produit coché se range à la fin : l'ordre se règle sur la page de la section. Un produit masqué n'apparaît pas sur l'accueil.",
                'query_builder' => fn (ProductRepository $repo) => $repo->createForHomeSectionQueryBuilder(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HomeSection::class,
        ]);
    }
}
