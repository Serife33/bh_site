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
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuoteType extends AbstractType
{
    private const FEE_OPTIONS = ['required' => false, 'empty_data' => '0', 'help_attr' => ['class' => 'resultat deux']] + QuoteLineType::PRICE_OPTIONS;

    public function __construct(private CustomerRepository $customerRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Cherché par le vendeur (quote/edit.html.twig, assets/devis.js), envoyé par son identifiant
            ->add('customer', HiddenType::class, ['error_bubbling' => false, 'invalid_message' => "Ce client n'existe plus dans le fichier."])
            ->add('lines', CollectionType::class, [
                'label' => 'Articles',
                'entry_type' => QuoteLineType::class,
                'prototype_data' => new QuoteLine(),   // une ligne ajoutée part de quantité 1 et remise 0, comme la première
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,   // passe par addLine()/removeLine(), qui relient la ligne au devis
                'error_bubbling' => false, // « Ajoutez au moins un article » s'affiche au-dessus des articles
            ])
            ->add('discountValue', NumberType::class, QuoteLineType::DISCOUNT_VALUE_OPTIONS + ['label' => 'Remise en %'])
            // Le prix final n'a de sens que sur un article
            ->add('discountType', EnumType::class, QuoteLineType::DISCOUNT_TYPE_OPTIONS + [
                'label' => "Remise sur l'ensemble des articles",
                'help' => 'Après les remises par article. Jamais sur la livraison ni le montage.',
                'choices' => [DiscountType::Percent, DiscountType::Amount],
            ])
            // Livraison ou retrait se choisit d'abord, en boutons ; un prix ne compte que pour une option choisie, vide : offerte.
            // Les aides des prix sont des résultats, réécrits pendant la saisie (assets/devis.js).
            ->add('delivery', ChoiceType::class, [
                'label' => 'Le client reçoit ses meubles',
                'choices' => ['Livraison à domicile' => true, 'Retrait au showroom' => false],
                'expanded' => true,
                'empty_data' => '0',
            ])
            ->add('deliveryFee', MoneyType::class, self::FEE_OPTIONS + ['label' => 'Prix de la livraison TTC', 'help' => 'Livraison offerte. Tapez un prix si elle est payante.'])
            ->add('assembly', CheckboxType::class, ['label' => 'Montage par nos soins', 'required' => false])
            ->add('assemblyFee', MoneyType::class, self::FEE_OPTIONS + ['label' => 'Prix du montage TTC', 'help' => 'Pas de montage.'])
            // L'acompte : le taux du devis, ou un autre montant convenu avec le client. Le choix n'est pas enregistré :
            // avec le taux, le montant (grisé, donc non envoyé) reste vide et le taux s'applique.
            ->add('depositMode', ChoiceType::class, [
                'mapped' => false,
                'choices' => ['taux' => 'taux', 'montant' => 'montant'],
                'expanded' => true,
                'data' => $builder->getData()?->getDepositAmount() === null ? 'taux' : 'montant',
            ])
            ->add('depositAmount', MoneyType::class, [
                'label' => "Montant de l'acompte TTC",
                'required' => false,
                'attr' => ['min' => '0.01', 'step' => '0.01'],   // pas de plafond ici : il dépend du total (DepositWithinTotal)
                'help' => 'Calculé sur le total TTC, livraison et montage compris.',
                'help_attr' => ['class' => 'resultat deux'],
            ] + QuoteLineType::PRICE_OPTIONS)
            ->add('leadTime', TextType::class, [
                'label' => 'Délai de livraison <span class="obl">(obligatoire)</span>',
                'label_html' => true,
                'help' => 'Imprimé sur le devis. Pour un retrait : quand les meubles seront prêts au showroom.',
                'attr' => ['maxlength' => 255],
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

        // Le taux choisi : un montant envoyé quand même (sans JavaScript) ne compte pas. Avant la validation (priorité 1).
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            if ($event->getForm()->get('depositMode')->getData() === 'taux') {
                $event->getData()->setDepositAmount(null);
            }
        }, 1);

        $builder->get('customer')->addModelTransformer(new CallbackTransformer(
            static fn (?Customer $customer) => $customer?->getId(),
            fn (?string $id) => $id ? ($this->customerRepository->findForQuote((int) $id) ?? throw new TransformationFailedException()) : null,
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quote::class,
        ]);
    }
}
