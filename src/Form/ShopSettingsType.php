<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Settings of a shop created from a dataset: its catalog comes from the dataset, so it is not fetched or built again.
 */
final class ShopSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Shop name',
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
        ;

        ShopImageFields::addTo($builder, requireLogo: false);

        $builder->add('save', SubmitType::class, [
            'label' => 'Save settings',
        ]);
    }
}
