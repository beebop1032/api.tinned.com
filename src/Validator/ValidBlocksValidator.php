<?php

namespace App\Validator;

use App\Service\Content\BlockCatalog;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidBlocksValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidBlocks) {
            throw new UnexpectedValueException($constraint, ValidBlocks::class);
        }

        // null / [] : une landing sans blocs est permise.
        if ($value === null || $value === []) {
            return;
        }

        if (!is_array($value) || !array_is_list($value)) {
            $this->context->buildViolation('Le champ blocks doit être une liste ordonnée de blocs.')->addViolation();
            return;
        }

        foreach ($value as $i => $block) {
            if (!is_array($block)) {
                $this->violation("[$i] : chaque bloc doit être un objet.");
                continue;
            }

            $id = $block['id'] ?? null;
            if (!is_string($id) || $id === '') {
                $this->violation("[$i].id : identifiant de bloc requis (string non vide).");
            }

            $type = $block['type'] ?? null;
            if (!is_string($type) || !BlockCatalog::isType($type)) {
                $this->violation("[$i].type : type inconnu (attendu : " . implode(', ', BlockCatalog::types()) . ').');
                continue;
            }

            foreach (BlockCatalog::requiredFields($type) as $field) {
                if (!isset($block[$field]) || $block[$field] === '' || $block[$field] === []) {
                    $this->violation("[$i].$field : champ requis pour le bloc « $type ».");
                }
            }

            if ($type === 'collection') {
                $source = $block['source'] ?? null;
                if (!in_array($source, BlockCatalog::COLLECTION_SOURCES, true)) {
                    $this->violation("[$i].source : source invalide (attendu : " . implode(', ', BlockCatalog::COLLECTION_SOURCES) . ').');
                }
            }
        }
    }

    private function violation(string $message): void
    {
        $this->context->buildViolation($message)->addViolation();
    }
}
