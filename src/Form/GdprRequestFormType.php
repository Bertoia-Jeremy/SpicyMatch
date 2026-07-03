<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\GdprRequest;
use App\Enum\GdprRequestType;
use App\Validator\AltchaSolved;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GdprRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.gdpr.email',
            ])
            ->add('requestType', EnumType::class, [
                'class' => GdprRequestType::class,
                'label' => 'form.gdpr.request_type',
                'choice_label' => static fn (GdprRequestType $type): string => $type->label(),
            ])
            ->add('message', TextareaType::class, [
                'label' => 'form.gdpr.message',
                'required' => false,
            ])
            ->add('altcha', HiddenType::class, [
                'mapped' => false,
                'label' => false,
                'error_bubbling' => false,
                'constraints' => [new AltchaSolved()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GdprRequest::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'gdpr_request_csrf_token',
        ]);
    }
}
