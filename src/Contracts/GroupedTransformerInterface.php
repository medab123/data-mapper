<?php

declare(strict_types=1);

namespace Medox\DataMapper\Contracts;

use Medox\DataMapper\ValueTransformer;

/**
 * A transformer that knows which groups it belongs to.
 *
 * Implementing this is optional. It exists so an application can hand the
 * package a transformer and have it land in the right group without the
 * registration site having to repeat the group name:
 *
 *     final class TransmissionExpandTransformer implements GroupedTransformerInterface
 *     {
 *         public function getGroups(): array
 *         {
 *             return ['export'];
 *         }
 *     }
 *
 * A group passed explicitly to {@see ValueTransformer::registerTransformer()}
 * always wins over the one declared here.
 */
interface GroupedTransformerInterface extends TransformerInterface
{
    /**
     * The groups this transformer should be registered in.
     *
     * @return array<int, string>
     */
    public function getGroups(): array;
}
