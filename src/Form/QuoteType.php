<?php

namespace App\Form;

use App\Entity\AdminUser;
use App\Entity\Customer;
use App\Entity\Quote;
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
            ->add('discountType', EnumType::class, QuoteLineType::DISCOUNT_TYPE_OPTIONS)
            ->add('pickup', CheckboxType::class, [
                'label' => 'Retrait au showroom (ni livraison ni montage)',
                'required' => false,
            ])
            ->add('deliveryFee', MoneyType::class, QuoteLineType::PRICE_OPTIONS + ['label' => 'Livraison TTC'])
            ->add('assemblyFee', MoneyType::class, QuoteLineType::PRICE_OPTIONS + ['label' => 'Montage TTC'])
            ->add('leadTime', TextType::class, [
                'label' => 'Délai de livraison',
                'required' => false,
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
