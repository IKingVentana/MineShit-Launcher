<?php
namespace app\modules;

use php\gui\UXNode;

class ControlFinder
{
    /**
     * Рекурсивный поиск контролла по ID
     * @param UXNode $root
     * @param string $id
     * @return UXNode|null
     */
    public static function findControlById(UXNode $root, string $id)
    {
        if ($root->getId() === $id) {
            return $root;
        }
        foreach ($root->getChildren() as $child) {
            $found = self::findControlById($child, $id);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }
}
