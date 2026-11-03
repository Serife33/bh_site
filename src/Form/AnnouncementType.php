<?php

namespace App\Form;

use App\Entity\Announcement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnnouncementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('text', TextType::class, [
                'label' => 'Message',
                'attr' => ['maxlength' => 120, 'placeholder' => 'Livraison partout en France'],
                'help' => '120 caractères maximum.',
            ])
            ->add('link', TextType::class, [
                'label' => 'Lien',
                'required' => false,
                'attr' => ['placeholder' => '/offres'],
                'help' => "Facultatif. Une page du site (/offres) ou une adresse en https:// (ouverte dans un nouvel onglet).",
            ])
            ->add('isPromo', CheckboxType::class, [
                'label' => 'Mettre en avant (promo, affiché en premier)',
                'required' => false,
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Début',
                'required' => false,
                'input' => 'datetime_immutable',
                'help' => 'Facultatif. Sans dates, le message est permanent. Les jours de début et de fin sont inclus.',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Fin',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('position', IntegerType::class, [
                'label' => "Ordre d'affichage",
                'attr' => ['min' => 1],
            ])
            ->add('isVisible', CheckboxType::class, [
                'label' => 'Message visible sur le site',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Announcement::class,
        ]);
    }
}
