<?php

namespace Ls\Hospitality\Helper;

use \Ls\Hospitality\Model\LSR;
use \Ls\Omni\Helper\Data;
use \Ls\Omni\Client\Ecommerce\Entity\Enum\StoreHourOpeningType;
use \Ls\Omni\Client\ResponseInterface;
use \Ls\Omni\Client\Ecommerce\Entity;
use \Ls\Omni\Client\Ecommerce\Operation;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Store Helper function
 *
 */
class StoreHelper extends AbstractHelper
{
    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @var Data
     */
    public $dataHelper;

    /**
     * @var DateTime
     */
    public $dateTime;

    /**
     * @var $pickupDateFormat
     */
    public $pickupDateFormat;

    /**
     * @var $pickupTimeFormat
     */
    public $pickupTimeFormat;

    /**
     * @param Context $context
     * @param Data $dataHelper
     * @param DateTime $dateTime
     * @param LSR $lsr
     */
    public function __construct(
        Context $context,
        Data $dataHelper,
        DateTime $dateTime,
        LSR $lsr
    ) {
        parent::__construct($context);
        $this->lsr        = $lsr;
        $this->dataHelper = $dataHelper;
        $this->dateTime   = $dateTime;
    }

    /**
     * Getting sales type
     *
     * @param string $websiteId
     * @param null $webStore
     * @param null $baseUrl
     * @return array|Entity\Store|Entity\StoreGetByIdResponse|ResponseInterface|null
     */
    public function getSalesType($websiteId = '', $webStore = null, $baseUrl = null)
    {
        return $this->getStore($websiteId, $webStore, $baseUrl);
    }


    /**
     * Getting sales type
     *
     * @param string $websiteId
     * @param null $webStore
     * @param null $baseUrl
     * @return array|Entity\Store|Entity\StoreGetByIdResponse|ResponseInterface|null
     */
    public function getStore($websiteId = '', $webStore = null, $baseUrl = null)
    {
        $response = [];
        if ($webStore == null) {
            $webStore = $this->lsr->getWebsiteConfig(LSR::SC_SERVICE_STORE, $websiteId);
        }
        if ($baseUrl == null) {
            $baseUrl = $this->lsr->getWebsiteConfig(LSR::SC_SERVICE_BASE_URL, $websiteId);
        }
        // @codingStandardsIgnoreStart
        $request   = new Entity\StoreGetById();
        $operation = new Operation\StoreGetById($baseUrl);
        // @codingStandardsIgnoreEnd

        $request->setStoreId($webStore);

        try {
            $response = $operation->execute($request);
        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
        }
        return $response ? $response->getResult() : $response;
    }


    /**
     * @return array
     */
    public function getStoreOrderingHours()
    {
        $store                  = $this->getStore();
        $storeHours             = $store->getStoreHours();
        $today                  = $this->getCurrentDate();
        $this->pickupDateFormat = $this->lsr->getStoreConfig(LSR::PICKUP_DATE_FORMAT);
        $this->pickupTimeFormat = $this->lsr->getStoreConfig(LSR::PICKUP_TIME_FORMAT);
        $dateTimSlots           = [];
        for ($count = 0; $count < 7; $count++) {
            $current          = $this->dateTime->date(
                $this->pickupDateFormat,
                strtotime($today) + ($count * 86400)
            );
            $currentDayOfWeek = $this->dateTime->date('w', strtotime($current));
            foreach ($storeHours as $storeHour) {
                if ($storeHour->getStoreId() == 'ORDER') {
                    if ($storeHour->getDayOfWeek() == $currentDayOfWeek) {
                        if ($this->dataHelper->checkDateValidity($current, $storeHour)) {
                            if ($storeHour->getType() == StoreHourOpeningType::NORMAL) {
                                $dateTimSlots[$current][StoreHourOpeningType::NORMAL] =
                                    [
                                        "open"  => $this->formatTime($storeHour->getOpenFrom()),
                                        "close" => $this->formatTime($storeHour->getOpenTo())
                                    ];
                            } elseif ($storeHour->getType() == StoreHourOpeningType::TEMPORARY) {
                                $dateTimSlots[$current][StoreHourOpeningType::TEMPORARY] =
                                    [
                                        "open"  => $this->formatTime($storeHour->getOpenFrom()),
                                        "close" => $this->formatTime($storeHour->getOpenTo())
                                    ];
                            } else {
                                $dateTimSlots[$current][StoreHourOpeningType::CLOSED] =
                                    [
                                        "open"  => $this->formatTime($storeHour->getOpenFrom()),
                                        "close" => $this->formatTime($storeHour->getOpenTo())
                                    ];
                            }
                        }
                    }
                }
            }
        }
        return $dateTimSlots;
    }

    /**
     * For getting date and timeslots option
     *
     * @return array
     */
    public function formatDateTimeSlotsValues()
    {
        $results = [];
        if ($this->lsr->isLSR($this->lsr->getCurrentStoreId())) {
            $options = $this->getDateTimeSlotsValues();
            if (!empty($options)) {
                foreach ($options as $key => $option) {
                    $results[$key] = $option[StoreHourOpeningType::NORMAL];
                }
            }
        }

        return $results;
    }

    /**
     * Format time
     *
     * @param $time
     * @return string
     */
    public function formatTime($time)
    {
        return $this->dateTime->date(
            $this->pickupTimeFormat,
            strtotime($time)
        );
    }

    /**
     * Get date time slots
     *
     * @return array
     */
    public function getDateTimeSlotsValues()
    {
        $dateTimeSlots      = [];
        $storeOrderingHours = $this->getStoreOrderingHours();
        $timeInterval       = $this->lsr->getStoreConfig(LSR::PICKUP_TIME_INTERVAL);
        $currentTime        = $this->dateTime->gmtDate($this->pickupTimeFormat);
        foreach ($storeOrderingHours as $date => $storeOrderHour) {
            foreach ($storeOrderHour as $type => $value) {
                $dateTimeSlots[$date][$type] = $this->applyPickupTimeInterval(
                    $value['open'],
                    $value['close'],
                    $timeInterval
                );
                if ($type == StoreHourOpeningType::CLOSED) {
                    if (array_key_exists(StoreHourOpeningType::NORMAL, $dateTimeSlots[$date])) {
                        $dateTimeSlots[$date][StoreHourOpeningType::NORMAL] = array_diff(
                            $dateTimeSlots[$date][StoreHourOpeningType::NORMAL],
                            $dateTimeSlots[$date][StoreHourOpeningType::CLOSED]
                        );
                    }
                }
                if ($type == StoreHourOpeningType::TEMPORARY) {
                    if (array_key_exists(StoreHourOpeningType::NORMAL, $dateTimeSlots[$date])) {
                        $dateTimeSlots[$date][StoreHourOpeningType::NORMAL] = array_merge(
                            $dateTimeSlots[$date][StoreHourOpeningType::NORMAL],
                            $dateTimeSlots[$date][StoreHourOpeningType::TEMPORARY]
                        );
                    }
                }
            }
            if ($this->getCurrentDate() == $date) {
                if (array_key_exists(StoreHourOpeningType::NORMAL, $dateTimeSlots[$date])) {
                    $arrayResult = $dateTimeSlots[$date][StoreHourOpeningType::NORMAL] = array_diff(
                        $dateTimeSlots[$date][StoreHourOpeningType::NORMAL],
                        $this->applyPickupTimeInterval(
                            reset($dateTimeSlots[$date][StoreHourOpeningType::NORMAL]),
                            $currentTime,
                            $timeInterval,
                            0
                        )
                    );
                    if (empty($arrayResult)) {
                        unset($dateTimeSlots[$date]);
                    }
                }
            }
        }
        return $dateTimeSlots;
    }

    /**
     * Current date
     *
     * @return false|string
     */
    public function getCurrentDate()
    {
        return $this->dateTime->gmtDate($this->pickupDateFormat);
    }

    /**
     * Apply pickup time interval
     *
     * @param $startTime
     * @param $endTime
     * @param $interval
     * @param int $isNotTimeDifference
     * @return array
     */
    function applyPickupTimeInterval($startTime, $endTime, $interval, $isNotTimeDifference = 1)
    {
        $counter = 0;
        $time    = [];
        if ($isNotTimeDifference) {
            $time [] = $this->dateTime->date(
                $this->pickupTimeFormat,
                strtotime($startTime));
        }
        while (strtotime($startTime) <= strtotime($endTime)) {
            $end       = $this->dateTime->date(
                $this->pickupTimeFormat, strtotime('+' . $interval . ' minutes',
                strtotime($startTime
                )));
            $startTime = $this->dateTime->date(
                $this->pickupTimeFormat,
                strtotime('+' . $interval . ' minutes',
                    strtotime($startTime)));
            $counter++;
            if ($startTime == "00:00" || $startTime == "12:00 AM") {
                $startTime = "24:00";
            }
            if (strtotime($startTime) <= strtotime($endTime)) {
                $time[] = $end;
            }
        }

        return $time;
    }
}
