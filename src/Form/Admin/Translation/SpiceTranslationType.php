<?php

declare(strict_types=1);

namespace App\Form\Admin\Translation;

use App\Entity\SpiceTranslation;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SpiceTranslationType extends AbstractTranslationType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addMeta($builder);
        $this->text($builder, 'name', 'Nom', true);
        $this->area($builder, 'description', 'Description');
        $this->area($builder, 'cooking', 'En cuisine');
        $this->area($builder, 'informations', 'Informations');
        $this->area($builder, 'benefits', 'Bienfaits');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SpiceTranslation::class,
        ]);
    }
}
