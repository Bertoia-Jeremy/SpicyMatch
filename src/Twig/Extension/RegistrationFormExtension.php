<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Factory\UsersFactory;
use App\Form\RegistrationFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Attribute\AsTwigFunction;

final readonly class RegistrationFormExtension
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private UsersFactory $usersFactory,
    ) {
    }

    #[AsTwigFunction(name: 'embedded_registration_form')]
    public function embeddedRegistrationForm(): FormView
    {
        return $this->formFactory->create(RegistrationFormType::class, $this->usersFactory->create())
            ->createView();
    }
}
