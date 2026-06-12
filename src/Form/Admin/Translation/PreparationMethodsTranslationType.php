<?php

declare(strict_types=1);

namespace App\Form\Admin\Translation;

use App\Entity\PreparationMethodsTranslation;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PreparationMethodsTranslationType extends AbstractTranslationType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addMeta($builder);
        $this->text($builder, 'name', 'Nom');
        $this->area($builder, 'description', 'Description');
        $this->area($builder, 'tools', 'Outils');
        $this->area($builder, 'informations', 'Informations');
        $this->area($builder, 'advice', 'Conseil');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreparationMethodsTranslation::class,
        ]);
    }
}
