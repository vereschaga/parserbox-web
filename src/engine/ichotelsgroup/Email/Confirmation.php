<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    // most common ihg email with reservations
    // comes in several styles
    public $mailFiles = "ichotelsgroup/it-1.eml, ichotelsgroup/it-10.eml, ichotelsgroup/it-12.eml, ichotelsgroup/it-13.eml, ichotelsgroup/it-15.eml, ichotelsgroup/it-1692630.eml, ichotelsgroup/it-1699849.eml, ichotelsgroup/it-2.eml, ichotelsgroup/it-2008707.eml, ichotelsgroup/it-5.eml, ichotelsgroup/it-6.eml, ichotelsgroup/it-7.eml, ichotelsgroup/it-8.eml, ichotelsgroup/it-9.eml";

    protected $lang;

    protected $dict = [
        "Your Confirmation Number is"     => ["de" => "Ihre Bestätigungsnummer ist"],
        "Your New Confirmation Number is" => ["de" => "Ihre neue Bestätigung ist"],
        "is"                              => ["de" => "ist"],
        "Details"                         => ["de" => "Hotelinformationen"],
        "Check-In"                        => ["de" => "Check-in"],
        "Check-Out"                       => ["de" => "Check-out"],
        "Front Desk"                      => ["de" => "Rezeption"],
        "Number of Guests:"               => ["de" => "Zahl der Gäste:"],
        "adult"                           => ["de" => "Erwachsene"],
        "child"                           => ["de" => "Kind"],
        "Guest Name:"                     => ["de" => "Name des Gastes:"],
        "Number of Rooms:"                => ["de" => "Zahl der Zimmer:"],
        "Rate Type:"                      => ["de" => "Tariftyp:"],
        "Cancellation Policy:"            => ["de" => "Stornierungsbedingungen:"],
        "Room Type:"                      => ["de" => "Zimmertyp:"],
        "Room Rate Per Night:"            => ["de" => "Zimmertarif pro Nacht:"],
        "Tax:"                            => ["de" => "Hotelgebühren:"],
        "Estimated Total Price:"          => ["de" => "Geschätzter Gesamtpreis:"],
    ];

    private static $providers = [
        "ichotelsgroup" => ["from" => [".ihg.com", "InterContinental.com"], "body" => "ihg.com"],
        "gcampaigns"    => ["from" => "@pkghlrss.com", "body" => "passkey.com"],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();
        $this->ParseEmailConfirmation($email);
        $email->setType('ReservationConfirmation' . ucfirst($this->lang));

        if (null !== ($prov = $this->getProvider($parser->getCleanFrom())) && ($prov !== 'ichotelsgroup')) {
            $email->setProviderCode($prov);
        }

        return $email;
    }

    public function ParseEmailConfirmation(Email $email)
    {
        $r = $email->add()->hotel();

        // ConfirmationNumber
        $confirmationNumber = $this->http->FindSingleNode("//*[contains(text(), '" . $this->translate('Your Confirmation Number is') . "')]",
            null, true, "/" . $this->translate("is") . " \#*\s*(\d+)/");

        if (!isset($confirmationNumber)) {
            $confirmationNumber = $this->http->FindSingleNode("//*[contains(text(), '" . $this->translate('Your New Confirmation Number is') . "')]",
                null, true, "/" . $this->translate("is") . " \#*\s*(\d+)/");
        }

        if (!isset($confirmationNumber)) {
            $confirmationNumber = $this->http->FindSingleNode("//*[contains(text(), '" . $this->translate('Online Hotel Confirmation Number is:') . "')]",
                null, true, "/" . $this->translate("is") . ":\s*(\w+\d+)/");
        }

        if (!isset($confirmationNumber)) {
            $confirmationNumber = $this->http->FindSingleNode("//*[contains(text(), '" . $this->translate('Your Confirmation Number is') . "')]/following::span[1]");
        }

        if (!isset($confirmationNumber)) {
            $confirmationNumber = $this->http->FindPreg('#Confirmation\s*\#\s*([\w\-]+)#');
        }

        if (empty($confirmationNumber)) {
            if ($this->http->XPath->query("//text()[normalize-space()='Reservation Information']/following::*[normalize-space()!=''][1][contains(normalize-space(),' Confirmation Number is: Pending')]")->length > 0) {
                $r->general()->noConfirmation();
            } else {
                $r->general()
                    ->confirmation(
                        $this->http->FindSingleNode(
                            "//text()[normalize-space()='Reservation Information']/following::text()[normalize-space()!=''][1][contains(normalize-space(),'Your Online Holidex Confirmation Number is:')]",
                            null,
                            false,
                            "/Your Online Holidex Confirmation Number is: (\d+)/"),
                        'Online Holidex Confirmation Number');
            }
        } else {
            $r->general()->confirmation($confirmationNumber);
        }

        //# Status
        if ($this->http->FindPreg("/(Your reservation is confirmed)/ims")) {
            $r->general()->status('Confirmed');
        }
        $detailBase = $this->http->XPath->query('//table/.//tr/td[1 and contains(.,"' . $this->translate("Details") . '") and not(.//table)]/../following-sibling::*[.//table]');
        $detailBase = ($detailBase->length > 0) ? $detailBase->item(0) : null;
        // CheckInDate
        $checkInDateStr = $this->http->FindSingleNode('//tr/td[contains(.,"' . $this->translate("Check-In") . '") and not(.//td)]/following-sibling::td',
            $detailBase);
        $checkInDate = $this->makeDate($checkInDateStr);

        if (!$checkInDate) {
            $checkInDate = $this->makeDate($this->http->FindSingleNode('//*[contains(text(), "' . $this->translate("Check-In") . '")]',
                null, false, '/' . $this->translate("Check-In") . ':\s+([\d\w\- ,]*\d)/ims'));
        }

        // CheckOutDate
        $checkOutDateStr = $this->http->FindSingleNode('//tr/td[contains(.,"' . $this->translate("Check-Out") . '") and not(.//td)]/following-sibling::td',
            $detailBase);
        $checkOutDate = $this->makeDate($checkOutDateStr);

        if (!$checkOutDate) {
            $checkOutDate = $this->makeDate($this->http->FindSingleNode('//*[contains(text(), "' . $this->translate("Check-In") . '")]',
                null, false, '/' . $this->translate("Check-Out") . ':\s+([\d\w\- ,]*\d)/ims'));
        }
        // check if date is european
        if ($checkInDate && $checkOutDate && ($checkOutDate - $checkInDate > 15 * \AwardWallet\Common\DateTimeUtils::SECONDS_PER_DAY)) {
            if (preg_match("/^(\d+)\/(\d+)\/(\d+)$/", $checkInDateStr, $mIn) && preg_match("/^(\d+)\/(\d+)\/(\d+)$/",
                    $checkOutDateStr, $mOut)
            ) {
                $checkInDate = strtotime($mIn[2] . "/" . $mIn[1] . "/" . $mIn[3]);
                $checkOutDate = strtotime($mOut[2] . "/" . $mOut[1] . "/" . $mOut[3]);
            }
        }
        $r->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate);

        $info = $this->http->XPath->query("//tr[contains(normalize-space(.), '" . $this->translate("Front Desk") . "') and not(.//tr)]");

        if ($info->length == 0) {
            $info = $this->http->XPath->query("//text()[contains(normalize-space(.), '" . $this->translate("Front Desk") . "')]/ancestor::tr[1]");
        }
        $info = $info->length > 0 ? $info->item(0) : null;

        // Address
        $values = $this->http->FindNodes('td[last()]/descendant-or-self::*[count(br) > 1]/text()[not(preceding-sibling::*[contains(., "' . $this->translate("Front Desk") . '")])]',
            $info);

        if (!empty($values)) {
            $address = trim(implode(", ", $values), " ,");
        }
        $values = $this->http->FindNodes('td[last()]//*[contains(text(), "' . $this->translate("Front Desk") . '")]/following-sibling::node()',
            $info);

        if (count($values) == 0) {
            $values = $this->http->FindNodes('//*[contains(text(), "' . $this->translate("Telephone") . '")]/following-sibling::a');
        }

        if (count($values) != 0) {
            $phone = implode($values);
        }
        // HotelName
        $hotelName = $this->http->FindSingleNode('(td[last()]//a)[1]', $info);

        if (empty($address) && empty($hotelName)) {
            $nodes = $this->http->XPath->query("//a[contains(@href, '/hoteldetail?')]");

            if ($nodes->length == 1) {
                $hotelName = \AwardWallet\Common\Parsing\Html::cleanXMLValue($nodes->item(0)->nodeValue);
                $address = implode(', ',
                    array_filter($this->http->FindNodes("parent::*/text()", $nodes->item(0)), "strlen"));
            } else {
                $hotelName = $this->http->FindSingleNode('//*[contains(text(), "' . $this->translate("Hotel Information") . '")]/ancestor::tr[1]/following-sibling::tr[1]/descendant::p[1]/descendant::text()[1]');
                $address = implode(', ',
                    array_filter($this->http->FindNodes('//*[contains(text(), "' . $this->translate("Hotel Information") . '")]/ancestor::tr[1]/following-sibling::tr[1]/descendant::p[1]/descendant::text()[position() > 1]')));

                if (preg_match("#^(.+?), ([+(\d][-. \d)(]{5,}[\d)])$#", $address, $m)) {
                    $address = $m[1];
                    $phone = $m[2];
                }

                if (!isset($hotelName)) {
                    $hotelName = $this->http->FindSingleNode('//img[@alt="Hotel Image"]/ancestor::td[1]/following-sibling::td[1]/descendant::a');
                }

                if (empty($address)) {
                    $address = implode(', ',
                        $this->http->FindNodes('//img[@alt="Hotel Image"]/ancestor::td[1]/following-sibling::td[1]/text()[position()>1]'));
                }
            }

            if ($r->getCheckInDate() && $r->getCheckOutDate() && (!$hotelName || !$address)) {
                $text = text($this->http->Response['body']);

                if (preg_match('#Reservation\s+Information(?:(?s).*)Reservation\s+Information\s+(.*)\n\s*((?s).*?)\n\n#i', $text, $matches) > 0) {
                    if (!$hotelName) {
                        $hotelName = $matches[1];
                    }

                    if (!$address && preg_match('/^\s*([\s\S]{3,})\s+([+(\d][-. \d)(]{5,}[\d)])\s*$/', $matches[2],
                            $m)) {
                        $address = nice($m[1], ',');
                        $phone = $m[2];
                    } elseif (!$address) {
                        $address = nice($matches[2], ',');
                    }
                }
            }
        }
        $r->hotel()
            ->name($hotelName);

        if (isset($address)) {
            $r->hotel()
                ->address($address);
        }

        if (isset($phone)) {
            $r->hotel()
                ->phone($phone);
        }

        // Guests & Kids
        $values = $this->http->FindSingleNode("//tr/td[contains(.,'" . $this->translate("Number of Guests:") . "') and not(.//td)]/following-sibling::td",
            $detailBase);

        if (preg_match('/(\d+)\s+' . $this->translate("adult") . '/', $values, $ar)) {
            $r->booked()->guests($ar[1]);
        } elseif ($guests = $this->http->FindSingleNode('//td[not(.//td) and contains(normalize-space(),"' . $this->translate("Number of Guests") . '")]/following-sibling::td[normalize-space()][1]')) {
            $r->booked()->guests($guests);
        }

        if (preg_match('/(\d+)\s+' . $this->translate("child") . '/', $values, $ar)) {
            $r->booked()->kids($ar[1]);
        }

        $name = $this->http->FindSingleNode("(//tr/td[contains(., '" . $this->translate("Guest Name:") . "') and not(.//td)]/following-sibling::td)[1]",
            $detailBase);

        if (!$name) {
            $name = $this->http->FindSingleNode("(//td[normalize-space(.) = 'Name:' and not(.//td)])[1]/following-sibling::td[1]");
        }

        if (isset($name)) {
            $r->general()->traveller($name);
            $level = $this->http->XPath->query("//text()[contains(., 'Membership Level:')]");

            if ($level->length == 0) {
                $accountNumber = $this->http->FindSingleNode("(//tr/td[contains(., 'Member #:') and not(.//td)]/following-sibling::td)[1]");
            } else {
                $limit = 10;

                do {
                    $level = $level->length > 0 ? $level->item(0) : null;
                    $accountNumber = $this->http->FindSingleNode("parent::*", $level, true,
                        "/" . $name . "\s*(\d+)/ims");
                    $level = $this->http->XPath->query("parent::*", $level);
                    $limit--;
                } while (!isset($accountNumber) && $limit > 0);

                if (isset($accountNumber)) {
                    $r->program()->account($accountNumber, false);
                }
            }
        } else {
            if ($name = $this->http->FindSingleNode("//*[contains(text(), '" . $this->translate("Guest Name:") . "')]/ancestor-or-self::p/descendant::text()[normalize-space()!=''][2]")) {
                $r->general()->traveller($name);
            }
        }

        if (!isset($accountNumber)) {
            $accountNumber = $this->http->FindSingleNode('//*[contains(text(), "Member #:")]/following::span[1]');
        }

        if (!empty($accountNumber)) {
            $r->program()->account($accountNumber, false);
        }

        // Rooms
        $rooms = $this->http->FindSingleNode("//tr/td[contains(.,'" . $this->translate("Number of Rooms:") . "') and not(.//td)]/following-sibling::td",
            $detailBase);

        if (!empty($rooms)) {
            $r->booked()->rooms($rooms);
        }

        $rr = $r->addRoom();
        // RateType
        if ($rt = $this->http->FindSingleNode("//tr/td[contains(.,'" . $this->translate("Rate Type:") . "') and not(.//td)]/following-sibling::td",
            $detailBase)
        ) {
            $rr->setRateType($rt);
        }

        // CancellationPolicy
        $cp = $this->http->FindSingleNode("//tr/td[contains(.,'" . $this->translate("Cancellation Policy:") . "') and not(.//td)]/following-sibling::td", $detailBase);

        if (empty($cp)) {
            $cp = $this->http->FindSingleNode("//text()[normalize-space()='Rules & Restrictions' or contains(normalize-space(),'/ Rules & Restrictions')]/following::text()[normalize-space()][1][normalize-space()='-']/following::text()[normalize-space()][1]");
        }

        if (!empty($cp)) {
            $r->general()->cancellation($cp);
            $this->detectDeadLine($r, $cp);
        }

        // RoomType
        if (!empty($roomType = $this->http->FindSingleNode("//tr/td[(contains(text(),'" . $this->translate("Room Type:") . "') or contains(.,'" . $this->translate("Room Type:") . "')) and not(.//td)]/following-sibling::td",
            $detailBase))
        ) {
            $rr->setType($roomType);
        }
        // RoomTypeDescription
        if (!empty($roomTypeDescription = $this->http->FindSingleNode("//tr/td[contains(.,'Smoking Preference:') and not(.//td)]/following-sibling::td",
            $detailBase))
        ) {
            $rr->setDescription($roomTypeDescription);
        }
        // Cost
        if ($rate = str_ireplace(",", "",
            $this->http->FindSingleNode("//tr/td[contains(.,'" . $this->translate("Room Rate Per Night:") . "') and not(.//td)]/following-sibling::td[2]",
                $detailBase, true, '/([\d,]+\.\d+)/i'))
        ) {
            $rr->setRate($rate);
        } else {
            if ($rate = $this->http->FindSingleNode("//*[contains(text(), '" . $this->translate("Guest(s)") . "')]/ancestor::p[1]/text()[2]",
                null, false, '/(\d+\.\d+)/ims')
            ) {
                $rr->setRate($rate);
            }
        }
        // Taxes
        if (!empty($taxes = str_ireplace(",", "",
            $this->http->FindSingleNode("//tr/td[contains(.,'" . $this->translate("Tax:") . "') and not(.//td)]/following-sibling::td",
                $detailBase, true, '/([\d,]+\.\d+)[ ]*[^\d%]/i')))
        ) {
            $r->price()->tax($taxes);
        }
        // Total
        $values = $this->http->FindSingleNode("//tr/td[contains(.,'" . $this->translate("Estimated Total Price:") . "')  and not(.//td)]/following-sibling::td",
            $detailBase);

        if (preg_match('/([\d,]+\.\d+)/', $values, $ar)) {
            $r->price()->total(str_ireplace(",", "", $ar[1]));
        }
        // Currency
        if (preg_match('/\((\w+)\)/', $values, $ar)) {
            $r->price()->currency($ar[1]);
        }

        return true;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']) && (stripos($headers['subject'], 'InterContinental') !== false || stripos($headers['subject'], 'Holiday Inn') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'check-in') !== false && (strpos($body, 'InterContinental') !== false || strpos($body, 'Holiday Inn') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.ihg.com') !== false || stripos($from, 'InterContinental.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de"];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    protected function detectLang()
    {
        $this->lang = null;

        if (stripos($this->http->Response['body'], "Bestätigung") !== false) {
            $this->lang = 'de';
        }
    }

    protected function translate($s)
    {
        if (!isset($this->lang) || !isset($this->dict[$s][$this->lang])) {
            return $s;
        }

        return $this->dict[$s][$this->lang];
    }

    protected function makeDate($s)
    {
        if (!isset($this->lang)) {
            return strtotime($s);
        }

        if (preg_match("/(\d+) (\w+) (\d{4} [\d:]+ [AP]M)/", $s, $m)) {
            return strtotime($this->dateStringToEnglish($m[1] . " " . $m[2] . $m[3]));
        }

        return strtotime($s);
    }

    private function getProvider($from)
    {
        foreach (self::$providers as $provider => $prop) {
            if ((preg_match("#{$this->opt($prop['from'])}#", $from))
                || $this->http->XPath->query("//a[contains(@href,'{$prop['body']}')]")->length > 0
            ) {
                return $provider;
            }
        }

        return null;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#Buchung muss mindestens (\d+) Tage im Voraus erfolgenWenn Sie Ihre Reservierung um (\d+:\d+(?:\s*[ap]m)?|\d+\s*[ap]m) \(Ortszeit\) am (.+) stornieren, entstehen Ihnen keine Kosten#i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days', $m[2]);
        } elseif (preg_match("#Cancellations received after (\d+:\d+(?:\s*[ap]m)?|\d+\s*[ap]m) [A-Z]{3} (\d+) days prior to your scheduled arrival date \(\d+ hours\) are subject to a cancellation penalty#i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[2] . ' days', $m[1]);
        } elseif (preg_match("#Canceling your reservation before (\d+:\d+(?:\s*[ap]m)?|\d+\s*[ap]m) \(local hotel time\) on \w+, (.+) will result in no charge#i",
                $cancellationText, $m)
            || preg_match("#Canceling your reservation after (\d+:\d+(?:\s*[ap]m)?|\d+\s*[ap]m) \(local hotel time\) on (.+), or failing to show, will result in a charge of#i",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime(str_replace(',', '', $m[2]) . ', ' . $m[1]));
        } elseif (preg_match("#Cancellations made within (?<prior>\d+ hours?) of arrival will forfeit one night’s room and tax#", $cancellationText, $m) // en
            || preg_match('#Cancellations must be made (?<prior>\d+ hours?) prior to the arrival date otherwise a penalty charge#i', $cancellationText, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        } elseif (preg_match("#Each individual reservation can be cancelled free of charge untill (\d{1,2}\.\d{1,2}\.\d{2,4})#i",
            $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        }
        $h->booked()
            ->parseNonRefundable('Canceling your reservation or failing to arrive will result in forfeiture of your deposit. Taxes may apply');
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
