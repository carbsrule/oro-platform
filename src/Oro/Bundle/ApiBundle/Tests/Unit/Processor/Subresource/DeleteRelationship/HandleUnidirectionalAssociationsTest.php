<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\DeleteRelationship;

use Oro\Bundle\ApiBundle\Config\Config;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Form\Handler\UnidirectionalAssociationHandler;
use Oro\Bundle\ApiBundle\Processor\Config\Shared\CompleteDefinition\UnidirectionalAssociationCompleter;
use Oro\Bundle\ApiBundle\Processor\Subresource\DeleteRelationship\HandleUnidirectionalAssociations;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\ChangeRelationshipProcessorTestCase;
use Symfony\Component\Form\FormInterface;

class HandleUnidirectionalAssociationsTest extends ChangeRelationshipProcessorTestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|UnidirectionalAssociationHandler */
    private $handler;

    /** @var HandleUnidirectionalAssociations */
    private $processor;

    protected function setUp()
    {
        parent::setUp();

        $this->handler = $this->createMock(UnidirectionalAssociationHandler::class);

        $this->processor = new HandleUnidirectionalAssociations($this->handler);
    }

    public function testProcessWhenNoParentConfig()
    {
        $this->handler->expects(self::never())
            ->method('handleDelete');

        $this->configProvider->expects(self::once())
            ->method('getConfig')
            ->willReturn(new Config());
        $this->context->setParentClassName('Test\ParentEntity');
        $this->processor->process($this->context);
    }

    public function testProcessWhenNoUnidirectionalAssociations()
    {
        $parentConfig = new EntityDefinitionConfig();

        $this->handler->expects(self::never())
            ->method('handleDelete');

        $this->context->setParentClassName('Test\ParentEntity');
        $this->context->setAssociationName('association');
        $this->context->setParentConfig($parentConfig);
        $this->processor->process($this->context);
    }

    public function testProcessWhenCurrentAssociationIsNotUnidirectionalAssociation()
    {
        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->set(
            UnidirectionalAssociationCompleter::UNIDIRECTIONAL_ASSOCIATIONS,
            ['field1' => 'targetAssociation1']
        );

        $this->handler->expects(self::never())
            ->method('handleDelete');

        $this->context->setParentClassName('Test\ParentEntity');
        $this->context->setAssociationName('association');
        $this->context->setParentConfig($parentConfig);
        $this->processor->process($this->context);
    }

    public function testProcessWithUnidirectionalAssociations()
    {
        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->set(
            UnidirectionalAssociationCompleter::UNIDIRECTIONAL_ASSOCIATIONS,
            ['field1' => 'targetAssociation1', 'association' => 'targetAssociation']
        );
        $form = $this->createMock(FormInterface::class);

        $this->handler->expects(self::once())
            ->method('handleDelete')
            ->with(
                self::identicalTo($form),
                self::identicalTo($parentConfig),
                ['association' => 'targetAssociation'],
                self::identicalTo($this->context->getRequestType())
            );

        $this->context->setParentClassName('Test\ParentEntity');
        $this->context->setAssociationName('association');
        $this->context->setParentConfig($parentConfig);
        $this->context->setForm($form);
        $this->processor->process($this->context);
    }
}
