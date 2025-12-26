<?php

namespace AwardWallet\Engine\spg\History;

use AwardWallet\MainBundle\Worker\AsyncProcess\HistoryMapInterface;

class NightMapper implements HistoryMapInterface
{
    /**
     * @return string
     *
     * @param $row
     */
    public static function map(array $row)
    {
        if (RedemptionMapper::map($row) == 'Hotel Stays') {
            if (preg_match('#(\d+)\s+night#ims', $row['Description'], $matches)) {
                return $matches[1];
            }

            if (preg_match('#(\d+)\s+free#ims', $row['Description'], $matches)) {
                return $matches[1];
            }

            if (preg_match('#(\d+)NT#ims', $row['Description'], $matches)) {
                return $matches[1];
            }

            $result = 0;

            if (preg_match('#wkdy\s+(\d+)#ims', $row['Description'], $matches)) {
                $result += intval($matches[1]);
            }

            if (preg_match('#wknd\s+(\d+)#ims', $row['Description'], $matches)) {
                $result += intval($matches[1]);
            }

            if ($result > 0) {
                return $result;
            }

            return 'Other';
        }

        return null;
    }
}
