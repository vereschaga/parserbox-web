<?php

namespace AwardWallet\Engine\marriott\History;

use AwardWallet\MainBundle\Worker\AsyncProcess\HistoryMapInterface;

class EarningMapper implements HistoryMapInterface
{
    /**
     * @return string
     *
     * @param $row
     */
    public static function map(array $row)
    {
        if (
            $row['Type'] == 'event'
            && (
                (stripos($row['Description'], 'night') !== false && stripos($row['Description'], 'category') !== false)
                || (stripos($row['Description'], 'night') !== false && stripos($row['Description'], 'tier') !== false)
                || preg_match('#travel\s+package#ims', $row['Description'])
            )
        ) {
            return 'Events';
        }

        if (
            stripos($row['Description'], 'point') !== false
            && stripos($row['Description'], 'transfer') !== false
        ) {
            return 'Point Transfer';
        }

        if (
            preg_match('#REWARD\s+ADJUSTMENT#ims', $row['Description'])
        ) {
            return 'Reward Adjustment';
        }

        if (
            $row['Type'] == 'bonus'
            && stripos($row['Description'], 'Visa') !== false
        ) {
            return 'Points earned with credit cards';
        }

        if (
            stripos($row['Description'], 'MEGABONUS') !== false
        ) {
            return 'Megabonus Promotions';
        }

        if (
            preg_match('#RETAIL\s+MALL#ims', $row['Description'])
        ) {
            return 'Retail Malls';
        }

        if (
            preg_match('#POINT\s+PURCHASE#ims', $row['Description'])
        ) {
            return 'Points Purchase';
        }

        if (
            $row['Type'] == 'bonus'
            && stripos($row['Description'], 'airport') !== false
            && stripos($row['Description'], 'bonus') !== false
        ) {
            return 'Airport bonuses';
        }

        if (
            stripos($row['Description'], 'Twitter') !== false
        ) {
            return 'Twitter earnings';
        }

        if (
            ($row['Type'] == 'stay' || $row['Type'] == 'hotel stay')
        ) {
            return 'Hotel Stays';
        }

        return 'Other';
    }
}
