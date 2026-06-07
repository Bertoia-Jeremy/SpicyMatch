<?php

declare(strict_types=1);

namespace App\Form\Admin\Translation;

use App\Entity\PreparationTipsTranslation;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PreparationTipsTranslationType extends AbstractTranslationType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addMeta($builder);
        $this->text($builder, 'title', 'Titre');
        $this->area($builder, 'text', 'Texte');
        $this->text($builder, 'advantages', 'Avantages');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreparationTipsTranslation::class,
        ]);
    }
}
