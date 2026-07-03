<?php

declare(strict_types=1);

namespace App\Validator;

use App\Service\Security\AltchaManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class AltchaSolvedValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AltchaManager $altchaManager,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof AltchaSolved) {
            throw new UnexpectedTypeException($constraint, AltchaSolved::class);
        }

        if (null !== $value && ! is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (null !== $value && '' !== $value && $this->altchaManager->verify($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->addViolation();
    }
}
