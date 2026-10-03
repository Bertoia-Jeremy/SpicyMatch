<?php

declare(strict_types=1);

namespace App\Form\Admin\Translation;

use App\Entity\FaqCategoryTranslation;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FaqCategoryTranslationType extends AbstractTranslationType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addMeta($builder);
        $this->text($builder, 'name', 'Nom', true);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FaqCategoryTranslation::class,
        ]);
    }
}
