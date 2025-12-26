<?php

namespace AwardWallet\Engine\mandarinoriental\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "mandarinoriental/it-104488549.eml, mandarinoriental/it-182704774.eml, mandarinoriental/it-292688557.eml, mandarinoriental/it-29980875.eml, mandarinoriental/it-32009415.eml, mandarinoriental/it-363464541.eml, mandarinoriental/it-45293255.eml, mandarinoriental/it-634507248-es.eml, mandarinoriental/it-78379577.eml, mandarinoriental/it-95067760.eml, mandarinoriental/it-676057691-es.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'ARRIVAL DATE'        => ['ARRIVAL DATE', 'ARRIVAL', 'Arrival Date'],
            'DEPARTURE DATE'      => ['DEPARTURE DATE', 'DEPARTURE', 'Departure Date'],
            'GUEST NAME'          => ['GUEST NAME', 'Guest Name'],
            'RESERVATION NUMBER'  => ['RESERVATION NUMBER', 'Reservation Number'],
            'CANCELLATION NUMBER' => ['CANCELLATION NUMBER', 'Cancellation Number'],
            'NUMBER OF GUESTS'    => ['NUMBER OF GUESTS', 'GUESTS', 'Number of Guests'],
            'Adults'              => ['Adults', 'Adult'],
            'Children'            => ['Children', 'Child'],
            'ROOM TYPE'           => ['ROOM TYPE', 'Room Type', 'DAILY ROOM TYPE', 'ROOM PREFERENCES'],
            'ROOM RATE'           => ['ROOM RATE', 'Room Rate', 'DAILY ROOM RATE'],
            'CHECK IN'            => ['CHECK IN', 'Check In'],
            'CHECK OUT'           => ['CHECK OUT', 'Check Out'],
            'TOTAL CHARGE'        => ['TOTAL CHARGE', 'Total', 'Grand Total', 'GRAND TOTAL'],
            'Yours Sincerely'     => ['Yours Sincerely', 'Yours Sincerely,'],
        ],
        'es' => [
            'ARRIVAL DATE'        => ['FECHA DE LLEGADA', 'Fecha de llegada'],
            'DEPARTURE DATE'      => ['FECHA DE SALIDA', 'Fecha de salida'],
            'GUEST NAME'          => ['NOMBRE DE HUÉSPED', 'Nombre del cliente'],
            'RESERVATION NUMBER'  => ['NUMERO DE RESERVA', 'Número de reserva'],
            //'CANCELLATION NUMBER' => [''],
            'CANCELLATIONS'       => ['POLÍTICA DE CANCELACIÓN', 'Cancelaciones'],
            'NUMBER OF GUESTS'    => ['CANTIDAD DE HUÉSPEDES', 'Número de personas'],
            'Adults'              => ['Adultos', 'Adulto'],
            'Children'            => ['Niños', 'Niño'],
            'ROOM TYPE'           => ['TIPO DE HABITACIÓN', 'Tipo de habitación'],
            'ROOM RATE'           => ['TARIFA', 'Tarifa de habitación'],
            'CHECK IN'            => ['HORA DE CHECK IN', 'Entrada'],
            'CHECK OUT'           => ['HORA DE CHECK OUT', 'Salida'],
            'TOTAL CHARGE'        => ['GRAN TOTAL', 'Total'],
            'Yours Sincerely'     => ['Atentamente', 'Atentamente,'],
        ],
    ];
    private $subjects = [
        'en' => ['Your Upcoming Stay at', 'Your Reservation Confirmation', 'Reservation Cancellation for'],
    ];

    private $subject;

    private $patterns = [
        'time'  => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*del mediodía)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
        'phone' => '[+(\d][-+.  \'\d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992    |    +90 '252 311 18 88
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->assignLang();
        $email->setType('Flight' . ucfirst($this->lang));
        $this->parseHtml($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Mandarin Oriental') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(normalize-space(),"Mandarin Oriental")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    private function parseHtml(Email $email): void
    {
        $hotelEmails = ['moprg-reservations@mohg.com', 'modoh-reservations@mohg.com', 'mozrh-reservations@mohg.com'];
        $xpathBold = '(self::b or self::strong)';

        $h = $email->add()->hotel();

        $travellers = [];
        $kidsCount = null;
        $guestNameRows = $this->http->XPath->query("//node()[{$this->eq($this->t('GUEST NAME'))}]/following::text()[normalize-space()][position() < 10][not(contains(normalize-space(), 'Member ID') or contains(normalize-space(), 'NUMERO DE RESERVA'))]");

        if ($guestNameRows->length == 0) {
            $guestNameRows = $this->http->XPath->query("//text()[normalize-space()='Reservation Details']/following::text()[normalize-space()][position() < 5]");
        }

        foreach ($guestNameRows as $gnRow) {
            $guestNameText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $gnRow));

            if (preg_match("/(?: NUMBER|{$this->opt($this->t('RESERVATION NUMBER'))})/iu", $guestNameText)) {
                break;
            }

            if (preg_match_all("/^[ ]*(?:{$this->opt(['Mr. & Mrs.', 'Mr.', 'Mrs.', 'Ms.', 'Sra.', 'Sr.'])})?\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[}{ ]*(?:\((\d{1,3}) Children\))?[}{ &]*$/imu", $this->htmlToText($guestNameText), $travellerMatches)) {
                // Mr. Andrew Shapiro }    |    Mr. Andrew Shapiro (3 Children)
                $travellers = array_merge($travellers, $travellerMatches[1]);
                $travellerMatches[2] = array_filter($travellerMatches[2]);

                if (count($travellerMatches[2])) {
                    $kidsCount += array_sum($travellerMatches[2]);
                }
            } else {
                //$travellers = [];
                break;
            }
        }

        if (count($travellers)) {
            $h->general()->travellers($travellers);
        }

        $confs = [];
        $conf = $this->getNode($this->t('RESERVATION NUMBER'), '/^[-A-Z\d]{5,}$/');

        if (empty($conf)) {
            $this->http->FindSingleNode("//span[{$this->eq($this->t('RESERVATION NUMBER'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]",
                null, true, '/^[-A-Z\d]{5,}$/');
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('RESERVATION NUMBER'))}]/following::text()[normalize-space()][1]", null, true, '/^([-A-Z\d]{5,})\s*$/');
        }

        if (empty($conf)) {
            $confs = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('RESERVATION NUMBER'))}]/ancestor::tr[1]/descendant::text()[normalize-space()][contains(normalize-space(), '-')]", null, '/^([-A-Z\d]{5,})\s*$/'));
        }

        if (empty($conf)) {
            for ($i = 1; $i < 10; $i++) {
                $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('RESERVATION NUMBER'))}]/following::text()[normalize-space()][{$i}]",
                    null, true, '/^([-A-Z\d]{5,})\s*\/\s*$/');

                if (empty($conf)) {
                    break;
                } else {
                    $confs[] = $conf;
                }
            }
        } else {
            $confs[] = $conf;
        }

        if (!empty($confs)) {
            foreach ($confs as $c) {
                $h->general()
                    ->confirmation($c);
            }
        }

        // checkInDate
        $dateCheckIn = null;
        $xpathCheckInDate = "descendant::tr[ *[normalize-space()][1][not(.//tr)][{$this->contains($this->t('ARRIVAL DATE'))}] ][1]/*[normalize-space()][1][ descendant::br and descendant::text()[normalize-space()][2] ]";

        if ($this->http->XPath->query($xpathCheckInDate)->length === 1) {
            $checkInDateText = $this->htmlToText($this->http->FindHTMLByXpath($xpathCheckInDate));

            if (preg_match("/^[ ]*{$this->opt($this->t('ARRIVAL DATE'))}[: ]*\n+[ ]*(.*\d.*?)[ ]*$/m", $checkInDateText, $m)) {
                $dateCheckIn = strtotime($this->normalizeDate($m[1]));
            }
        }

        if (empty($dateCheckIn)) {
            $checkInDate = $this->getNode($this->t('ARRIVAL DATE'), '/.*\d.*/s', 'eq') ?? $this->http->FindSingleNode("//span[{$this->eq($this->t('ARRIVAL DATE'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]") ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('ARRIVAL DATE'))}][not(ancestor::*[{$xpathBold}])]/following::text()[normalize-space()][1][ancestor::*[{$xpathBold}]]", null, true, '/.*\d.*/s');

            if (preg_match('/\d/', $checkInDate)) {
                $dateCheckIn = strtotime($checkInDate);
            }
        }

        // checkOutDate
        $dateCheckOut = null;
        $xpathCheckOutDate = "descendant::tr[ *[normalize-space()][1][not(.//tr)][{$this->contains($this->t('DEPARTURE DATE'))}] ][1]/*[normalize-space()][1][ descendant::br and descendant::text()[normalize-space()][2] ]";

        if ($this->http->XPath->query($xpathCheckOutDate)->length === 1) {
            $checkOutDateText = $this->htmlToText($this->http->FindHTMLByXpath($xpathCheckOutDate));

            if (preg_match("/^[ ]*{$this->opt($this->t('DEPARTURE DATE'))}[: ]*\n+[ ]*(.*\d.*?)[ ]*$/m", $checkOutDateText, $m)) {
                $dateCheckOut = strtotime($this->normalizeDate($m[1]));
            }
        }

        if (empty($dateCheckOut)) {
            $checkOutDate = $this->getNode($this->t('DEPARTURE DATE'), '/.*\d.*/s') ?? $this->http->FindSingleNode("//span[{$this->eq($this->t('DEPARTURE DATE'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]") ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('DEPARTURE DATE'))}][not(ancestor::*[{$xpathBold}])]/following::text()[normalize-space()][1][ancestor::*[{$xpathBold}]]", null, true, '/.*\d.*/s');

            if (preg_match('/\d/', $checkOutDate)) {
                $dateCheckOut = strtotime($checkOutDate);
            }
        }

        if (!empty($this->http->FindSingleNode("//text()[contains(normalize-space(), 'has been cancelled')]"))) {
            $h->general()
                ->cancelled()
                ->status('cancelled')
                ->cancellationNumber($this->http->FindSingleNode("//text()[{$this->contains($this->t('CANCELLATION NUMBER'))}]/following::text()[normalize-space()][1]"));
        }

        if ($h->getStatus() !== 'cancelled') {
            $roomRate = null;
            $rateText = '';
            $rateDates = [];
            $rateRowsHtml = $this->http->FindHTMLByXpath("//node()[{$this->starts($this->t('ROOM RATE'))}]/ancestor::*[ descendant::node()[starts-with(normalize-space(),'TOTAL CHARGE')] ][1]") // it-32009415.eml
                ?? $this->http->FindHTMLByXpath("//span[{$this->starts($this->t('ROOM RATE'))} and following-sibling::node()[normalize-space()]]/ancestor::td[1]") // it-78379577.eml
            ;

            $rateRowsText = $this->htmlToText($rateRowsHtml);
            $rateRowsText = preg_replace("/[\s\S]*{$this->opt($this->t('ROOM RATE'))}.*\n+[ ]*([\s\S]+?)[ ]*\n+[ ]*TOTAL CHARGE[\s\S]*/", '$1', $rateRowsText);
            $rateRows = preg_split('/[ ]*\n+[ ]*/', $rateRowsText);

            if (count(array_filter($rateRows)) === 0) {
                $rateRows = $this->http->FindNodes("//span[starts-with(normalize-space(), 'Room Rate')]/ancestor::td[1]/descendant::tr[normalize-space()][not(contains(normalize-space(), 'Room Rate'))]");
            }

            foreach ($rateRows as $rateRow) {
                if (preg_match("/\([ ]*(?<date>[[:alpha:]]{3,}\s+\d{1,2}[ ]*,[ ]*\d{4})[ ]*\)$/u", $rateRow, $m) // March 20, 2020
                    || preg_match("/^[-[:alpha:]]+[ ]*,[ ]*(?<date>[[:alpha:]]{3,}\s+\d{1,2}[ ]*,[ ]*\d{4})\s/u", $rateRow, $m) // Monday, July 19, 2021
                ) {
                    $rateDates[] = $m['date'];
                }
                $rateText .= "\n" . preg_replace('/^Complimentary\s*\(/i', '0 (', $rateRow);
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $roomRate = $rateRange;
            }

            // it-29980875.eml
            if ($roomRate === null || $roomRate === '') {
                $roomRate = $this->getNode($this->t('ROOM RATE'));
            }

            if ($roomRate === null || $roomRate === '') {
                $roomRate = implode(', ', $this->http->FindNodes("//text()[(contains(normalize-space(),'DAILY ROOM RATE'))]/following::table[1]/descendant::tr"));
            }

            $roomType = $this->getNode($this->t('ROOM TYPE'))
                ?? $this->http->FindSingleNode("//span[{$this->eq($this->t('ROOM TYPE'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]/ancestor::p[1]")
                ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('ROOM TYPE'))}]/following::text()[normalize-space()][1]")
            ;

            if ($roomRate !== null && $roomRate !== '' || $roomType) {
                $room = $h->addRoom();

                if ($roomRate !== null && $roomRate !== '') {
                    $room->setRate($roomRate);
                }

                if ($roomType) {
                    $room->setType(preg_replace("/({$this->opt($this->t('ROOM TYPE'))})/", "", $roomType));
                }

                if (preg_match("/\s(\d)[x]\s*(?:\w+)?\s*Rooms/", $roomType, $m)) {
                    $roomCount = $h->getRoomsCount();
                    $roomCount += intval($m[1]);
                    $h->booked()
                        ->rooms($roomCount);
                }

                if (!empty($roomType) && preg_match("/^\s*(\d+)x (.+)/", $roomType, $m)) {
                    $room->setType($m[2]);

                    for ($i = 1; $i < $m[1]; $i++) {
                        $h->addRoom()->fromArray($room->toArray());
                    }
                }
            }
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'for the first night with our compliments')]")->length === 1) {
            $h->setFreeNights(1);

            if (empty($h->getRooms())) {
                $rateRows = $this->http->FindNodes("//span[starts-with(normalize-space(), 'Room Rate')]/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Room Rate'))]");
                $rate = [];

                foreach ($rateRows as $rateRow) {
                    if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/", $rateRow, $m)) {
                        if ($m['total'] == 0) {
                            $rate[] = '0';
                        } else {
                            $rate[] = $m['total'] . ' ' . $m['currency'];
                        }
                    }
                }
                $h->addRoom()->setRates($rate);
            }
        }

        if (isset($rateDates) && count($rateDates)) {
            if (empty($h->getCheckInDate())) {
                // it-45293255.eml
                $dateCheckIn = strtotime($rateDates[0]);
            }

            if (empty($h->getCheckOutDate())) {
                $dateCheckOut = strtotime('+1 day', strtotime(array_pop($rateDates)));
            }
        }

        $hotelInfo = $this->http->FindNodes("//text()[{$this->eq("RESERVATION NUMBER")}]/ancestor::td/following-sibling::td//text()[{$this->starts('CHECK IN')}]/preceding-sibling::node()[normalize-space(.)!='']");

        if (empty($hotelInfo)) {
            $hotelInfo = $this->http->FindNodes("//node()[{$this->starts('CHECK IN')}]/preceding-sibling::span[normalize-space(.)!=''][last()]/descendant::text()[normalize-space(.)!='']");
        }

        if (empty($hotelInfo)) {
            $hotelInfo = $this->http->FindNodes("//text()[{$this->eq("RESERVATION NUMBER")}]/ancestor::td/following-sibling::td[.//text()[{$this->eq('CHECK IN')}]][descendant::text()[normalize-space()][1][{$this->contains(['MANDARIN', 'mandarin', 'Mandarin'])}]]//text()[normalize-space(.)!='']");

            if (!empty($hotelInfo)) {
                $hotelInfo = explode("\n", preg_replace("/\n\s*CHECK IN\s*\n[\s\S]+/", '', implode("\n", $hotelInfo)));
            }
        }

        if (empty($hotelInfo)) {
            // it-32009415.eml
            $hotelInfoHtml = $this->http->FindHTMLByXpath("descendant::a[contains(normalize-space(),'Map & Directions')][1]/ancestor::td[1][{$this->contains(['#E4E3E1', '#e4e3e1', 'rgb(228, 227, 225)', 'rgb(228,227,225)'], '@style')}]");
            $hotelInfoText = $this->htmlToText($hotelInfoHtml);
            $hotelInfo = preg_match("/\n{2}((?:.+\n){2,})\s*Map & Directions/u", $hotelInfoText, $m) ? preg_split('/[ ]*\n[ ]*/', trim($m[1])) : [];
            $hotelInfo = array_filter($hotelInfo, function ($item) {
                return !preg_match('/^.*(?:@|\bwww\.|\.com\b).*$/i', $item);
            });
        }

        if (empty($hotelInfo)) {
            $hotelInfo = $this->http->FindNodes("//text()[contains(normalize-space(.), 'We look forward to welcoming you soon')]/following-sibling::node()[normalize-space(.)][not(name()='a')]");
        }

        if (empty($hotelInfo)) {
            // it-78379577.eml
            $xpathHotelInfo = "descendant::text()[{$this->eq($hotelEmails)}]/ancestor::td[1][ descendant::text()[starts-with(normalize-space(),'+') or ancestor::*[contains(@class,'phone')]] ][not(contains(., 'please call'))]";
            $hotelName_temp = $this->http->FindSingleNode($xpathHotelInfo . "/descendant::text()[normalize-space()][1]");

            if ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1
                || $this->http->XPath->query("//img[{$this->contains($hotelName_temp, '@alt')}]")->length > 0
            ) {
                $hotelInfoHtml = $this->http->FindHTMLByXpath($xpathHotelInfo);
                $hotelInfoText = $this->htmlToText($hotelInfoHtml);

                if (preg_match("/^\s*(?<name>.{3,}?)[ ]*\n+(?<address>([ ]*.{3,}[ ]*\n+){1,3}?)[ ]*(?<other>(?:{$this->patterns['phone']}|{$this->opt($hotelEmails)})[\s\S]*)$/", $hotelInfoText, $m)) {
                    $hotelInfo = preg_split('/[ ]*\n[ ]*/', $m['name'] . "\n" . preg_replace('/\s+/', ' ', trim($m['address'])) . "\n" . $m['other']);
                } else {
                    $hotelInfo = [];
                }
                $hotelInfo = array_filter($hotelInfo, function ($item) {
                    return !preg_match('/^.*(?:@|\bwww\.|\.com\b).*$/i', $item);
                });
            }
        }

        if (empty($hotelInfo)) {
            $hotelTemp = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Email')]/ancestor::tr[1]/following::tr[string-length()>5][contains(normalize-space(), '+') and contains(normalize-space(), '@')][1]/descendant::text()[normalize-space()]"));

            if (empty($hotelTemp)) {
                $hotelTemp = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Mandarin Oriental,') or starts-with(normalize-space(), 'Mandarin Oriental Palace,')]/ancestor::tr[1][contains(normalize-space(), '@')]/descendant::text()[normalize-space()]"));
            }

            if (preg_match("/^(?<name>.+)\n(?<address>(?:.+\n){1,2})(?<phone>[+][\d\(\)\-\s]+)\n/", $hotelTemp, $m)) {
                $hotelInfo[] = $m['name'];
                $hotelInfo[] = str_replace("\n", " ", $m['address']);
                $hotelInfo[] = $m['phone'];
            }
        }

        if (empty($hotelInfo)) {
            //it-95067760.eml
            $hotelName = $this->http->FindSingleNode("//node()[{$this->eq($this->t('Yours Sincerely'))}]/preceding::text()[normalize-space()][string-length()>1][1]/ancestor::tr[1]/descendant::span[normalize-space()][1]")
                ?? $this->http->FindSingleNode("//text()[normalize-space()='GUEST NAME']/ancestor::table[1]/descendant::td[contains(normalize-space(),'@')]/descendant::text()[normalize-space()][1]");

            $hotelTemp = implode("\n", $this->http->FindNodes("//node()[{$this->eq($this->t('Yours Sincerely'))}]/preceding::text()[normalize-space()][string-length()>1][1]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), '@'))]"));

            if (empty($hotelTemp)) {
                $hotelTemp = implode("\n", $this->http->FindNodes("//text()[normalize-space()='GUEST NAME']/ancestor::table[1]/descendant::td[contains(normalize-space(), '@')]/descendant::text()[normalize-space()][not(contains(normalize-space(), '@'))]"));
            }

            if ($hotelName && preg_match("/{$hotelName}\n(.+)\n({$this->patterns['phone']})\s*\D*$/su", $hotelTemp, $m)) {
                $hotelInfo[] = $hotelName;
                $hotelInfo[] = str_replace("\n", ",", $m[1]);
                $hotelInfo[] = $m[2];
            }
        }

        if (empty($hotelInfo)) {
            $hotelTemp = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Reservation Details']/ancestor::tr[1]/following::tr[1]/descendant::text()[contains(normalize-space(), '(General Line)')]/ancestor::td[1]/descendant::text()[normalize-space()]"));

            if (preg_match("/^(?<address>(?:.+\n){1,3})(?<phone>[+][\d\(\)\s]+)\s\(/u", $hotelTemp, $m)) {
                if (preg_match("/(?:Your upcoming stay at)\s*(.+)/", $this->subject, $match)) {
                    $hotelInfo[] = $match[1];
                }

                $hotelInfo[] = str_replace("\n", " ", $m['address']);
                $hotelInfo[] = $m['phone'];
            }
        }

        if (empty($hotelInfo)) {
            $hotelTemp = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('CHECK IN'))}]/preceding::text()[normalize-space()][1][contains(normalize-space(), '@')]/preceding::text()[normalize-space()][1]/ancestor::span[1]/descendant::text()[normalize-space()]"));

            if (empty($hotelTemp)) {
                $hotelTemp = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Yours Sincerely,')]/following::text()[starts-with(normalize-space(), 'Mandarin')][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));
            }

            if (preg_match("/^(?<hotelName>.+)\n(?<address>(?:.+\n){1,4})(?<phone>[+][\d\s\-]+)/", $hotelTemp, $m)) {
                $hotelInfo[] = $m['hotelName'];
                $hotelInfo[] = str_replace("\n", " ", $m['address']);
                $hotelInfo[] = $m['phone'];
            }
        }

        if (empty($hotelInfo)) {
            $this->logger->debug('Hotel address: Type 1 (noAddress)');

            //We are excited to welcome you as our guest at Mandarin Oriental, Miami.
            $hotel = $this->http->FindSingleNode("descendant::text()[{$this->starts(['We are excited to welcome you as our guest at', 'We look forward to welcoming you at'])}]",
                null, false, "/{$this->opt(['We are excited to welcome you as our guest at', 'We look forward to welcoming you at'])}\s*(.+?)\./");

            if (empty($hotel) && preg_match("/(?:Reservation Cancellation for)\s*(.+)/", $this->subject, $m)) {
                $hotel = $m[1];
            }

            if (!empty($hotel)) {
                $h->hotel()->name($hotel)->noAddress();
            }
        } else {
            $this->logger->debug(var_export($hotelInfo, true));
            $this->logger->debug('Hotel address: Type 2');
            $h->hotel()->name(array_shift($hotelInfo));

            $last = array_pop($hotelInfo);

            if (preg_match("/.+@.+/", $last)) {
                $last = array_pop($hotelInfo);
            }
            // phone
            $phone = preg_match("/^({$this->patterns['phone']})/", $last, $m) ? str_replace("'", '', $m[1]) : null;
            $h->hotel()->phone($phone);

            $address = implode(', ', $hotelInfo);

            if (!empty($address)) {
                $h->hotel()->address($address);
            }
        }

        $numberOfGuests = $this->http->FindSingleNode("//span[{$this->eq($this->t('NUMBER OF GUESTS'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]") ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('NUMBER OF GUESTS'))}]/following::text()[normalize-space()][1][ancestor::*[{$xpathBold}]]");

        if (preg_match("/^\d+$/", $numberOfGuests)) {
            $numberOfGuests = $this->http->FindSingleNode("//span[{$this->eq($this->t('NUMBER OF GUESTS'))}]/following::text()[normalize-space()][1][not(ancestor::*[(self::b or self::strong)])]/ancestor::p[1]");
        }
        // guestCount
        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->tPlusEn('Adults'))}/iu", $numberOfGuests, $m)) {
            $h->booked()->guests($m[1]);
        }

        // kidsCount
        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->tPlusEn('Children'))}/iu", $numberOfGuests, $m)) {
            $h->booked()->kids($m[1]);
        } elseif ($kidsCount !== null) {
            $h->booked()->kids($kidsCount);
        }

        // p.currencyCode
        // p.total
        $totalCharge = $this->http->FindSingleNode("//span[{$this->eq($this->t('TOTAL CHARGE'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]") ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL CHARGE'))}]/following::text()[normalize-space()][1][ancestor::*[{$xpathBold}]]");

        if (preg_match("/\d+\,/", $totalCharge)) {
            $totalCharge = $this->http->FindSingleNode("//span[{$this->eq($this->t('TOTAL CHARGE'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]") ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL CHARGE'))}]/following::text()[normalize-space()][1]/ancestor::p[1]", null, true, "/{$this->opt($this->t('TOTAL CHARGE'))}\s*([A-Z]{3}\s*\d[,.\'\d\´]*)/");
        }

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d\´]*)/", $totalCharge, $matches)) {
            // JPY 72,636
            $h->price()
                ->currency($matches['currency'])
                ->total(PriceHelper::parse($matches['amount'], $matches['currency']))
            ;
        }
        $cost = $this->http->FindSingleNode("//text()[{$this->eq('ROOM TOTAL')}]/following::text()[normalize-space(.)!=''][1][ancestor::*[{$xpathBold}]]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\´\d]*)/", $cost, $matches)) {
            // JPY 72,636
            $h->price()
                ->currency($matches['currency'])
                ->cost(PriceHelper::parse($matches['amount'], $matches['currency']));
        }

        // checkInDate (continuation)
        $checkInTexts = ['check-in time is', 'Check-in is', 'check-in', 'OFFICIAL CHECK IN', 'de entrada desde las'];
        $checkInTime = $this->http->FindSingleNode("descendant::text()[{$this->contains($checkInTexts)}][not(contains(normalize-space(), 'request'))][1]", null, true, "/{$this->opt($checkInTexts)}\s*({$this->patterns['time']})/i");

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("descendant::text()[{$this->eq($checkInTexts)}][1]/following::text()[normalize-space()][1]", null, true, "/({$this->patterns['time']})/i");
        }

        $xpathCheckInTime = "descendant::tr[ *[normalize-space()][2][not(.//tr)][{$this->contains($this->t('CHECK IN'))}] ][1]/*[normalize-space()][2][ descendant::br and descendant::text()[normalize-space()][2] ]";

        if (empty($checkInTime) && $this->http->XPath->query($xpathCheckInTime)->length === 1) {
            $checkInTimeText = $this->htmlToText($this->http->FindHTMLByXpath($xpathCheckInTime));

            if (preg_match("/^[ ]*{$this->opt($this->t('CHECK IN'))}[: ]*\n+[ ]*({$this->patterns['time']})/m", $checkInTimeText, $m)) {
                $checkInTime = $m[1];
            }
        }

        if ($dateCheckIn && $checkInTime) {
            $dateCheckIn = strtotime($this->normalizeTime($checkInTime), $dateCheckIn);
        }

        // checkOutDate (continuation)
        $checkOutTexts = ['check-out time is', 'check-out is', 'check-out', 'OFFICIAL CHECK OUT', 'de salida hasta las'];
        $checkOutTime = $this->http->FindSingleNode("descendant::text()[{$this->contains($checkOutTexts)}][1]", null, true, "/{$this->opt($checkOutTexts)}\s*({$this->patterns['time']})/i");

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode("descendant::text()[{$this->eq($checkOutTexts)}][1]/following::text()[normalize-space()][1]", null, true, "/({$this->patterns['time']})/i");
        }

        $xpathCheckOutTime = "descendant::tr[ *[normalize-space()][2][not(.//tr)][{$this->contains($this->t('CHECK OUT'))}] ][1]/*[normalize-space()][2][ descendant::br and descendant::text()[normalize-space()][2] ]";

        if (empty($checkOutTime) && $this->http->XPath->query($xpathCheckOutTime)->length === 1) {
            $checkOutTimeText = $this->htmlToText($this->http->FindHTMLByXpath($xpathCheckOutTime));

            if (preg_match("/^[ ]*{$this->opt($this->t('CHECK OUT'))}[: ]*\n+[ ]*({$this->patterns['time']})/m", $checkOutTimeText, $m)) {
                $checkOutTime = $m[1];
            }
        }

        if ($dateCheckOut && $checkOutTime) {
            $dateCheckOut = strtotime($this->normalizeTime($checkOutTime), $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        // cancellation
        if ($cancel = $this->getNode('CANCELLATIONS')) {
            $h->general()->cancellation($cancel);
        } elseif ($cancel = $this->http->FindSingleNode("(//text()[normalize-space(.)='CANCELLATIONS' or normalize-space(.)='CANCEL POLICY' or normalize-space(.)='Cancellations' or normalize-space(.)='AMENDMENTS AND CANCELLATIONS']/following::node()[normalize-space(.)][1])[1]")) {
            $h->general()->cancellation($cancel);
        } elseif ($cancel = $this->http->FindSingleNode("//text()[{$this->contains('bookings cancelled')}]")) {
            $h->general()->cancellation($cancel);
        } elseif ($cancel = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATIONS'))}]/following::text()[normalize-space()][1]")) {
            $h->general()->cancellation($cancel);
        }

        // deadline
        if (!empty($h->getCancellation()) && !empty($h->getCheckInDate())) {
            if (preg_match('/Please inform us for any modification or cancellation\s*(?<prior>\d{1,3})\s*h prior to your arrival./i', $h->getCancellation(), $m) // en
                || preg_match('/Must cancel (?<prior>\d{1,3}) hours prior to arrival to avoid one night room and tax penalty/siu', $h->getCancellation(), $m) // en
            ) {
                $h->booked()->deadlineRelative($m['prior'] . ' hours', '00:00');
            } elseif (preg_match("/kindly inform us (?<prior>\d{1,3}\s*(?:days?|hours?)) prior to the day of arrival by (?<hour>{$this->patterns['time']})(?:\s*[A-Z]{3,}|\s+local time)? for rooms in order to avoid full night cancell?ation fee/i", $h->getCancellation(), $m)
                || preg_match("/kindly inform us (?<prior>\d{1,3}\s*(?:days?|hours?)) prior to the day of arrival by (?<hour>{$this->patterns['time']})(?:\s*[A-Z]{3,}|\s+local time)? in order to avoid (?:cancell?ation penalty fee|a one night cancell?ation fee)/i", $h->getCancellation(), $m)
                || preg_match("/Should your travel plans change\, kindly inform us by (?<hour>\d+\s*a?p?m) \(local time\) one day prior to arrival in order to avoid a one night cancellation fee/i", $h->getCancellation(), $m)
                || preg_match("/Should your travel plans change, kindly inform us 24 hours prior to the day of arrival by (?<hour>\d+\s*a?p?m) \(local time\) in order to avoid a one night cancellation fee./i", $h->getCancellation(), $m)
                || preg_match("/No (?i)charge for(?:\s+room)? bookings cancell?ed by (?<hour>{$this->patterns['time']})(?:\s*[A-Z]{3,}|\s+local time)? the day prior to arrival/", $h->getCancellation(), $m)
                || preg_match("/No (?i)charge for(?:\s+room)? bookings cancell?ed by (?<hour>{$this->patterns['time']})(?:\s*[A-Z]{3,}|\s+local time)?, (?<prior>\d{1,3}\s*(?:days?|hours?)) prior to arrival/", $h->getCancellation(), $m)
                || preg_match("/Should your travel plans change, kindly inform us 24 hours prior to the day of arrival by (?<hour>{$this->patterns['time']}) MST in order to avoid any void of i-voucher as one night cancellation penalty./", $h->getCancellation(), $m)
                || preg_match("/kindly inform us by\s+(?<hour>{$this->patterns['time']})(:?\s*\(local time\))?\s*(?<prior>(?:\d{1,3}|[[:alpha:]]+)\s+days?)\s+prior to arrival/u", $h->getCancellation(), $m) // en
            ) {
                $m['prior'] = empty($m['prior']) ? '1 day' : $m['prior'];
                $m['prior'] = preg_replace(['/\bthree\b/i'], ['3'], $m['prior']);
                $this->parseDeadlineRelative($h, $m['prior'], $m['hour']);
            } elseif (preg_match("/kindly inform us by [\d\:]+a?p?m GMT 24 hours prior to the day of arrival, or (?<hours>\d+\s*hours?) for suites in order to avoid a one night cancellation fee/", $h->getCancellation(), $m)) {
                $h->booked()->deadlineRelative($m['hours']);
            } elseif (preg_match("/^Si (?i)sus planes de viaje cambian, por favor infórmenos antes de las\s+(?<hour>{$this->patterns['time']})(?:\s*\(hora local\))\s+un día antes de la llegada para evitar una penalización por cancell?ación/u", $h->getCancellation(), $m) // es
            ) {
                $h->booked()->deadlineRelative('1 day', $m['hour']);
            }
        }
    }

    /**
     * Dependencies `$this->normalizeAmount()`.
     */
    private function parseRateRange(?string $string): ?string
    {
        if (preg_match_all('/(?<currency>[A-Z]{3})?[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*\([^)(]{6,}\)/m', $string, $rateMatches) // JPY 58000 (March 7, 2019)
            || preg_match_all('/^[^)(]{6,}?[ ]*(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)$/m', $string, $rateMatches) // Sunday, July 18, 2021 EUR 1,070
        ) {
            $rateMatches['currency'] = array_values(array_filter($rateMatches['currency']));

            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})(?:\s+de|[[:alpha:]]{2})?\s+([[:alpha:]]{3,})\s+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // Thursday, 25 Jan 2024    |    Tuesday, 28th March 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([[:alpha:]]{3,})\s+(\d{1,2})(?:\s*,\s*|[[:alpha:]]{2}\s+)(\d{4})$/u', $text, $m)) {
            // Tuesday, June 29, 2021    |    Friday, September 1st 2023
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function normalizeTime(string $string): string
    {
        if (preg_match('/^(?:12)?\s*(?:noon|del mediodía)$/iu', $string)) {
            return '12:00';
        }

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51
        $string = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25
        $string = preg_replace('/(\d)[ ]*-[ ]*(\d)/', '$1:$2', $string); // 01-55 PM    ->    01:55 PM

        return $string;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function getNode($str, ?string $re = null, ?string $type = 'starts'): ?string
    {
        if ($type == 'eq') {
            return $this->http->FindSingleNode("(//text()[{$this->eq($str)}]/following-sibling::node()[normalize-space(.)!=''])[1]",
                null, true, $re);
        } else {
            return $this->http->FindSingleNode("(//text()[{$this->starts($str)}]/following-sibling::node()[normalize-space(.)!=''])[1]",
                null, true, $re);
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['ARRIVAL DATE']) || empty($phrases['RESERVATION NUMBER'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['ARRIVAL DATE'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['RESERVATION NUMBER'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function tPlusEn(string $s): array
    {
        return array_unique(array_merge((array) $this->t($s), (array) $this->t($s, 'en')));
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function opt($field, $delim = '/')
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) use ($delim) {
            return str_replace(' ', '\s+', preg_quote($s, $delim));
        }, $field)) . ')';
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE
        $s = preg_replace("/\n +\n/", "\n\n", $s);

        return trim($s);
    }

    private function parseDeadlineRelative(\AwardWallet\Schema\Parser\Common\Hotel $h, $prior, $hour = null): bool
    {
        $checkInDate = $h->getCheckInDate();

        if (empty($checkInDate)) {
            return false;
        }

        if (empty($hour)) {
            $deadline = strtotime('-' . $prior, $checkInDate);
            $h->booked()->deadline($deadline);

            return true;
        }

        $base = strtotime('-' . $prior, $checkInDate);

        if (empty($base)) {
            return false;
        }
        $deadline = strtotime($hour, strtotime(date('Y-m-d', $base)));

        if (empty($deadline)) {
            return false;
        }
        $priorUnix = strtotime($prior);

        if (empty($priorUnix)) {
            return false;
        }
        $priorSeconds = $priorUnix - strtotime('now');

        while ($checkInDate - $deadline < $priorSeconds) {
            $deadline = strtotime('-1 day', $deadline);
        }
        $h->booked()->deadline($deadline);

        return true;
    }
}
