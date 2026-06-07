<?php

declare(strict_types=1);

namespace App\Form\Admin\Translation;

use App\Entity\AchievementTranslation;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AchievementTranslationType extends AbstractTranslationType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addMeta($builder);
        $this->text($builder, 'name', 'Nom', true);
        $this->area($builder, 'description', 'Description');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AchievementTranslation::class,
        ]);
    }
}
