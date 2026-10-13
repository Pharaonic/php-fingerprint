<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

/**
 * Removes repeated list elements, keeping the first occurrence: unique() or uniqueBy()
 *
 * unique() compares whole elements by their canonical bytes, so 1 and '1' are both kept.
 * uniqueBy() compares one field; elements missing that field count as one shared value.
 *
 * @internal
 */
final readonly class ListUnique
{
    /**
     * @param non-empty-list<string>|null $field
     */
    public function __construct(private ?array $field = null) {}

    /**
     * @param list<mixed> $items
     * @return list<mixed>
     */
    public function apply(array $items): array
    {
        $seen = [];
        $kept = [];

        foreach ($items as $item) {
            if ($this->field === null) {
                $key = Encoder::encode($item);
            } else {
                $value = Tree::field($item, $this->field);
                $key = $value === Tree::missing() ? '' : Encoder::encode($value);
            }

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $kept[] = $item;
            }
        }

        return $kept;
    }
}
