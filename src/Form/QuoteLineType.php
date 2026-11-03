<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\QuoteLine;
use App\Enum\DiscountType;
use App\Repository\ProductRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Une ligne du bloc « Lignes du devis » de QuoteType. Choisir un produit ou une variante pré-remplit
// la ligne (assets/admin.js) : désignation, précision, prix et fabrication sur mesure restent modifiables.
// Les champs du produit, de la variante et du tissu ont leur gabarit : quote/_line_theme.html.twig.
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
            // Un champ de recherche, la liste des produits trouvés et l'identifiant du produit choisi
            ->add('product', TextType::class, [
                'label' => 'Produit du catalogue',
                'required' => false,
                'block_prefix' => 'quote_product',
                'invalid_message' => "Ce produit n'existe plus dans le catalogue.",
            ])
            // Les variantes du produit choisi seulement : sa taille et son prix sont recopiés dans la ligne
            ->add('variant', TextType::class, [
                'label' => 'Variante',
                'required' => false,
                'block_prefix' => 'quote_variant',
                'invalid_message' => "Cette variante n'existe plus dans le catalogue.",
            ])
            ->add('label', TextType::class, ['label' => 'Désignation'])
            // Les tissus, coloris et finitions du produit choisi, proposés à la frappe
            ->add('finish', TextType::class, [
                'label' => 'Tissu et coloris, ou finition',
                'required' => false,
                'block_prefix' => 'quote_finish',
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

        // Produit et variante arrivent par leur identifiant : le repository les retrouve, sinon le champ est refusé
        $builder->get('product')->addModelTransformer($this->byId($this->productRepository->findForQuoteLine(...)));
        $builder->get('variant')->addModelTransformer($this->byId($this->productRepository->findVariantForQuoteLine(...)));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuoteLine::class,
        ]);
    }

    private function byId(callable $find): CallbackTransformer
    {
        return new CallbackTransformer(
            static fn (Product|ProductVariant|null $entity) => $entity?->getId(),
            static fn (?string $id) => $id ? ($find((int) $id) ?? throw new TransformationFailedException()) : null,
        );
    }
}
