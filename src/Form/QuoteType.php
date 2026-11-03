<?php

namespace App\Form;

use App\Entity\AdminUser;
use App\Entity\Customer;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\DiscountType;
use App\Enum\QuoteStatus;
use App\Repository\AdminUserRepository;
use App\Repository\CustomerRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuoteType extends AbstractType
{
    private const FEE_OPTIONS = ['required' => false, 'empty_data' => '0'] + QuoteLineType::PRICE_OPTIONS;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', EntityType::class, [
                'label' => 'Client',
                'class' => Customer::class,
                'placeholder' => '- Choisir -',
                'query_builder' => fn (CustomerRepository $repository) => $repository->createSortedQueryBuilder(),
                'choice_label' => fn (Customer $customer) => $customer . ($customer->getPhone() ? ' · ' . $customer->getPhone() : ''),
            ])
            ->add('lines', CollectionType::class, [
                'label' => 'Lignes du devis',
                'entry_type' => QuoteLineType::class,
                'prototype_data' => new QuoteLine(),   // une ligne ajoutée part de quantité 1 et remise 0, comme la première
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,   // passe par addLine()/removeLine(), qui relient la ligne au devis
                'error_bubbling' => false, // « Ajoutez au moins une ligne » s'affiche au-dessus du bloc
                'help' => "Prix TTC, tels qu'affichés sur le site. La remise d'une ligne porte sur le total de la ligne.",
            ])
            ->add('discountValue', NumberType::class, QuoteLineType::DISCOUNT_VALUE_OPTIONS + [
                'label' => 'Remise globale',
                'help' => "Sur le total des articles, après les remises de ligne. Jamais sur la livraison ni le montage.",
            ])
            // Le prix final n'a de sens que sur une ligne
            ->add('discountType', EnumType::class, QuoteLineType::DISCOUNT_TYPE_OPTIONS + ['choices' => [DiscountType::Percent, DiscountType::Amount]])
            // Deux options : leur prix ne compte que si elles sont cochées. Prix vide : offert.
            ->add('delivery', CheckboxType::class, ['label' => 'Livraison (sinon retrait au showroom)', 'required' => false])
            ->add('deliveryFee', MoneyType::class, self::FEE_OPTIONS + ['label' => 'Prix de la livraison TTC', 'help' => 'Vide ou 0 : livraison offerte.'])
            ->add('assembly', CheckboxType::class, ['label' => 'Montage', 'required' => false])
            ->add('assemblyFee', MoneyType::class, self::FEE_OPTIONS + ['label' => 'Prix du montage TTC', 'help' => 'Vide ou 0 : montage offert.'])
            ->add('depositAmount', MoneyType::class, [
                'label' => 'Acompte TTC',
                'required' => false,
                'attr' => ['min' => '0.01', 'step' => '0.01'],   // pas de plafond ici : il dépend du total (DepositWithinTotal)
                'help' => 'Vide : le pourcentage habituel du total. Un montant saisi le remplace.',
            ] + QuoteLineType::PRICE_OPTIONS)
            ->add('leadTime', TextType::class, [
                'label' => 'Délai de livraison',
                'attr' => ['placeholder' => "6 à 10 semaines après réception de l'acompte"],
            ])
            ->add('validUntil', DateType::class, [
                'label' => "Valable jusqu'au",
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => Quote::VALIDITY_DAYS . ' jours après la date du devis, proposé à la création.',
            ])
            ->add('seller', EntityType::class, [
                'label' => 'Vendeur',
                'class' => AdminUser::class,
                'query_builder' => fn (AdminUserRepository $repository) => $repository->createSellersQueryBuilder(),
                'choice_label' => 'email',
            ])
            ->add('status', EnumType::class, [
                'label' => 'Statut',
                'class' => QuoteStatus::class,
                'choice_label' => 'label',
                'help' => 'Accepté : le devis est signé et ne se modifie plus. Envoyé passe seul à Expiré après sa date de validité.',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes internes',
                'required' => false,
                'attr' => ['placeholder' => "Visibles seulement dans l'administration, jamais sur le devis imprimé."],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quote::class,
        ]);
    }
}
