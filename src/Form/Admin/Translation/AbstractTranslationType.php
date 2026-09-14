<?php

declare(strict_types=1);

namespace App\Form\Admin\Translation;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

abstract class AbstractTranslationType extends AbstractType
{
    protected function addMeta(FormBuilderInterface $builder): void
    {
        $builder
            ->add('locale', ChoiceType::class, [
                'choices' => [
                    'English (en)' => 'en',
                    'Español (es)' => 'es',
                ],
                'label' => 'Langue',
                'translation_domain' => false,
            ])
            ->add('reviewed', CheckboxType::class, [
                'required' => false,
                'label' => 'Relue (ne sera plus écrasée par le seed)',
                'translation_domain' => false,
            ]);
    }

    protected function text(FormBuilderInterface $builder, string $name, string $label, bool $required = false): void
    {
        $builder->add($name, TextType::class, [
            'required' => $required,
            'label' => $label,
            'translation_domain' => false,
        ]);
    }

    protected function area(FormBuilderInterface $builder, string $name, string $label): void
    {
        $builder->add($name, TextareaType::class, [
            'required' => false,
            'label' => $label,
            'translation_domain' => false,
        ]);
    }
}
