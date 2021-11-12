<?php

namespace Oro\Bundle\EntityMergeBundle\Tests\Unit\Data;

use Oro\Bundle\EntityMergeBundle\Data\EntityData;
use Oro\Bundle\EntityMergeBundle\Data\FieldData;
use Oro\Bundle\EntityMergeBundle\Metadata\FieldMetadata;
use Oro\Bundle\EntityMergeBundle\Model\MergeModes;

class FieldDataTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $entityData;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $fieldMetadata;

    /**
     * @var FieldData
     */
    protected $fieldData;

    protected function setUp(): void
    {
        $this->entityData = $this->createMock(EntityData::class);
        $this->fieldMetadata = $this->createMock(FieldMetadata::class);

        $this->fieldData = new FieldData($this->entityData, $this->fieldMetadata);
    }

    public function testGetMetadata()
    {
        $this->assertEquals($this->fieldMetadata, $this->fieldData->getMetadata());
    }

    public function testSetGetSourceEntity()
    {
        $this->assertNull($this->fieldData->getSourceEntity());
        $entity = $this->createTestEntity(1);
        $this->assertEquals($this->fieldData, $this->fieldData->setSourceEntity($entity));
        $this->assertEquals($entity, $this->fieldData->getSourceEntity());
    }

    public function testSetGetMode()
    {
        $this->assertEquals(MergeModes::REPLACE, $this->fieldData->getMode());
        $this->assertEquals($this->fieldData, $this->fieldData->setMode(MergeModes::UNITE));
        $this->assertEquals(MergeModes::UNITE, $this->fieldData->getMode());
    }

    public function testGetEntityData()
    {
        $this->assertEquals($this->entityData, $this->fieldData->getEntityData());
    }

    public function testGetFieldName()
    {
        $fieldName = 'test';
        $this->fieldMetadata->expects($this->once())
            ->method('getFieldName')
            ->willReturn($fieldName);

        $this->assertEquals($fieldName, $this->fieldData->getFieldName());
    }

    protected function createTestEntity($id)
    {
        $result = new \stdClass();
        $result->id = $id;
        return $result;
    }
}
