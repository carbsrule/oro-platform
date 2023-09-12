<?php

namespace Oro\Bundle\OrganizationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for selecting business unit in tree
 */
class BusinessUnitTreeSelectType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return BusinessUnitTreeType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'configs' => array(
                    'is_safe'              => false,
                ),
                'business_unit_ids' => [],
                'forbidden_business_unit_ids' => []
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'oro_business_unit_tree_select';
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['business_unit_ids'] = array_combine($options['business_unit_ids'], $options['business_unit_ids']);
        $view->vars['forbidden_business_unit_ids'] = $options['forbidden_business_unit_ids'];
    }
}
