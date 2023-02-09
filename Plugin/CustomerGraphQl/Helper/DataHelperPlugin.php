<?php

namespace Ls\Hospitality\Plugin\CustomerGraphQl\Helper;

use \Ls\CustomerGraphQl\Helper\DataHelper;
use \Ls\Hospitality\Model\LSR;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * DataHelper plugin for sales entries
 */
class DataHelperPlugin
{

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @param LSR $lsr
     */
    public function __construct(
        LSR $lsr
    ) {
        $this->lsr = $lsr;
    }

    /**
     * Around plugin to format modifiers and ingredients in sales entries
     *
     * @param DataHelper $subject
     * @param callable $proceed
     * @param $items
     * @param $magOrder
     * @return array
     * @throws NoSuchEntityException
     */
    public function aroundGetItems(
        DataHelper $subject,
        callable $proceed,
        $items,
        $magOrder
    ) {
        if (!$this->lsr->isHospitalityStore()) {
            return $proceed($items);
        }

        $itemsArray  = [];
        $parent      = 0;
        $parentItem  = 0;
        $childrenKey = 'subitems';
        foreach ($items->getSalesEntryLine() as $item) {
            $data       = [
                'amount'                 => $item->getAmount(),
                'click_and_collect_line' => $item->getClickAndCollectLine(),
                'discount_amount'        => $item->getDiscountAmount(),
                'discount_percent'       => $item->getDiscountPercent(),
                'item_description'       => $item->getItemDescription(),
                'item_id'                => $item->getItemId(),
                'item_image_id'          => $item->getItemImageId(),
                'line_number'            => $item->getLineNumber(),
                'line_type'              => $item->getLineType(),
                'net_amount'             => $item->getNetAmount(),
                'net_price'              => $item->getNetPrice(),
                'parent_line'            => $item->getParentLine(),
                'price'                  => $item->getPrice(),
                'quantity'               => $item->getQuantity(),
                'store_id'               => $item->getStoreId(),
                'tax_amount'             => $item->getTaxAmount(),
                'uom_id'                 => $item->getUomId(),
                'variant_description'    => $item->getVariantDescription(),
                'variant_id'             => $item->getVariantId(),
                'custom_options'         => $this->getCustomOptions($magOrder, $item->getItemId(), $subject)
            ];
            $lineNumber = $item->getLineNumber();
            $parentLine = $item->getParentLine();
            if (empty($parentLine) || $lineNumber == $parentLine) {
                if (!empty($itemsArray) && array_key_exists($lineNumber, $itemsArray)) {
                    $tempArray[$lineNumber]                = $data;
                    $tempArray [$lineNumber][$childrenKey] = $itemsArray[$lineNumber][$childrenKey];
                    $itemsArray[$lineNumber]               = $tempArray[$lineNumber];
                    $tempArray                             = null;
                    foreach ($itemsArray[$lineNumber][$childrenKey] as $key => $value) {
                        if (array_key_exists($key, $itemsArray)) {
                            $itemsArray[$lineNumber] [$childrenKey][$key][$childrenKey] =
                                $itemsArray[$key] [$childrenKey];
                            unset($itemsArray[$key]);
                        }
                    }
                } else {
                    $itemsArray [$lineNumber] = $data;
                }
            } else {
                $itemsArray[$parentLine][$childrenKey][$lineNumber] = $data;
            }
        }

        return $itemsArray;
    }

    /**
     * Get custom options from magento
     *
     * @param $magOrder
     * @param $id
     * @param $subject
     * @return array
     */
    public function getCustomOptions($magOrder, $id, $subject)
    {
        $outputOptions = [];
        if (!empty($magOrder)) {
            $items   = $magOrder->getAllVisibleItems();
            $counter = 0;
            foreach ($items as $item) {
                list($itemId) = $subject->itemHelper->getComparisonValues(
                    $item->getSku()
                );
                if ($itemId == $id) {
                    $options = $item->getProductOptions();
                    if (isset($options['options']) && !empty($options['options'])) {
                        foreach ($options['options'] as $option) {
                            $outputOptions[$counter]['label'] = $option['label'];
                            $outputOptions[$counter]['value'] = $option['value'];
                            $counter++;
                        }
                    }
                }
            }
        }

        return $outputOptions;
    }
}
