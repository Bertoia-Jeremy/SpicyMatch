<?php

declare(strict_types=1);

namespace App\Form\Admin\Translation;

use App\Entity\FaqQuestionTranslation;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FaqQuestionTranslationType extends AbstractTranslationType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addMeta($builder);
        $this->text($builder, 'question', 'Question', true);
        $builder->add('answer', TextareaType::class, [
            'required' => true,
            'label' => 'Réponse',
            'translation_domain' => false,
            'attr' => [
                'rows' => 6,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FaqQuestionTranslation::class,
        ]);
    }
}
