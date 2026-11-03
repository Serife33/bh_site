<?php

namespace App\Form;

use App\Entity\Customer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastName', TextType::class, ['label' => 'Nom'])
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'required' => false])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'required' => false])
            ->add('address', TextType::class, [
                'label' => 'Adresse de livraison',
                'required' => false,
                'attr' => ['placeholder' => 'Numéro et rue, code postal, ville'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Customer::class,
        ]);
    }
}
