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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Un article du devis (QuoteType), sa carte : quote/_article.html.twig, ses champs particuliers : quote/_form_theme.html.twig.
// Un seul nom à donner : « Article » (label). Le choisir dans la liste du catalogue lie le produit et sa variante,
// envoyés par identifiant dans des champs cachés ; sans choix, c'est une ligne libre. Prix, précisions et
// fabrication sur mesure restent modifiables (assets/devis.js).
class QuoteLineType extends AbstractType
{
    // Partagées avec la remise globale de QuoteType. Montants en chaînes : ils vont tels quels au calcul bcmath.
    // Le type de remise se choisit d'abord, en boutons ; « remise » est le gabarit du groupe (type, puis valeur).
    public const DISCOUNT_VALUE_OPTIONS = ['scale' => 2, 'html5' => true, 'input' => 'string', 'block_prefix' => 'valeur_remise',
        'help' => 'Aucune remise', 'help_attr' => ['class' => 'resultat deux'], 'attr' => ['min' => 0, 'max' => 50000, 'step' => '0.01']];
    public const DISCOUNT_TYPE_OPTIONS = ['class' => DiscountType::class, 'choice_label' => 'label', 'expanded' => true, 'block_prefix' => 'remise'];
    public const PRICE_OPTIONS = ProductType::PRICE_OPTIONS + ['input' => 'string'];

    public function __construct(private ProductRepository $productRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Article <span class="obl">(obligatoire)</span>',
                'label_html' => true,
                'attr' => ['maxlength' => 255],
            ])
            ->add('product', HiddenType::class, ['invalid_message' => "Ce produit n'existe plus dans le catalogue."])
            ->add('variant', HiddenType::class, ['invalid_message' => "Cette variante n'existe plus dans le catalogue."])
            ->add('finish', TextType::class, [
                'label' => 'Tissu et coloris, ou finition',
                'required' => false,
                'block_prefix' => 'finition',   // avec les propositions du produit choisi
                'help' => 'Choisissez dans les propositions, ou tapez librement.',
                'attr' => ['maxlength' => 255],
            ])
            ->add('details', TextareaType::class, [
                'label' => 'Précisions',
                'required' => false,
                'help' => "Imprimées sous le nom de l'article. Pour un produit du catalogue, notez ici ce qui change : angle à droite, dimensions spéciales.",
                'attr' => ['rows' => 2, 'maxlength' => 1000],
            ])
            ->add('quantity', IntegerType::class, ['label' => 'Quantité', 'attr' => ['min' => 1, 'max' => 999, 'inputmode' => 'numeric']])
            // L'aide est un résultat, réécrit pendant la saisie : le prix du catalogue, ou pourquoi il n'y en a pas
            ->add('unitPrice', MoneyType::class, self::PRICE_OPTIONS + [
                'label' => 'Prix unitaire TTC <span class="obl">(obligatoire)</span>',
                'label_html' => true,
                'help' => 'Rempli par le produit choisi.',
                'help_attr' => ['class' => 'resultat deux'],
            ])
            ->add('discountValue', NumberType::class, self::DISCOUNT_VALUE_OPTIONS + ['label' => 'Remise en %'])
            ->add('discountType', EnumType::class, self::DISCOUNT_TYPE_OPTIONS + ['label' => 'Remise sur cet article'])
            ->add('customMade', CheckboxType::class, [
                'label' => 'Fabriqué selon les choix du client (pas de droit de rétractation)',
                'required' => false,
                'help' => "Cochée d'office pour un produit fabriqué à la commande. Imprimée sur le devis.",
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
