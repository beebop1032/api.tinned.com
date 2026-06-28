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
                $this->violation('Chaque bloc doit être un objet.', "[$i]");
                continue;
            }

            $id = $block['id'] ?? null;
            if (!is_string($id) || $id === '') {
                $this->violation('Identifiant de bloc requis (string non vide).', "[$i].id");
            }

            $type = $block['type'] ?? null;
            if (!is_string($type) || !BlockCatalog::isType($type)) {
                $this->violation('Type inconnu (attendu : ' . implode(', ', BlockCatalog::types()) . ').', "[$i].type");
                continue;
            }

            foreach (BlockCatalog::requiredFields($type) as $field) {
                if (!isset($block[$field]) || $block[$field] === '' || $block[$field] === []) {
                    $this->violation("Champ requis pour le bloc « $type ».", "[$i].$field");
                }
            }

            if ($type === 'collection') {
                $source = $block['source'] ?? null;
                if ($source !== null && !in_array($source, BlockCatalog::COLLECTION_SOURCES, true)) {
                    $this->violation('Source invalide (attendu : ' . implode(', ', BlockCatalog::COLLECTION_SOURCES) . ').', "[$i].source");
                }
            }
        }
    }

    private function violation(string $message, string $path = ''): void
    {
        $builder = $this->context->buildViolation($message);
        if ($path !== '') {
            $builder->atPath($path);
        }
        $builder->addViolation();
    }
}
