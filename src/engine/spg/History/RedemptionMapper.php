<?php

namespace AwardWallet\Engine\spg\History;

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
            $row['Type'] == 'award'
            && stripos($row['Description'], 'D.D.') !== false
        ) {
            return 'Airline Direct Deposit';
        }

        if (
            $row['Type'] == 'award'
            && (
                preg_match('#delta\s+skymiles#ims', $row['Description'])
                || preg_match('#delta\s+flyer\s+mile#ims', $row['Description'])
                || preg_match('#alaska\s+mileage#ims', $row['Description'])
                || preg_match('#lufthansa\s+miles#ims', $row['Description'])
                || preg_match('#lufthansa\s+transfer#ims', $row['Description'])
                || preg_match('#aadvantage#ims', $row['Description'])
                || preg_match('#all\s+nippon#ims', $row['Description'])
                || preg_match('#aeroplan#ims', $row['Description'])
                || preg_match('#lanpass#ims', $row['Description'])
                || preg_match('#alitalia#ims', $row['Description'])
                || preg_match('#MilleMiglia#ims', $row['Description'])
                || preg_match('#HAWAIIAN\s+AIR\s+MILES#ims', $row['Description'])
                || preg_match('#ETIHAD\s+MILES#ims', $row['Description'])
                || preg_match('#KRISFLYER#ims', $row['Description'])
                || preg_match('#BRITISH\s+AIRWAYS#ims', $row['Description'])
                || preg_match('#VIRGIN\s+ATLANTIC\s+AIRLINES#ims', $row['Description'])
                || preg_match('#aeromexico#ims', $row['Description'])
                || preg_match('#air\s*berlin#ims', $row['Description'])
                || preg_match('#topbonus#ims', $row['Description'])
                || preg_match('#air\s*china#ims', $row['Description'])
                || preg_match('#phoenix\s*miles#ims', $row['Description'])
                || preg_match('#air\s*new\s*zealand#ims', $row['Description'])
                || preg_match('#airpoints#ims', $row['Description'])
                || preg_match('#nippon\s+airways#ims', $row['Description'])
                || preg_match('#ana\s*mileage#ims', $row['Description'])
                || preg_match('#british\s*airways#ims', $row['Description'])
                || preg_match('#executive\s*club#ims', $row['Description'])
                || preg_match('#china\s*eastern#ims', $row['Description'])
                || preg_match('#eastern\s*miles#ims', $row['Description'])
                || preg_match('#emirates#ims', $row['Description'])
                || preg_match('#skywards#ims', $row['Description'])
                || preg_match('#klm#ims', $row['Description'])
                || preg_match('#flying\s*blue#ims', $row['Description'])
                || preg_match('#gol\s*airlines#ims', $row['Description'])
                || preg_match('#hainan\s*airlines#ims', $row['Description'])
                || preg_match('#fortune\s*wings#ims', $row['Description'])
                || preg_match('#japan\s*air#ims', $row['Description'])
                || preg_match('#jmb#ims', $row['Description'])
                || preg_match('#jet\s*airways#ims', $row['Description'])
                || preg_match('#qatar\s*air#ims', $row['Description'])
                || preg_match('#saudi.*air#ims', $row['Description'])
                || preg_match('#singapore.*air#ims', $row['Description'])
                || preg_match('#thai.*air#ims', $row['Description'])
                || preg_match('#united.*air#ims', $row['Description'])
                || preg_match('#united.*mile#ims', $row['Description'])
                || preg_match('#us\s+air#ims', $row['Description'])
                || preg_match('#us\s+.*dividend#ims', $row['Description'])
                || preg_match('#virgin\s+australia#ims', $row['Description'])
                || preg_match('#virgin\s+.*velocity#ims', $row['Description'])
                || preg_match('#virgin\s+atlantic#ims', $row['Description'])
                || preg_match('#virgin\s+.*flying\s*club#ims', $row['Description'])
            )
        ) {
            return 'Transfer';
        }

        if (
            $row['Type'] == 'award'
            && $row['Description'] == 'INTERNAL SPG POINT TRANSFER'
        ) {
            return 'Internal spg point transfer';
        }

        if (
            $row['Type'] == 'award'
            && stripos($row['Description'], 'SPG FLIGHTS AWARD') !== false
        ) {
            return 'Flights Award';
        }

        if (
            $row['Type'] == 'award'
            && stripos($row['Description'], '$') !== false
            && (
                preg_match('#gift\s+cert#ims', $row['Description'])
                || preg_match('#gift\s+card#ims', $row['Description'])
            )
        ) {
            return 'Gift Cards and Certificates';
        }

        if (
            $row['Type'] == 'award'
            && (
                preg_match('#\bCAT\s+\w#ims', $row['Description'])
                || preg_match('#(FREE\s+\NIGHT|WKND|WEEKDAY|WEEKEND)#ims', $row['Description'])
            )
        ) {
            return 'Hotel Stays';
        }

        return 'Other';
    }
}
