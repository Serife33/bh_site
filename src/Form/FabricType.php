<?php

namespace App\Form;

use App\Entity\Fabric;
use App\Entity\FabricColor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FabricType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Type de tissu',
                'attr' => ['placeholder' => 'Chenille'],
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'required' => false,
                'attr' => ['placeholder' => 'GENOVA'],
                'help' => 'La référence du fournisseur. Affichée entre parenthèses : « Chenille (GENOVA) ».',
            ])
            ->add('supplier', TextType::class, [
                'label' => 'Fournisseur',
                'required' => false,
            ])
            ->add('fabricColors', CollectionType::class, [
                'entry_type' => FabricColorType::class,
                'entry_options' => ['show_fabric' => false],   // on est déjà sur le tissu
                'allow_add' => true,        // autorise l'ajout de lignes
                'allow_delete' => true,     // autorise la suppression de lignes
                'by_reference' => false,    // ⭐ force le passage par addFabricColor()/removeFabricColor()
                'label' => 'Nuancier',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Fabric::class,
        ]);
    }
}