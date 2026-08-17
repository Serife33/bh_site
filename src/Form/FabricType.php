<?php

namespace App\Form;

use App\Entity\Fabric;
use Symfony\Component\Form\AbstractType;
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Fabric::class,
        ]);
    }
}