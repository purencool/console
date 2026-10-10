<?php
namespace App\Support;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionException;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;

class ReflectionHelper
{
    /** @var array<string, mixed> Memoization cache map to store resolved reflection tokens */
    private static array $cache = [];

    /**
     * Resolves layout grid orientation enums safely matching your active PhpTui library release.
     *
     * @throws ReflectionException
     * @return array{mixed, mixed} Horizontal and Vertical direction enum definitions
     */
    public static function getDirectionClasses(): array
    {
        if (isset(self::$cache["directions"])) {
            return self::$cache["directions"];
        }

        $directionClass = self::getParameterClassName(
            GridWidget::class,
            "direction",
            0,
        );

        self::$cache["directions"] = [
            constant("$directionClass::Horizontal"),
            constant("$directionClass::Vertical"),
        ];

        return self::$cache["directions"];
    }

    /**
     * Resolves the fully qualified class name for grid constraint factories.
     *
     * @throws ReflectionException
     */
    public static function getConstraintClass(): string
    {
        if (isset(self::$cache["constraint_class"])) {
            return self::$cache["constraint_class"];
        }

        self::$cache["constraint_class"] = self::getParameterClassName(
            GridWidget::class,
            "constraints",
            0,
        );

        return self::$cache["constraint_class"];
    }

    /**
     * Dynamically finds the composite border layout type definition flag.
     *
     * @throws ReflectionException
     */
    public static function getBordersAll(): int
    {
        if (isset(self::$cache["borders_all"])) {
            return self::$cache["borders_all"];
        }

        $bordersType = self::getParameterClassName(
            BlockWidget::class,
            "borders",
            0,
        );

        if ($bordersType !== "int" && class_exists($bordersType)) {
            self::$cache["borders_all"] = constant("$bordersType::ALL");
            return self::$cache["borders_all"];
        }

        foreach (
            ["PhpTui\Tui\Model\Widget\Borders", "PhpTui\Tui\Widget\Borders"]
            as $ns
        ) {
            if (class_exists($ns) && defined("$ns::ALL")) {
                self::$cache["borders_all"] = constant("$ns::ALL");
                return self::$cache["borders_all"];
            }
        }

        self::$cache["borders_all"] = 15; // Standard fallback parameter boundary mask
        return self::$cache["borders_all"];
    }

    /**
     * Resolves the target layout parameter expected by your block headers titles modifier.
     *
     * @throws ReflectionException
     */
    public static function getTitleType(): string
    {
        if (isset(self::$cache["title_type"])) {
            return self::$cache["title_type"];
        }

        self::$cache["title_type"] = self::getParameterClassName(
            BlockWidget::class,
            "titles",
            0,
        );

        return self::$cache["title_type"];
    }

    /**
     * Builds a standardized, bordered container block with styled text title mappings.
     *
     * @throws ReflectionException
     */
    public static function createBlock(string $title): BlockWidget
    {
        $block = BlockWidget::default()->borders(self::getBordersAll());
        $titleType = self::getTitleType();

        if ($titleType === "string") {
            return $block->titles($title);
        }

        return $block->titles($titleType::fromString($title));
    }

    /**
     * Safe multiline-aware word wrapping algorithm utilizing mbstring boundaries.
     *
     * @return list<string> Compiled line segments fitting the specified viewport width bounds
     */
    public static function wrapText(string $text, int $width): array
    {
        $clampedWidth = max(1, $width);
        $lines = [];

        foreach (explode("\n", $text) as $line) {
            $wrapped = wordwrap($line, $clampedWidth, "\n", true);
            foreach (explode("\n", $wrapped) as $wrappedLine) {
                $lines[] = $wrappedLine;
            }
        }

        return $lines;
    }

    /**
     * Internal abstraction helper to safely resolve parameter type names while evading abstract base class errors.
     *
     * @throws ReflectionException
     */
    private static function getParameterClassName(
        string $class,
        string $method,
        int $parameterIndex,
    ): string {
        $reflectionMethod = new ReflectionMethod($class, $method);
        $parameters = $reflectionMethod->getParameters();

        if (!isset($parameters[$parameterIndex])) {
            return "";
        }

        $type = $parameters[$parameterIndex]->getType();

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return "";
    }
}
