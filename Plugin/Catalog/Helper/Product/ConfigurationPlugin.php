<?php
/**
 * Copyright © 2020 LS Retail ehf. All rights reserved.
 * See COPYING.txt for license details.
 * @author: Zeeshan Khuwaja <zeeshan.khuwaja@lsretail.com>
 */

namespace Ls\Hospitality\Plugin\Catalog\Helper\Product;

use Ls\Hospitality\Model\LSR as LSRModel;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;

class ConfigurationPlugin
{

    public $lsr;

    /** @var */
    public $serializer;

    public function __construct(
        LSRModel $lsr,
        Json $serializer = null
    ) {
        $this->lsr        = $lsr;
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
    }

    public function afterGetCustomOptions(
        \Magento\Catalog\Helper\Product\Configuration $subject,
        $result,
        \Magento\Catalog\Model\Product\Configuration\Item\ItemInterface $item
    ) {
        if (!$this->lsr->isHospitalityStore()) {
            return $result;
        }
        $product   = $item->getProduct();
        $options   = [];
        $optionIds = $item->getOptionByCode('option_ids');
        if ($optionIds) {
            $options = [];
            foreach (explode(',', $optionIds->getValue()) as $optionId) {
                $option = $product->getOptionById($optionId);
                if ($option) {
                    $itemOption = $item->getOptionByCode('option_' . $option->getId());
                    /** @var $group \Magento\Catalog\Model\Product\Option\Type\DefaultType */
                    $group = $option->groupFactory($option->getType())
                        ->setOption($option)
                        ->setConfigurationItem($item)
                        ->setConfigurationItemOption($itemOption);

                    if ('file' == $option->getType()) {
                        $downloadParams = $item->getFileDownloadParams();
                        if ($downloadParams) {
                            $url = $downloadParams->getUrl();
                            if ($url) {
                                $group->setCustomOptionDownloadUrl($url);
                            }
                            $urlParams = $downloadParams->getUrlParams();
                            if ($urlParams) {
                                $group->setCustomOptionUrlParams($urlParams);
                            }
                        }
                    }

                    $options[] = [
                        'label'                 => $option->getTitle(),
                        'value'                 => $group->getFormattedOptionValue($itemOption->getValue()),
                        'print_value'           => $group->getPrintableOptionValue($itemOption->getValue()),
                        'option_id'             => $option->getId(),
                        'option_type'           => $option->getType(),
                        'custom_view'           => $group->isCustomizedView(),
                        'ls_modifier_recipe_id' => $option->getData('ls_modifier_recipe_id')
                    ];
                }
            }
        }

        $addOptions = $item->getOptionByCode('additional_options');
        if ($addOptions) {
            $options = array_merge($options, $this->serializer->unserialize($addOptions->getValue()));
        }

        return $options;
    }

}
