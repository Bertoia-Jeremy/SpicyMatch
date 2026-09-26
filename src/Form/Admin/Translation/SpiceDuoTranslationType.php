<?php

declare(strict_types=1);

namespace App\Form\Admin\Translation;

use App\Entity\SpiceDuoTranslation;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SpiceDuoTranslationType extends AbstractTranslationType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addMeta($builder);
        $this->text($builder, 'title', 'Accroche');
        $this->area($builder, 'effect', 'Effet');
        $this->area($builder, 'science', 'Science');
        $this->area($builder, 'example', 'Exemple');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SpiceDuoTranslation::class,
        ]);
    }
}
