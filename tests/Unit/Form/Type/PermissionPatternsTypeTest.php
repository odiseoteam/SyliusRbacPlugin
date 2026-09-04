<?php

declare(strict_types=1);

namespace Tests\Odiseo\SyliusRbacPlugin\Unit\Form\Type;

use Odiseo\SyliusRbacPlugin\Form\Type\PermissionPatternsType;
use Symfony\Component\Form\Test\TypeTestCase;

final class PermissionPatternsTypeTest extends TypeTestCase
{
    public function testItReadsTheStoredPatternsBackAsOneFieldPerLine(): void
    {
        $form = $this->factory->create(PermissionPatternsType::class, ['sylius.product.*', 'sylius.order.index']);

        self::assertSame("sylius.product.*\nsylius.order.index", $form->getViewData());
    }

    public function testItSubmitsOnePatternPerLine(): void
    {
        $form = $this->factory->create(PermissionPatternsType::class);
        $form->submit("sylius.product.*\n*.*.index\nsylius.order.cancel");

        self::assertTrue($form->isSynchronized());
        self::assertSame(['sylius.product.*', '*.*.index', 'sylius.order.cancel'], $form->getData());
    }

    public function testItIgnoresBlankLinesAndSurroundingSpace(): void
    {
        $form = $this->factory->create(PermissionPatternsType::class);
        $form->submit("  sylius.product.*  \n\n\n sylius.order.index\n");

        self::assertSame(['sylius.product.*', 'sylius.order.index'], $form->getData());
    }

    public function testItKeepsEachPatternOnlyOnce(): void
    {
        $form = $this->factory->create(PermissionPatternsType::class);
        $form->submit("sylius.product.*\nsylius.product.*");

        self::assertSame(['sylius.product.*'], $form->getData());
    }

    /**
     * The value is produced by the editor, so anything malformed reaching here is a tampered
     * request. Storing a pattern nothing can match would grant nothing while looking like it
     * grants something, so it is dropped rather than kept.
     *
     * @dataProvider malformedPatterns
     */
    public function testItDropsAPatternNothingCouldEverMatch(string $malformed): void
    {
        $form = $this->factory->create(PermissionPatternsType::class);
        $form->submit("sylius.product.*\n" . $malformed);

        self::assertTrue($form->isSynchronized());
        self::assertSame(['sylius.product.*'], $form->getData());
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPatterns(): iterable
    {
        yield 'too few segments' => ['sylius.product'];
        yield 'too many segments' => ['sylius.product.index.extra'];
        yield 'uppercase' => ['Sylius.product.index'];
        yield 'a dash' => ['sylius.product-variant.index'];
        yield 'a leading digit' => ['sylius.2product.index'];
        yield 'a partial wildcard' => ['sylius.product.ind*'];
    }

    public function testItSubmitsNothingAsAnEmptyList(): void
    {
        $form = $this->factory->create(PermissionPatternsType::class);
        $form->submit('');

        self::assertSame([], $form->getData());
    }
}
