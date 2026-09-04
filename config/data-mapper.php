<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Transformer groups
    |--------------------------------------------------------------------------
    |
    | Transformers that belong to your application rather than to this package —
    | anything carrying business meaning — are registered here, keyed by the group
    | they belong to. The package never interprets a group name; it only answers
    | which transformers are in one, so a UI can offer the right subset.
    |
    | The ten shipped transformers always register in the "core" group
    | (Medox\DataMapper\ValueTransformer::GROUP_CORE) and need no entry here.
    |
    |     'groups' => [
    |         'import' => [
    |             App\Import\Transformers\StockNumberTransformer::class,
    |         ],
    |         'export' => [
    |             App\Export\Transformers\TransmissionExpandTransformer::class,
    |         ],
    |     ],
    |
    | Each class must implement Medox\DataMapper\Contracts\TransformerInterface
    | and is resolved through the container, so it may declare its own dependencies.
    |
    */

    'groups' => [
        //
    ],

];
