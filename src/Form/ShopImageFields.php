<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Form;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ShopImageFields
{
    public static function addTo(FormBuilderInterface $builder, bool $requireLogo = true, bool $keepsExistingLogo = true): void
    {
        $imageFile = new File(
            maxSize: '5M',
        );

        $logoConstraints = $requireLogo ? [new NotBlank(), $imageFile] : [$imageFile];

        $builder
            ->add('imageLogo', FileType::class, [
                'label' => match (true) {
                    $requireLogo => 'Logo',
                    $keepsExistingLogo => 'Logo (optional — keeps existing if empty)',
                    default => 'Logo (optional)',
                },
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/*'],
                'constraints' => $logoConstraints,
            ])
            ->add('imageHeader', FileType::class, [
                'label' => 'Hero image (optional)',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/*'],
                'constraints' => [$imageFile],
            ])
            ->add('imageInterstice', FileType::class, [
                'label' => 'Interstice image (optional)',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/*'],
                'constraints' => [$imageFile],
            ])
        ;
    }
}
