<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\QuoteLine;
use App\Enum\DiscountType;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Une ligne du bloc « Lignes du devis » de QuoteType. Choisir un produit ou une variante pré-remplit
// la ligne (assets/admin.js) : désignation, précision, prix et fabrication sur mesure restent modifiables.
class QuoteLineType extends AbstractType
{
    // Partagées avec la remise globale de QuoteType. Montants en chaînes : ils vont tels quels au calcul bcmath.
    public const DISCOUNT_VALUE_OPTIONS = ['scale' => 2, 'html5' => true, 'input' => 'string', 'attr' => ['min' => 0, 'max' => 50000, 'step' => '0.01']];
    public const DISCOUNT_TYPE_OPTIONS = ['label' => 'Type de remise', 'class' => DiscountType::class, 'choice_label' => 'symbol'];
    public const PRICE_OPTIONS = ProductType::PRICE_OPTIONS + ['input' => 'string'];

    public function __construct(private ProductRepository $productRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'label' => 'Produit du catalogue',
                'class' => Product::class,
                'required' => false,
                'placeholder' => 'Ligne libre (hors catalogue)',
                'query_builder' => fn (ProductRepository $repository) => $repository->createForQuoteQueryBuilder(),
                'choice_attr' => fn (Product $product) => [
                    'data-prix' => $product->getActualPrice(),
                    'data-sur-mesure' => (int) $product->isCustomMade(),
                ],
            ])
            // Une aide à la saisie, pas une donnée de la ligne : sa taille et son prix y sont recopiés
            ->add('variant', EntityType::class, [
                'label' => 'Variante',
                'class' => ProductVariant::class,
                'mapped' => false,
                'required' => false,
                'placeholder' => '- Aucune -',
                'query_builder' => $this->productRepository->createVariantsForQuoteQueryBuilder(),
                'group_by' => fn (ProductVariant $variant) => $variant->getProduct()->getName(),
                'choice_label' => fn (ProductVariant $variant) => self::describe($variant) . ' : ' . $variant->getActualPrice() . ' €',
                'choice_attr' => fn (ProductVariant $variant) => [
                    'data-produit' => $variant->getProduct()->getId(),
                    'data-prix' => $variant->getActualPrice(),
                    'data-precision' => self::describe($variant),
                ],
            ])
            ->add('label', TextType::class, ['label' => 'Désignation'])
            ->add('finish', TextType::class, [
                'label' => 'Tissu et coloris, ou finition',
                'required' => false,
                'attr' => ['list' => 'finitions'],   // les coloris du nuancier, proposés à la frappe (quote/_form.html.twig)
            ])
            ->add('details', TextareaType::class, [
                'label' => "Précisions (taille, sur-mesure, sens de l'angle…)",
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('quantity', IntegerType::class, ['label' => 'Qté', 'attr' => ['min' => 1, 'max' => 999]])
            ->add('unitPrice', MoneyType::class, self::PRICE_OPTIONS + ['label' => 'Prix unitaire TTC'])
            ->add('discountValue', NumberType::class, self::DISCOUNT_VALUE_OPTIONS + ['label' => 'Remise'])
            ->add('discountType', EnumType::class, self::DISCOUNT_TYPE_OPTIONS)
            // Affiché seulement, rempli par le calcul en direct : le serveur recalcule tout à l'enregistrement
            ->add('total', TextType::class, ['label' => 'Total TTC', 'mapped' => false, 'disabled' => true])
            ->add('customMade', CheckboxType::class, [
                'label' => 'Fabriqué selon les choix du client (pas de droit de rétractation)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuoteLine::class,
        ]);
    }

    // « Taille 160x200, matière Céramique (Premium) »
    private static function describe(ProductVariant $variant): string
    {
        return ucfirst(implode(', ', array_filter([
            $variant->getSize() ? 'taille ' . $variant->getSize() : null,
            $variant->getFabric() ? 'matière ' . $variant->getFabric() : null,
        ])));
    }
}
