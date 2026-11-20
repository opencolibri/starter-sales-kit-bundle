<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A shop is created from a dataset: its catalog and images are copied into the shop.
 */
final class ShopCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dataset', ChoiceType::class, [
                'label' => 'Dataset',
                'choices' => $options['datasets'],
                'placeholder' => 'Choose a dataset',
                'constraints' => [new NotBlank()],
            ])
            ->add('name', TextType::class, [
                'label' => 'Shop name',
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optional — the dataset one if empty)',
                'required' => false,
            ])
        ;

        ShopImageFields::addTo($builder, requireLogo: false, keepsExistingLogo: false);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('datasets')
            ->setAllowedTypes('datasets', 'array')
        ;
    }
}
