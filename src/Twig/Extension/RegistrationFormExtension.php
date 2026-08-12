<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Factory\UsersFactory;
use App\Form\RegistrationFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RegistrationFormExtension extends AbstractExtension
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly UsersFactory $usersFactory,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('embedded_registration_form', $this->embeddedRegistrationForm(...)),
        ];
    }

    public function embeddedRegistrationForm(): FormView
    {
        return $this->formFactory->create(RegistrationFormType::class, $this->usersFactory->create())
            ->createView();
    }
}
