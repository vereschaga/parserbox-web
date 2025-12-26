<?php

namespace AwardWallet\Engine\spg\History;

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
            if (preg_match('#(CAT\s+\d+)#ims', $row['Description'], $matches)) {
                return $matches[1];
            } else {
                return 'Other';
            }
        }

        return null;
    }
}
