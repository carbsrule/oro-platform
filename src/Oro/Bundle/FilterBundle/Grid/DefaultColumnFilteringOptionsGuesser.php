<?php

namespace Oro\Bundle\FilterBundle\Grid;

use Oro\Bundle\DataGridBundle\Datagrid\AbstractColumnOptionsGuesser;
use Oro\Bundle\DataGridBundle\Datagrid\Guess\ColumnGuess;
use Oro\Bundle\FilterBundle\Form\Type\Filter\NumberFilterType;

/**
 * Guesses filtering options based on a column type.
 */
class DefaultColumnFilteringOptionsGuesser extends AbstractColumnOptionsGuesser
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function guessFilter($class, $property, $type)
    {
        switch ($type) {
            case 'integer':
            case 'smallint':
            case 'bigint':
            case 'number':
                $options = [
                    'type'    => 'number-range',
                    'options' => [
                        'data_type' => NumberFilterType::DATA_INTEGER
                    ]
                ];
                break;
            case 'decimal':
            case 'float':
                $options = [
                    'type'    => 'number-range',
                    'options' => [
                        'data_type' => NumberFilterType::DATA_DECIMAL
                    ]
                ];
                break;
            case 'boolean':
                $options = [
                    'type' => 'boolean'
                ];
                break;
            case 'date':
                $options = [
                    'type' => 'date'
                ];
                break;
            case 'datetime':
                $options = [
                    'type' => 'datetime'
                ];
                break;
            case 'money':
                $options = [
                    'type' => 'number-range'
                ];
                break;
            case 'percent':
                $options = [
                    'type' => 'percent'
                ];
                break;
            default:
                $options = [
                    'type' => 'string'
                ];
                break;
        }

        return new ColumnGuess($options, ColumnGuess::LOW_CONFIDENCE);
    }
}
