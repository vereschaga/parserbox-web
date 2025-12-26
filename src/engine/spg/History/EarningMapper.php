<?php

namespace AwardWallet\Engine\spg\History;

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
            $row['Type'] == 'stay'
            && preg_match('#\d\d\/\d\d\/\d\d\d\d#ims', $row['Description'])
        ) {
            return 'Hotel Stays';
        }

        if (
            $row['Type'] == 'award stay'
            && preg_match('#\d\d\/\d\d\/\d\d\d\d#ims', $row['Description'])
        ) {
            return 'Award Stays';
        }

        if (
            $row['Type'] == 'bonus'
            && stripos($row['Description'], 'SPG AX') !== false
        ) {
            return 'SPG and Amex';
        }

        if (
            $row['Type'] == 'bonus'
            && preg_match('#DL\s+PTS\s+\w\w\w#ims', $row['Description'])
        ) {
            return 'SPG & Delta Crossover Rewards';
        }

        if (
            $row['Type'] == 'bonus'
            && preg_match('#Starpoints|Bonus|Make a green Choice|Profit|Goodwill|Double play/#ims', $row['Description'])
        ) {
            return 'Hotel Bonuses';
        }

        if (
            $row['Type'] == 'bonus'
            && preg_match('#\d\d\/\d\d\/\d\d\d\d#ims', $row['Description'])
            && preg_match('# UBER[\-\s]*STAY#ims', $row['Description'])
        ) {
            return 'Uber-Stay Bonus';
        }

        if (
            $row['Type'] == 'bonus'
            && preg_match('#SPG\s+DIGITAL\s+BOOKING|SECURITY\s+QUESTION\s+SETUP\s+BONUS|DASHBOARD#ims', $row['Description'])
        ) {
            return 'Promos';
        }

        if (
            $row['Type'] == 'bonus'
            && preg_match('#Purchased\s+Points#ims', $row['Description'])
        ) {
            return 'Purchased Points';
        }

        if (
            $row['Type'] == 'uber'
        ) {
            return 'Uber';
        }

        if (
            $row['Type'] == 'award'
            && preg_match('#INTERNAL\s+SPG\s+POINT\s+TRANSFER#ims', $row['Description'])
        ) {
            return 'Internal SPG point transfer';
        }

        if (
            $row['Type'] == 'food & beverage'
        ) {
            return 'Food & Beverage';
        }

        if (
            $row['Type'] == 'event'
            && preg_match('#catering|group\s+rooms#ims', $row['Description'])
            && preg_match('#\d\d\/\d\d\/\d\d\d\d#ims', $row['Description'])
        ) {
            return 'Food & Beverage';
        }

        return 'Other';
    }
}
