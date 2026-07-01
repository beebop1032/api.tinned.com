<?php

namespace App\Security\Voter;

use App\Entity\Box\Box;
use App\Entity\Content\Article;
use App\Entity\Content\Trip;
use App\Entity\Product\Product;
use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductVariant;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class BoxOwnerVoter extends Voter
{
    public const BOX_EDIT = 'BOX_EDIT';
    public const ARTICLE_EDIT = 'ARTICLE_EDIT';
    public const PRODUCT_EDIT = 'PRODUCT_EDIT';
    public const VARIANT_EDIT = 'VARIANT_EDIT';
    public const BUNDLE_EDIT = 'BUNDLE_EDIT';
    public const TRIP_EDIT = 'TRIP_EDIT';

    public function __construct(private Security $security) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match($attribute) {
            self::BOX_EDIT => $subject instanceof Box,
            self::ARTICLE_EDIT => $subject instanceof Article,
            self::PRODUCT_EDIT => $subject instanceof Product,
            self::VARIANT_EDIT => $subject instanceof ProductVariant,
            self::BUNDLE_EDIT => $subject instanceof ProductBundle,
            self::TRIP_EDIT => $subject instanceof Trip,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return match(true) {
            $subject instanceof Box => $subject->getOwner()?->getId() === $user->getId(),
            $subject instanceof Article => $subject->getBlogBox()?->getOwner()?->getId() === $user->getId(),
            $subject instanceof Product => $subject->getStoreBox()?->getOwner()?->getId() === $user->getId(),
            $subject instanceof ProductVariant => $subject->getProduct()?->getStoreBox()?->getOwner()?->getId() === $user->getId(),
            $subject instanceof ProductBundle => $subject->getStoreBox()?->getOwner()?->getId() === $user->getId(),
            $subject instanceof Trip => $subject->getTravelBox()?->getOwner()?->getId() === $user->getId(),
            default => false,
        };
    }
}
