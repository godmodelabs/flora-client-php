<?php

namespace Flora\Client\Test;

use function Flora\stringify_select;
use PHPUnit\Framework\TestCase;

class SelectSerializationTest extends TestCase
{
    public function testSimpleArray()
    {
        $this->assertEquals('id,name', stringify_select(['id', 'name']));
    }

    public function testNestedArrays()
    {
        $this->assertEquals('id,name,attr1,attr2', stringify_select(['id', 'name', ['attr1', 'attr2']]));
    }

    public function testNestedArraysWithNonNumericKeys()
    {
        $spec = [
            'id',
            'name',
            'subGroup' => ['attr1', 'attr2'],
            'attr'
        ];

        $this->assertEquals('id,name,subGroup[attr1,attr2],attr', stringify_select($spec));
    }

    public function testNonNumericKeysWithNonArrayValues()
    {
        $this->assertEquals('subGroup.attr', stringify_select(['subGroup' => 'attr']));
    }

    public function testNestedNonNumericArrays()
    {
        $spec = [
            'subGroup' => ['subSubGroup' => 'attr']
        ];

        $this->assertEquals('subGroup.subSubGroup.attr', stringify_select($spec));
    }

    public function testDeeplyNestedArrays()
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

    public function testSingleItemSubItemGroup()
    {
        $this->assertEquals('group.attr', stringify_select(['group' => ['attr']]));
    }

    public function testError()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot handle given select specification. "1337" cannot be stringified');

        stringify_select([1337]);
    }
}
