<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ValidBlocks extends Constraint
{
    public string $message = 'Le contenu (blocks) est invalide.';
}
