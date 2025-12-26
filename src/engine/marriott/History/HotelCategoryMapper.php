<?php

namespace AwardWallet\Engine\marriott\History;

use AwardWallet\MainBundle\Worker\AsyncProcess\HistoryMapInterface;

class HotelCategoryMapper implements HistoryMapInterface
{
    /**
     * @return string
     *
     * @param $row
     */
    public static function map(array $row)
    {
        if (RedemptionMapper::map($row) == 'Hotel Stays') {
            if (preg_match('#((category|tier)\s+\d+)#ims', $row['Description'], $matches)) {
                return $matches[1];
            } else {
                return 'Other';
            }
        }

        return null;
    }
}
