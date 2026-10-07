<?php
namespace App\Support;

use Error;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use ReflectionException;
use ValueError;
/**
 * Refle
 */
class ReflectionHelper
{
    /**
     * @throws ReflectionException
     * @throws Error
     *
     * @return array{mixed, mixed}
     */
    public static function getDirectionClasses(): array
    {
        $directionClass = new \ReflectionMethod(GridWidget::class, "direction")
            ->getParameters()[0]
            ->getType()
            ->getName();
        return [
            constant("$directionClass::Horizontal"),
            constant("$directionClass::Vertical"),
        ];
    }

    /**
     * @throws ReflectionException
     */
    public static function getConstraintClass(): string
    {
        return new \ReflectionMethod(GridWidget::class, "constraints")
            ->getParameters()[0]
            ->getType()
            ->getName();
    }

    /**
     * @throws ReflectionException
     * @throws Error
     */
    public static function getBordersAll(): int
    {
        $bordersType = new \ReflectionMethod(BlockWidget::class, "borders")
            ->getParameters()[0]
            ->getType()
            ->getName();
        if ($bordersType !== "int" && class_exists($bordersType)) {
            return constant("$bordersType::ALL");
        }
        foreach (
            ["PhpTui\Tui\Model\Widget\Borders", "PhpTui\Tui\Widget\Borders"]
            as $ns
        ) {
            if (class_exists($ns) && defined("$ns::ALL")) {
                return constant("$ns::ALL");
            }
        }
        return 15;
    }

    /**
     * @throws ReflectionException
     */
    public static function getTitleType(): string
    {
        return new \ReflectionMethod(BlockWidget::class, "titles")
            ->getParameters()[0]
            ->getType()
            ->getName();
    }

    /**
     * @throws ReflectionException
     * @throws Error
     *
     * @return BlockWidget
     */
    public static function createBlock(string $title)
    {
        $block = BlockWidget::default()->borders(self::getBordersAll());
        $titleType = self::getTitleType();
        if ($titleType === "string") {
            return $block->titles($title);
        }
        return $block->titles($titleType::fromString($title));
    }

    /**
     * @throws ValueError
     *
     * @return list<string>
     */
    public static function wrapText(string $text, int $width): array
    {
        if ($width < 1) {
            $width = 1;
        }
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $wrapped = wordwrap($line, $width, "\n", true);
            $lines = array_merge($lines, explode("\n", $wrapped));
        }
        return $lines;
    }
}
