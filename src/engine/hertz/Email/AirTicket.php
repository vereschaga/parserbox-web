<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\hertz\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "hertz/it-6694446.eml";

    private $detects = [
        'Hertz Gold Plus Rewards 회원번호',
    ];

    private $from = 'hertz.com';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'emailType'  => 'AirTicketKo',
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], $this->from) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $body = preg_replace('/\s+/', ' ', $body);

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['ko'];
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\CarRental $it */
        $it = ['Kind' => 'L'];

        // Number
        $it['Number'] = $this->http->FindSingleNode("//td[contains(., '귀하의 예약번호입니다') and not(descendant::td)]", null, true, '/:\s*([A-Z\d]+)/');
        // PickupDatetime
        $it['PickupDatetime'] = $this->normalize($this->getNode('인수 날짜'));
        // PickupLocation
        $it['PickupLocation'] = $this->getNode('인수 영업소');
        // DropoffDatetime
        $it['DropoffDatetime'] = $this->normalize($this->getNode('반환 날짜'));
        // DropoffLocation
        $it['DropoffLocation'] = $this->getNode('반환 영업소');
        // PickupPhone
        $it['PickupPhone'] = $this->getNode('전화번호');
        // PickupFax
        // PickupHours
        $it['PickupHours'] = $this->getNode('운영시간');
        // DropoffHours
        $it['DropoffHours'] = $this->getNode('운영시간', 2);
        // DropoffPhone
        $it['DropoffPhone'] = $this->getNode('전화번호', 2);
        // RentalCompany
        // CarImageUrl
        $xpath = "//tr[contains(normalize-space(.), '차량 정보')]/following-sibling::tr[descendant::img[contains(@src, 'global/img/ZEUSICAR999')]][1]/";
        $it['CarImageUrl'] = $this->http->FindSingleNode($xpath . "descendant::img[contains(@src, 'global/img/ZEUSICAR999')]/@src");
        // CarModel
        $it['CarModel'] = $this->http->FindSingleNode($xpath . "descendant::tr[count(td)=2]/td[2]/descendant::tr[1]");
        // CarType
        $it['CarType'] = $this->http->FindSingleNode($xpath . "descendant::tr[count(td)=2]/td[2]/descendant::tr[2]");
        // RenterName
        // PromoCode
        // TotalCharge
        $it['TotalCharge'] = $this->getNode('예상 임차비용');
        // Currency
        $it['Currency'] = $this->getNode('편도대여비', 1, '/([A-Z]{3})/');
        // TotalTaxAmount
        // AccountNumbers
        // Status
        // ServiceLevel
        // Cancelled
        // PricedEquips
        // Discount
        // Discounts
        // Fees
        // ReservationDate
        // NoItineraries
        return [$it];
    }

    private function getNode($str, $elem = 1, $re = null)
    {
        return $this->http->FindSingleNode("(//td[contains(normalize-space(.), '" . $str . "') and not(descendant::td)]/following-sibling::td[normalize-space(.)][1])[" . $elem . "]", null, true, $re);
    }

    private function normalize($str)
    {
        $re = [
            '/(\d{4})\s*-\s*(\d{2})\s*-\s*(\d{2})\s+at\s+(\d{1,2}:\d{2})/',
        ];
        $out = [
            '$3.$2.$1, $4',
        ];

        return strtotime(preg_replace($re, $out, $str));
    }
}
