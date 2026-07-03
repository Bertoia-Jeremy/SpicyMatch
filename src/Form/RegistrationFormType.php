<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Users;
use App\Validator\AltchaSolved;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'form.register.username',
                'constraints' => [
                    new Regex(pattern: '/^anonyme-/i', match: false, message: 'user.username_reserved'),
                ],
            ])
            ->add('mail', EmailType::class, [
                'label' => 'form.register.mail',
                'required' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'form.register.password',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'user.password_blank',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'user.password_min',
                        'max' => 255,
                    ]),
                ],
            ])
            ->add('altcha', HiddenType::class, [
                'mapped' => false,
                'label' => false,
                'error_bubbling' => false,
                'constraints' => [new AltchaSolved()],
            ])
            ->add('Valider', SubmitType::class, [
                'label' => 'common.actions.submit',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => Users::class,
                'csrf_protection' => true,
                'csrf_field_name' => '_token',
                'csrf_token_id' => 'registration_csrf_token',
            ]
        );
    }
}
