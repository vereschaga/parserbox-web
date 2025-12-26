<?php

namespace AwardWallet\Engine\marriott\History;

use AwardWallet\MainBundle\Worker\AsyncProcess\HistoryMapInterface;

class RedemptionMapper implements HistoryMapInterface
{
    /**
     * @return string
     *
     * @param $row
     */
    public static function map(array $row)
    {
        if (
            $row['Type'] == 'reward'
            && (
                (stripos($row['Description'], 'night') !== false && stripos($row['Description'], 'category') !== false)
                || (stripos($row['Description'], 'night') !== false && stripos($row['Description'], 'tier') !== false)
                || preg_match('#travel\s+package#ims', $row['Description'])
            )
        ) {
            return 'Hotel Stays';
        }

        if (
            $row['Type'] == 'reward'
            && (
                preg_match('#frequent\s+flyer\s+miles#ims', $row['Description'])
                || preg_match('#AIRLINE\s+MILEAGE#ims', $row['Description'])
            )
        ) {
            return 'Points Transfers';
        }

        if (
            preg_match('#Room\s+Upgrade#ims', $row['Description'])
        ) {
            return 'Room Upgrade';
        }

        if (
            stripos($row['Description'], 'product') !== false
            && stripos($row['Description'], 'travel') !== false
            && preg_match('#service\s+awards#ims', $row['Description'])
        ) {
            return 'Product, Travel, or Service Awards';
        }

        if (
            preg_match('#Buy\s+Back#ims', $row['Description'])
        ) {
            return 'Elite Status Buy Back';
        }

        if (
            $row['Type'] == 'bonus'
            && (
                preg_match('#POINTS\s+TRANSFERRED#ims', $row['Description'])
            )
        ) {
            return 'Transferring Points';
        }

        return 'Other';
    }
}
