<?php declare(strict_types=1);

namespace Flora\Client\Test;

use function Flora\stringify_select;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SelectSerializationTest extends TestCase
{
    public function testSimpleArray(): void
    {
        $this->assertEquals('id,name', stringify_select(['id', 'name']));
    }

    public function testNestedArrays(): void
    {
        $this->assertEquals('id,name,attr1,attr2', stringify_select(['id', 'name', ['attr1', 'attr2']]));
    }

    public function testNestedArraysWithNonNumericKeys(): void
    {
        $spec = [
            'id',
            'name',
            'subGroup' => ['attr1', 'attr2'],
            'attr'
        ];

        $this->assertEquals('id,name,subGroup[attr1,attr2],attr', stringify_select($spec));
    }

    public function testNonNumericKeysWithNonArrayValues(): void
    {
        $this->assertEquals('subGroup.attr', stringify_select(['subGroup' => 'attr']));
    }

    public function testNestedNonNumericArrays(): void
    {
        $spec = [
            'subGroup' => ['subSubGroup' => 'attr']
        ];

        $this->assertEquals('subGroup.subSubGroup.attr', stringify_select($spec));
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
        $this->assertEquals($selectString, stringify_select($spec));
    }

    public function testSingleItemSubItemGroup(): void
    {
        $this->assertEquals('group.attr', stringify_select(['group' => ['attr']]));
    }

    public function testError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot handle given select specification. "1337" cannot be stringified');

        stringify_select([1337]);
    }
}
