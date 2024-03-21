<?php declare(strict_types=1);

namespace Flora\Client\Test;

use function Flora\stringify_select;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SelectSerializationTest extends TestCase
{
    public function testSimpleArray(): void
    {
        self::assertSame('id,name', stringify_select(['id', 'name']));
    }

    public function testNestedArrays(): void
    {
        self::assertSame('id,name,attr1,attr2', stringify_select(['id', 'name', ['attr1', 'attr2']]));
    }

    public function testNestedArraysWithNonNumericKeys(): void
    {
        $spec = [
            'id',
            'name',
            'subGroup' => ['attr1', 'attr2'],
            'attr'
        ];

        self::assertSame('id,name,subGroup[attr1,attr2],attr', stringify_select($spec));
    }

    public function testNonNumericKeysWithNonArrayValues(): void
    {
        self::assertSame('subGroup.attr', stringify_select(['subGroup' => 'attr']));
    }

    public function testNestedNonNumericArrays(): void
    {
        $spec = [
            'subGroup' => ['subSubGroup' => 'attr']
        ];

        self::assertSame('subGroup.subSubGroup.attr', stringify_select($spec));
    }

    public function testDeeplyNestedArrays(): void
    {
        $spec = [
            'id',
            'name',
            'subGroupA' => [
                'id',
                'name',
                'subSubGroupA' => ['attr1', 'attr2'],
                'subSubGroupB' => [
                    'subSubSubGroupA' => ['attr1', 'attr2'],
                    'subSubSubItem',
                    'subSubSubGroupB' => ['attr1', 'attr2']
                ]
            ]
        ];

        $selectString = 'id,name,subGroupA[id,name,subSubGroupA[attr1,attr2],subSubGroupB[subSubSubGroupA[attr1,attr2],subSubSubItem,subSubSubGroupB[attr1,attr2]]]';
        self::assertSame($selectString, stringify_select($spec));
    }

    public function testSingleItemSubItemGroup(): void
    {
        self::assertSame('group.attr', stringify_select(['group' => ['attr']]));
    }

    public function testError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot handle given select specification. "1337" cannot be stringified');

        stringify_select([1337]);
    }
}
