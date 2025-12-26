<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelPayConfirmation extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-49448313.eml";
    private $subjects = ['Hotel Payment Confirmation'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $h->ota()
            ->confirmation($this->http->FindSingleNode('//*[contains(normalize-space(text()),"Booking Id:")]', null, true, '/([A-Z\d]{5,})$/'), "Booking Id");

        $dateReservation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation expected by')]/following::text()[normalize-space()][1]", null, true, '/^(\d+\s\D+\d+[:]\d+\s[\D]{2})/');
        $h->general()
            ->noConfirmation()
            ->date(EmailDateHelper::calculateDateRelative($dateReservation, $this, $parser));

        $h->hotel()
            ->noAddress()
            ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hotel in')]/following::text()[normalize-space()][1]", null, true));

        $checkIn = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Night')]/following::text()[normalize-space()][2]", null, true, '/(\d+\s[\D]{3})\s[-]\s\d+\s[\D]{3}/');
        $checkOut = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Night')]/following::text()[normalize-space()][2]", null, true, '/\d+\s[\D]{3}\s[-]\s(\d+\s[\D]{3})/');

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Room')]", null, true, '/(\d+)\sRoom/'))
            ->guests($this->http->FindSingleNode("//text()[contains(normalize-space(), 'guest')]", null, true, '/(\d+)\sguest/'))
            ->checkIn(EmailDateHelper::calculateDateRelative($checkIn, $this, $parser))
            ->checkOut(EmailDateHelper::calculateDateRelative($checkOut, $this, $parser));

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@makemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'MakeMyTrip') !== false || stripos($headers['from'], 'makemytrip.com') !== false) {
            foreach ($this->subjects as $phrases) {
                if (stripos($headers['subject'], $phrases) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.makemytrip.com')] | //a[contains(@href,'.makemytrip.com')]")->length > 0) {
            if (($this->http->XPath->query('//text()[contains(., "hotel booking is confirmed")]')->length > 0)
                && ($this->http->XPath->query('//text()[contains(., "The PNR will be generated")]')->length > 0)
                && ($this->http->XPath->query('//text()[contains(., "CHECK CONFIRMATION STATUS")]')->length > 0)) {
                return true;
            }
        }

        return false;
    }
}
