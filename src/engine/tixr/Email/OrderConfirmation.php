<?php

namespace AwardWallet\Engine\tixr\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "tixr/it-597485212.eml, tixr/it-648280024.eml, tixr/it-645812495.eml, tixr/it-649279549.eml, tixr/it-715921316.eml, tixr/it-717980977-fr.eml";
    public $type = '';
    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public $subjects = [
        'Order Confirmation: ',
        'Confirmation de commande: ', // fr
    ];

    public static $dictionary = [
        "fr" => [
            // PDF
            'This is your ticket'                   => 'Ceci est votre billet',
            'Present this entire page at the event' => "Présentez cette page entière à l'événement",
            'confNumberPdf'                         => ['NUMÉRO DE COMMANDE'],
            'Issued to:'                            => 'Délivré à:',
            // 'SECTION' => '',
            'TABLE' => 'RANGÉE',
            'SEAT'  => 'SIÈGE',

            // HTML
            'confNumber' => 'Numéro de commande',
            'Order Date' => 'Date de commande',
            // 'Shipping Address' => '',
            // 'Expected Delivery Date' => '',
            // 'At' => '',
            // 'to' => '',
            'at'                    => 'à',
            'Total Including fees:' => 'Total incluant les frais:',
            'feeNames'              => ['Rabais'],
            'Discount'              => 'Rabais',

            // HTML 2
            'Items' => 'Articles',
        ],
        "en" => [
            // PDF
            // 'This is your ticket' => '',
            // 'Present this entire page at the event' => '',
            'confNumberPdf' => ['ORDER ID', 'TRANSFER ID'],
            'Issued to:'    => 'Issued to:',
            // 'SECTION' => '',
            // 'TABLE' => '',
            // 'SEAT' => '',

            // HTML
            'confNumber'            => 'Order ID',
            // 'Order Date' => '',
            // 'Shipping Address' => '',
            // 'Expected Delivery Date' => '',
            'At'                    => 'At',
            // 'to' => '',
            // 'at' => '',
            'Total Including fees:' => ['Total Including fees:', 'Total Including Fees:', 'Order Total:'],
            'feeNames'              => ['Fees & Taxes', 'Tax', 'Shipping', 'Processing Fees', 'Payment Plan', 'Credit Card Fees', 'Occupancy Tax', 'Donation', 'Discount', 'Service Fee', 'Facility Fee', 'Resort Fee', 'City Fee', 'Town of Dillon Sustainability', '5% GST'],
            // 'Discount' => '',

            // HTML 2
            // 'Items' => '',
        ],
    ];

    private $patterns = [
        'date'          => '\b[[:alpha:]]{3,15}\s+\d{1,2}\s*,\s*\d{4}\b', // Jun 15, 2024
        'dateShort'     => '\b[-[:alpha:]]{3,15}(?:\s*[,.]+\s*|[ ])(?:[[:alpha:]]{3,15}\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]{3,15})\b', // Saturday, November 18  |  Sun. Feb 25  |  jeu. 29 août
        'time'          => '\d{1,2}(?:[:：]|[ ]*[Hh][ ]*)\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*(?i)min(?-i))?', // 4:19PM  |  2:00 p. m.  |  20 h 00 min
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tixr.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // PDF

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, "www.tixr.com") !== false
                && $this->assignLangPdf($text)
            ) {
                return true;
            }
        }

        // HTML

        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".tixr.com/") or contains(@href,"www.tixr.com") or contains(@href,"support.tixr.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"at www.tixr.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRootHTML()->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tixr\.com$/', $from) > 0;
    }

    public function ParsePriceHTML(Event $e): void
    {
        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Including fees:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>[^\-\d)(]+?)\s*(?<amount>\d[,.‘\'\d ]*)$/", $price, $matches)) {
            // $ 1,135.72
            $currency = $this->currency($matches['currency']);
            $e->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currency));

            $costAmounts = $discountAmounts = [];

            $costRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Total Including fees:'))}]/preceding::tr[ count(*[normalize-space()])=3 and *[normalize-space()][1][translate(normalize-space(),'0123456789 ','')='x'] ]");

            foreach ($costRows as $costRow) {
                $costCharge = $this->http->FindSingleNode('*[normalize-space()][3]', $costRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?\s*(?<amount>\d[,.‘\'\d ]*)$/u', $costCharge, $m)) {
                    $costAmounts[] = PriceHelper::parse($m['amount'], $currency);
                }
            }

            if (count($costAmounts) > 0) {
                $e->price()->cost(array_sum($costAmounts));
            }

            $feeRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Total Including fees:'))}]/preceding::tr[count(*[normalize-space()])=2][ *[normalize-space()][1][{$this->eq($this->t('feeNames'), "translate(.,':','')")}] or *[normalize-space()][2][{$this->starts(['+', '-', '–'])}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^[-–+\s]*(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?\s*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $feeAmount = PriceHelper::parse($m['amount'], $currency);

                    if (preg_match('/^[-–]/', $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow))
                        || preg_match("/^{$this->opt($this->t('Discount'))}$/i", $feeName)
                    ) {
                        $discountAmounts[] = $feeAmount;
                    } else {
                        $e->price()->fee($feeName, $feeAmount);
                    }
                }
            }

            if (count($discountAmounts) > 0) {
                $e->price()->discount(array_sum($discountAmounts));
            }
        }
    }

    public function ParsePriceHTML2(Event $e): void
    {
        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Including fees:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>[^\-\d)(]+?)\s*(?<amount>\d[,.‘\'\d ]*)$/u", $price, $matches)
            || preg_match("/(?:^|[^\-\d)(])\s*(?<amount>\d[,.‘\'\d ]*?)\s*(?<currency>[A-Z]{3})$/u", $price, $matches)
        ) {
            // $ 909.23    |    $ 170,00 CAD
            $currency = $this->currency($matches['currency']);
            $e->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currency));

            $costAmounts = $discountAmounts = [];

            $costRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Total Including fees:'))}]/preceding::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][translate(normalize-space(),'0123456789 ','')='x'] ]");

            foreach ($costRows as $costRow) {
                $costCharge = $this->http->FindSingleNode('ancestor::*[ preceding-sibling::node()[normalize-space()] or following-sibling::node()[normalize-space()] ][1][not(following-sibling::node()[normalize-space()])]/preceding-sibling::node()[normalize-space()][1]', $costRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?\s*(?<amount>\d[,.‘\'\d ]*)$/u', $costCharge, $m)
                    || preg_match('/(?:^|[^\-\d)(])\s*(?<amount>\d[,.‘\'\d ]*?)\s*(?:' . preg_quote($matches['currency'], '/') . ')$/u', $costCharge, $m)
                ) {
                    $costAmounts[] = PriceHelper::parse($m['amount'], $currency);
                }
            }

            if (count($costAmounts) > 0) {
                $e->price()->cost(array_sum($costAmounts));
            }

            $feeRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Total Including fees:'))}]/preceding::*[count(node()[normalize-space() and not(self::comment())])=2][ node()[normalize-space() and not(self::comment())][1][{$this->eq($this->t('feeNames'), "translate(.,':','')")}] or node()[normalize-space() and not(self::comment())][2][{$this->starts(['+', '-', '–'])}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('node()[normalize-space() and not(self::comment())][2]', $feeRow, true, '/^[-–+\s]*(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?\s*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)
                    || preg_match('/(?:^|[^\-\d)(])\s*(?<amount>\d[,.‘\'\d ]*?)\s*(?:' . preg_quote($matches['currency'], '/') . ')$/u', $feeCharge, $m)
                ) {
                    $feeName = $this->http->FindSingleNode('node()[normalize-space() and not(self::comment())][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $feeAmount = PriceHelper::parse($m['amount'], $currency);

                    if (preg_match('/^[-–]/', $this->http->FindSingleNode('node()[normalize-space() and not(self::comment())][2]', $feeRow))
                        || preg_match("/^{$this->opt($this->t('Discount'))}$/i", $feeName)
                    ) {
                        $discountAmounts[] = $feeAmount;
                    } else {
                        $e->price()->fee($feeName, $feeAmount);
                    }
                }
            }

            if (count($discountAmounts) > 0) {
                $e->price()->discount(array_sum($discountAmounts));
            }
        }
    }

    public function ParseEventHTML(Email $email, ?\DOMNode $root): void
    {
        // examples: it-597485212.eml, it-648280024.eml, it-645812495.eml, it-649279549.eml

        $e = $email->add()->event();
        $e->type()->show();

        $nameAndLoc = $this->http->FindSingleNode("tr[normalize-space()][1]", $root);

        if (preg_match("/^(?<name>.{2,}?)\s+{$this->opt($this->t('At'))}\s+(?<location>.{3,})$/", $nameAndLoc, $m)) {
            $e->place()->name($m['name'])->address($m['location']);
        }

        $datesVal = $this->http->FindSingleNode("tr[normalize-space()][2]", $root);
        $dates = preg_split("/\s+{$this->opt($this->t('to'))}\s+/", $datesVal);

        if (count($dates) === 2) {
            $dateStartVal = $dates[0];
            $dateEndVal = $dates[1];
        } elseif (count($dates) === 1) {
            $dateStartVal = $datesVal;
            $dateEndVal = null;
        } else {
            $dateStartVal = $dateEndVal = null;
        }

        $dateStart = $dateEnd = $timeStart = $timeEnd = null;
        $pattern1 = "/^(?:{$this->patterns['dateShort']}\s*,\s*\d{4}|{$this->patterns['date']})$/u"; // Fri. Aug 9, 2024
        $pattern2 = "/^(?<date>{$this->patterns['dateShort']}\s*,\s*\d{4}|{$this->patterns['date']})\s+{$this->opt($this->t('at'))}\s+(?<time>{$this->patterns['time']})/u"; // Feb 17, 2024 at 7:30 PM PST

        if (preg_match($pattern1, $dateStartVal)) {
            $dateStart = strtotime($this->normalizeDate($dateStartVal));
            $timeStart = '00:00';
        } elseif (preg_match($pattern2, $dateStartVal, $m)) {
            $dateStart = strtotime($this->normalizeDate($m['date']));
            $timeStart = $this->normalizeTime($m['time']);
        }

        if (preg_match($pattern1, $dateEndVal)) {
            $dateEnd = strtotime($this->normalizeDate($dateEndVal));
            $timeEnd = '23:59';
        } elseif (preg_match($pattern2, $dateEndVal, $m)) {
            $dateEnd = strtotime($this->normalizeDate($m['date']));
            $timeEnd = $this->normalizeTime($m['time']);
        }

        if ($dateStart && $timeStart) {
            $e->booked()->start(strtotime($timeStart, $dateStart));
        }

        if ($dateEnd && $timeEnd) {
            $e->booked()->end(strtotime($timeEnd, $dateEnd));
        } elseif (!empty($e->getStartDate()) && !$dateEndVal) {
            $e->booked()->noEnd();
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $e->general()->confirmation($confirmation, $confirmationTitle);
        }

        $saText = $this->htmlToText($this->http->FindHTMLByXpath("//node()[{$this->eq($this->t('Shipping Address'), "translate(.,':','')")}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]"));
        $shippingAddress = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Shipping Address'))}(?:[: ]*\n+[ ]*)+([\s\S]+?)(?:(?:[ ]*\n+[ ]*)+{$this->opt($this->t('Expected Delivery Date'))}|\s*$)/", $saText);

        if (preg_match("/^({$this->patterns['travellerName']})[ ]*\n[ ]*\S.{2,}/u", $shippingAddress, $m)) {
            $e->general()->traveller($m[1], true);
        }

        $this->ParsePriceHTML($e);
    }

    public function ParseEventHTML2(Email $email, ?\DOMNode $root): void
    {
        // examples: it-715921316.eml, it-717980977-fr.eml

        $e = $email->add()->event();
        $e->type()->show();

        $orderDate = strtotime($this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Order Date'), "translate(.,':','')")}] ]/*[normalize-space()][2]", null, true, "/^(?:\d{1,2}\/\d{1,2}\/\d{2,4}|\d{4}-\d{1,2}-\d{1,2})$/u"));
        $year = date('Y', $orderDate ? $orderDate : null);

        $name = $this->http->FindSingleNode("node()[normalize-space() and not(self::comment())][last()-3]", $root);
        $location = $this->http->FindSingleNode("node()[normalize-space() and not(self::comment())][last()-2]", $root);
        $e->place()->name($name)->address($location);

        $datesVal = $this->http->FindSingleNode("node()[normalize-space() and not(self::comment())][last()-1]", $root);
        $dates = preg_split("/\s+[-–]\s+/", $datesVal);

        if (count($dates) === 2) {
            $dateStartVal = $dates[0];
            $dateEndVal = $dates[1];
        } elseif (count($dates) === 1) {
            $dateStartVal = $datesVal;
            $dateEndVal = null;
        } else {
            $dateStartVal = $dateEndVal = null;
        }

        $dateStart = $dateEnd = $timeStart = $timeEnd = null;

        // Thu Sep 12
        $pattern1 = "/^(?<date>{$this->patterns['dateShort']}|.{4,}\b\d{4})$/u";

        // Thu Sep 12 at 6:00 PM
        $pattern2 = "/^(?<date>{$this->patterns['dateShort']}|.{4,}\b\d{4})\s+{$this->opt($this->t('at'))}\s+(?<time>{$this->patterns['time']})$/u";

        if (preg_match($pattern1, $dateStartVal)) {
            $timeStart = '00:00';
        } elseif (preg_match($pattern2, $dateStartVal, $m)) {
            $dateStartVal = $m['date'];
            $timeStart = $this->normalizeTime($m['time']);
        }

        if (preg_match($pattern1, $dateEndVal)) {
            $timeEnd = '23:59';
        } elseif (preg_match($pattern2, $dateEndVal, $m)) {
            $dateEndVal = $m['date'];
            $timeEnd = $this->normalizeTime($m['time']);
        }

        // Fri Aug 8    |    jeu. 29 août
        $pattern3 = "/^(?<wday>[-[:alpha:]]+)[,.\s]+(?<date>[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+)$/u";

        if (preg_match("/^.{4,}\b\d{4}$/", $dateStartVal) > 0) {
            $dateStart = strtotime($this->normalizeDate($dateStartVal));
        } elseif (preg_match($pattern3, $dateStartVal, $m) && $year) {
            $dateNormal = $this->normalizeDate($m['date']);
            $weekDateNumber = WeekTranslate::number1($m['wday'], $this->lang);

            if ($dateNormal && $weekDateNumber) {
                $dateStart = EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $year, $weekDateNumber);
            }
        }

        if (preg_match("/^.{4,}\b\d{4}$/", $dateEndVal) > 0) {
            $dateEnd = strtotime($this->normalizeDate($dateEndVal));
        } elseif (preg_match($pattern3, $dateEndVal, $m) && $year) {
            $dateNormal = $this->normalizeDate($m['date']);
            $weekDateNumber = WeekTranslate::number1($m['wday'], $this->lang);

            if ($dateNormal && $weekDateNumber) {
                $dateEnd = EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $year, $weekDateNumber);
            }
        }

        if ($dateStart && $timeStart) {
            $e->booked()->start(strtotime($timeStart, $dateStart));
        }

        if ($dateEnd && $timeEnd) {
            $e->booked()->end(strtotime($timeEnd, $dateEnd));
        } elseif (!empty($e->getStartDate()) && !$dateEndVal) {
            $e->booked()->noEnd();
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'), "translate(.,':','')")}]", null, true, '/^(.+?)[\s:：]*$/u');
            $e->general()->confirmation($confirmation, $confirmationTitle);
        }

        $shippingAddress = implode("\n", $this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Shipping Address'), "translate(.,':','')")}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]"));

        if (preg_match("/^({$this->patterns['travellerName']})[ ]*\n[ ]*\S.{2,}/u", $shippingAddress, $m)) {
            $e->general()->traveller($m[1], true);
        }

        $this->ParsePriceHTML2($e);
    }

    public function ParseEventPDF(Email $email, $text): void
    {
        $startFragment = '';

        if (preg_match_all("/^[ ]*{$this->opt($this->t('This is your ticket'))}.*\n+(?:[ ]*{$this->opt($this->t('Present this entire page at the event'))})?[ ]*(.*(?:\n+.+){1,5})\n+[ ]{10,}\S.+\n.*[ ]{10}{$this->opt($this->t('Issued to:'))}/m", $text, $nameMatches)) {
            if (count(array_unique(array_map(function ($item) { return preg_replace('/\s+/', ' ', $item); }, $nameMatches[1]))) > 1) {
                $this->logger->debug('WARNING: The letter contains tickets for various events!');

                return;
            } else {
                $startFragment = $nameMatches[1][0];
            }
        }

        $e = $email->add()->event();
        $e->type()->show();

        if (preg_match("/^[ ]+({$this->opt($this->t('confNumberPdf'))}).*\n+[ ]+([-A-Z\d]{5,})(?:[ ]{2}|$)/m", $text, $m)) {
            $e->general()->confirmation($m[2], $m[1]);
        }

        $startFragment = preg_replace("/[ ]{2,}Doors .+/i", '', $startFragment); // remove garbage

        $eventName = preg_replace('/\s+/', ' ', trim($this->re("/(?:^|[ ]{10}){$this->patterns['dateShort']}.*(?:\n[ ]*{$this->patterns['time']})?\n+((?:\n[ ]*\S.+){1,3})$/u", $startFragment)));

        if ($eventName
            && ($eventNameHtml = $this->http->FindSingleNode("//*[count(tr[normalize-space()])=2]/tr[normalize-space()][1][{$this->starts($eventName)}]", null, true, "/^({$this->opt($eventName)}.+?)\s+{$this->opt($this->t('At'))}\s+\S/i"))
            && $eventName !== $eventNameHtml
        ) {
            // it-648280024.eml
            $eventName = $eventNameHtml;
            $this->logger->debug('INFO: Event name collect from HTML.');
        }

        $e->place()->name($eventName)->address($this->re("/[ ]{10}{$this->opt($this->t('Issued to:'))}.*\n+[ ]{10,}(.{3,})/", $text));

        $dateStart = $dateEnd = $timeStart = $timeEnd = null;
        // TODO: use EmailDateHelper::parseDateRelative()

        $this->patterns['dateAtTime'] = "(?:\s+(?:{$this->opt($this->t('at'))}|[-–])\s+|[ ]*,[ ]*|\n[ ]*)";
        $this->patterns['dateToDate'] = "\s+(?:{$this->opt($this->t('to'))}|[-–])\s+";

        if (preg_match("/(?:^|[ ]{10})(?<dayMonth1>{$this->patterns['dateShort']})(?:\s*,\s*|[ ])(?<year1>\d{4}){$this->patterns['dateAtTime']}(?<time1>{$this->patterns['time']}){$this->patterns['dateToDate']}(?<dayMonth2>{$this->patterns['dateShort']})(?:\s*,\s*|[ ])(?<year2>\d{4}){$this->patterns['dateAtTime']}(?<time2>{$this->patterns['time']})\n/iu", $startFragment, $m)) {
            // Fri Jun 28, 2024, 7:00 PM - Sat Jun 29, 2024 7:00 PM
            $dateStart = strtotime($this->normalizeDate($m['dayMonth1'] . ', ' . $m['year1']));
            $timeStart = $this->normalizeTime($m['time1']);
            $dateEnd = strtotime($this->normalizeDate($m['dayMonth2'] . ', ' . $m['year2']));
            $timeEnd = $this->normalizeTime($m['time2']);
        } elseif (preg_match("/(?:^|[ ]{10})(?<dayMonth>{$this->patterns['dateShort']})(?:\s*,\s*|[ ])(?<year>\d{4}){$this->patterns['dateAtTime']}(?<time1>{$this->patterns['time']}){$this->patterns['dateToDate']}(?<time2>{$this->patterns['time']})\n/iu", $startFragment, $m)) {
            // Saturday, August 17, 2024 at 8:30 PM - 10:15 PM
            $dateStart = strtotime($this->normalizeDate($m['dayMonth'] . ', ' . $m['year']));
            $timeStart = $this->normalizeTime($m['time1']);
            $dateEnd = $dateStart;
            $timeEnd = $this->normalizeTime($m['time2']);
        } elseif (preg_match("/(?:^|[ ]{10})(?<dayMonth1>{$this->patterns['dateShort']})(?:\s*,\s*|[ ])(?<year1>\d{4}){$this->patterns['dateToDate']}(?<dayMonth2>{$this->patterns['dateShort']})(?:\s*,\s*|[ ])(?<year2>\d{4})\n/iu", $startFragment, $m)) {
            // Fri Apr 19, 2024 - Sun Apr 21, 2024
            $dateStart = strtotime($this->normalizeDate($m['dayMonth1'] . ', ' . $m['year1']));
            $timeStart = '00:00';
            $dateEnd = strtotime($this->normalizeDate($m['dayMonth2'] . ', ' . $m['year2']));
            $timeEnd = '23:59';
        } elseif (preg_match("/(?:^|[ ]{10})(?<dayMonth>{$this->patterns['dateShort']})(?:\s*,\s*|[ ])(?<year>\d{4}){$this->patterns['dateAtTime']}(?<time>{$this->patterns['time']})(?:{$this->patterns['dateToDate']}{$this->patterns['dateShort']}|\n)/iu", $startFragment, $m) // it-597485212.eml
            || preg_match("/(?:^|[ ]{10})(?<dayMonth>{$this->patterns['dateShort']})(?:\s*,\s*|[ ])(?<year>\d{4}){$this->patterns['dateAtTime']}(?<time>{$this->patterns['time']})\s+Showtime\n/iu", $startFragment, $m) // special pattern, don't modify!
            || preg_match("/(?:^|[ ]{10})(?<dayMonth>{$this->patterns['dateShort']}){$this->patterns['dateAtTime']}(?<time>{$this->patterns['time']}){$this->patterns['dateToDate']}.*\b(?<year>\d{4})\n/iu", $startFragment, $m) // it-648280024.eml
        ) {
            $dateStart = strtotime($this->normalizeDate($m['dayMonth'] . ', ' . $m['year']));
            $timeStart = $this->normalizeTime($m['time']);
        }

        if ($dateStart && $timeStart) {
            $e->booked()->start(strtotime($timeStart, $dateStart));
        }

        if ($dateEnd && $timeEnd) {
            $e->booked()->end(strtotime($timeEnd, $dateEnd));
        } elseif (!empty($e->getStartDate()) && !$dateEnd) {
            $e->booked()->noEnd();
        }

        if (preg_match_all("/{$this->opt($this->t('Issued to:'))}[ ]*({$this->patterns['travellerName']})\n/u", $text, $travellerMatches)) {
            $e->general()->travellers(array_unique($travellerMatches[1]), true);
        }

        $this->findRootHTML();

        if ($this->type === 'html') {
            $this->ParsePriceHTML($e);
        } elseif ($this->type === 'html2') {
            $this->ParsePriceHTML2($e);
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLangPdf($text)) {
                $this->ParseEventPDF($email, $text);
                $this->type = 'pdf';
            }
        }

        if (count($email->getItineraries()) === 0) {
            $this->assignLang();
            $roots = $this->findRootHTML();
            $root = $roots->length === 1 ? $roots->item(0) : null;

            if ($this->type === 'html') {
                $this->ParseEventHTML($email, $root);
            } elseif ($this->type === 'html2') {
                $this->ParseEventHTML2($email, $root);
            }
        }

        $email->setType('OrderConfirmation' . ucfirst($this->type) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2; // PDF + HTML
    }

    private function findRootHTML(): \DOMNodeList
    {
        $format = 'html2';
        $nodes = $this->http->XPath->query("//*[ node()[normalize-space()][3] and node()[normalize-space()][last()][{$this->eq($this->t('Items'), "translate(.,'0123456789','')")}] ]");

        if ($nodes->length === 0) {
            $format = 'html';
            $nodes = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('At'))}] ]");
        }

        if ($nodes->length > 0) {
            $this->type = $format;
        }

        return $nodes;
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function assignLangPdf(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumberPdf']) || empty($phrases['Issued to:'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumberPdf']) !== false
                && $this->strposArray($text, $phrases['Issued to:']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->starts($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function currency($s): ?string
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> '$',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b([[:alpha:]]+)[,.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // Mon. May 27, 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^([[:alpha:]]+)[,.\s]+(\d{1,2})$/u', $text, $m)) {
            // May 27
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/\b(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{4})$/u', $text, $m)) {
            // Mon, 27 May 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})[,.\s]+([[:alpha:]]+)$/u', $text, $m)) {
            // 27 May
            $day = $m[1];
            $month = $m[2];
            $year = '';
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

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace([
            '/^(\d{1,2})[ ]*[-h][ ]*(\d{1,2})(?:[ ]*min)?$/i', // 20 h 00 min    ->    20:00
        ], [
            '$1:$2',
        ], $s);

        return $s;
    }
}
