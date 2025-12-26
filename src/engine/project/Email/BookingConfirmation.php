<?php

namespace AwardWallet\Engine\project\Email;

use AwardWallet\Schema\Parser\Common\TransferSegment;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "project/it-1.eml, project/it-2.eml, project/it-229777841.eml, project/it-3.eml, project/it-4.eml, project/it-43318743.eml, project/it-49638631.eml, project/it-5.eml, project/it-6.eml, project/it-7.eml, project/it-79891574.eml, project/it-8.eml, project/it-9.eml";

    public $reFrom = ["@projectexpedition.com", "@smartflyer.com", "@ntmllc.com"];
    public $reBody = [
        'en' => ['Booked through Project Expedition', 'Tour Operator'],
    ];
    public $reSubject = [
        'Booking Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Traveler Names'           => 'Traveler Names',
            'Notes from Tour Operator' => 'Notes from Tour Operator',
            'confirmation'             => ['Operator Reference:', 'Confirmation:'],
            'Ref:'                     => ['Ref:', 'Reference:'],
            'direction'                => ['Pickup Details', 'Return Details'],
            'Cancellation'             => ['Cancellation', 'May Cancel Before'],
            'General:'                 => ['General:', '**We are located at The Harbor Mall:**'],
        ],
    ];
    private $keywordProv = 'Project Expedition';
    private $date;
    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        'phone'         => '[+(\d][-+. \/\d)(]{5,18}?[\d)]', // (+44) 800 038 8019    |    + 36/20-332-5364
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        'gps'           => '[- ]*\d[.\d]{3,}\d', // 51.501119    |    -0.123778
        'nameCode'      => '(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)', // Miami Airport (MIA)
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $htmlBody = $this->http->Response['body'];

        if ($this->http->XPath->query("descendant::tr[ *[2][{$this->eq($this->t('Payment Summary'))}] ]/following-sibling::tr[normalize-space()][1]/*[2][normalize-space()]")->length === 0) {
            // rebuild DOM
            $this->logger->debug('Rebuilding DOM...');
            $this->http->FilterHTML = true;
            $this->http->SetEmailBody($htmlBody);
        }

        if (!$this->assignLang($htmlBody)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if (empty($this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'Reservation is pending and for reference only')]"))) {
            $roots = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Reservation Details'))}] and *[2][{$this->eq($this->t('Tour Operator'))}] ]/ancestor::table[1]");

            foreach ($roots as $root) {
                $this->parseItinerary($email, $root);
            }
        } else {
            // it-79891574.eml
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.projectexpedition.com')] | //a[contains(@href,'.projectexpedition.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang($parser->getHTMLBody());
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) !== true) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || $this->stripos($headers["subject"], $this->keywordProv))
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // transfer | event
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseItinerary(Email $email, \DOMNode $root): void
    {
        $confNoOta = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Ref:'))}][1]", $root, false,
            "#{$this->opt($this->t('Ref:'))}\s*(.+)#");

        $phoneOta = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Booked through Project Expedition'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[{$this->eq($this->t('Phone:'))}]/following::text()[normalize-space()!=''][1]", $root);

        $paymentSummaryHtml = $this->http->FindHTMLByXpath("descendant::tr[ *[2][{$this->eq($this->t('Payment Summary'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]", null, $root);
        $paymentSummary = $this->htmlToText($paymentSummaryHtml);

        if (preg_match("/^[ ]*{$this->opt($this->t('Cancellation'))}[ ]*[:]+[ ]*(.+?)[ ]*$/m", $paymentSummary, $m)
            || preg_match("/^[ ]*({$this->opt($this->t('Cancellation Unavailable'))}.*?)[ ]*$/m", $paymentSummary, $m)
        ) {
            $cancellation = $m[1];
        } else {
            $cancellation = null;
        }

        $status = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Status:')]", $root, false, "#{$this->opt($this->t('Status:'))}\s*(.+)#");

        /* Notes (start) */

        $notes = $this->http->FindHTMLByXpath("//tr[count(td[normalize-space()]) = 2]/td[2][descendant::text()[normalize-space()][1][normalize-space() = 'Notes from Tour Operator']]");

        $notes = preg_replace("/\s*<\s*br\s*\/?\s*(?: [^<>]+)?>\s*/i", "\n", $notes); // <br clear="none">
        $notes = preg_replace("/\s*<\/\s*p\s*>\s*/i", "</p>\n", $notes);
        $notes = html_entity_decode(strip_tags($notes));
        $notes = preg_replace("/^\s*Notes from Tour Operator\s*/", '', $notes);
        $notes = preg_replace("/\s*\n\s*" . $this->opt(['COVID-19 Policies (subject to change prior to date of travel):', 'Tour Operator:']) . "[\s\S]+/", '', $notes);
        // $notes = preg_replace(["/(\s*\n){3,}/", "/^ +/m"], ["\n", ''], $notes);

        // remove text duplicates
        $notesParts = preg_split("/(?:[ ]*\n+[ ]*)+{$this->opt($this->t('General:'))}\s*/", $notes);
        $notesPartsCleared = array_unique(array_map(function ($item) { return preg_replace('/\W/', '', $item); }, $notesParts));
        $notes = implode("\nGeneral:\n", array_intersect_key($notesParts, $notesPartsCleared));

        // remove \n
        $notes = preg_replace(["/:\s*\n\s*/", "/\.?\s*\n\s*/"], [': ', '. '], $notes);
        $notes = str_replace(['{', '}'], ['(', ')'], $notes);

        /* Notes (end) */

        $date = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Ref:'))}][1]/ancestor::td[1]/descendant::text()[normalize-space()!=''][1]", $root);
        $date = preg_replace("/(\d)[ ]*[:]+[ ]*(\d)/", '$1:$2', $date); // 4: 00pm    ->    4:00pm
        $date = preg_replace("/([\s,])0{1,2}:00 am\s*$/i", '${1}00:00', $date);
        $nameTour = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Ref:'))}][1]/ancestor::td[1]/preceding::text()[normalize-space()!=''][1]", $root);
        $nodeTravellers = implode("\n",
            $this->http->FindNodes("descendant::text()[starts-with(normalize-space(),'Traveler Names')]/ancestor::td[1]/descendant::text()[normalize-space()!='']", $root));

        if (preg_match_all("/^[ ]*\d{1,3}\.[ ]*({$this->patterns['travellerName']})\s*(?:\(|$)/mu", $nodeTravellers, $travellerMatches)) {
            $travellers = $travellerMatches[1];
        }
        $guests = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Traveler Names')]", $root, false,
            "#\((\d+) Total\):#");

        if ($date == 'Private' || preg_match("/^Transfer\s*:/i", $nameTour)) {
            ///////////////////////////////
            //      TRANSFER
            ///////////////////////////////

            // nameTour = `Transfer: Venice Marco Polo Airport (VCE) to Bauer Hotel`

            $r = $email->add()->transfer();

            $r->ota()
                ->phone($phoneOta, 'Project Expedition')
                ->confirmation($confNoOta, 'Ref');

            $confNo = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Tour Operator:'))}]/ancestor::td[1]/descendant::text()[{$this->starts($this->t('Transfer Reference:'))}][1]", $root, true, "/.:[\s#]*([^:\s#].{3,60}?)[,.;\s]*$/")
                ?? $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confirmation'))}]", $root, true, "/.:[\s#]*([^:\s#].{3,60}?)[,.;\s]*$/")
            ;

            if (!empty($confNo)) {
                if (preg_match_all("#([\w\-]+)\s*\(([^\)]+)\)#", $confNo, $m, PREG_SET_ORDER)) {
                    // 12141562 (Outbound), 12141563 (Return)
                    foreach ($m as $i => $v) {
                        $r->general()->confirmation($v[1], $v[2], $i === 0);
                    }
                } else {
                    $r->general()->confirmation($confNo);
                }
            } else {
                $r->general()->noConfirmation();
            }

            $node = implode("\n", $this->http->FindNodes("descendant::text()[{$this->eq($this->t('direction'))}]/ancestor::*[../self::tr][1]/descendant::text()[normalize-space()]", $root));
            $segments = $this->splitter("#\n({$this->opt($this->t('direction'))})#", $node);

            foreach ($segments as $segment) {
                $s = $r->addSegment();

                if (preg_match("#\nChild[^\n]*\n(.+?)\nContact Info:#is", $nodeTravellers, $m)) {
                    $kids = count(explode("\n", $m[1]));
                    $s->extra()
                        ->adults($guests - $kids)
                        ->kids($kids);
                } else {
                    $s->extra()->adults($guests);
                }

                $pickupInfo = $this->re("#{$this->opt($this->t('direction'))}\s+(.+)\s+Drop Off:#s", $segment);
                $name = $this->nice($this->re("#From:\s*(.{2,}?)\s*(?:Location:|Arriving:|Pickup:|Details:)#s", $pickupInfo));

                if (preg_match("#^{$this->patterns['nameCode']}$#", $name, $m)) {
                    $s->departure()->name($m['name'])->code($m['code']);
                } else {
                    $s->departure()->name(preg_replace('/^(([-[:alpha:]]{2,})\s+Port)$/iu', '$1, $2', $name));
                }

                $address = $this->nice($this->re("#Address:\s*(.{3,}?)\s*(?:Pickup Time:|$)#s", $pickupInfo));

                if (empty($address) && !empty($s->getDepName())) {
                    $address = $this->nice($this->re("#Details:\s*(?i)([^:]*{$this->opt($s->getDepName())}[^:]*)$#", $pickupInfo));
                }

                if (!empty($address)) {
                    $s->departure()->address($address);
                }

                $dateDep = $this->normalizeDate($this->re("#(?:Pickup Time:|Meeting Time:|Pickup:)\s*(.{3,})#", $pickupInfo));

                if (empty($dateDep)) {
                    $dateDep = strtotime('+30 minutes', $this->normalizeDate($this->re("#\nArriving:\s*(.+)#", $pickupInfo)));
                }

                $s->departure()->date($dateDep);

                $dropoffInfo = $this->re("#Drop Off:\s*(.+?)\s*(?:Contact Info|$)#s", $segment);
                $name = $this->nice($this->re("#^(.{2,}?)\s*(?:Location:|Departure Details:|Details:)#s", $dropoffInfo));

                if (preg_match("#^{$this->patterns['nameCode']}$#", $name, $m)) {
                    $s->arrival()->name($m['name'])->code($m['code']);
                } else {
                    $name = preg_replace("/\([A-z]{1,6}\)$/", "", $name);
                    $name = preg_replace('/^(([-[:alpha:]]{2,})\s+Port)$/iu', '$1, $2', $name);
                    $s->arrival()->name($name);
                }

                $address = $this->nice($this->re("#Address:\s*(.{3,}?)\s*$#s", $dropoffInfo));

                if (empty($address) && !empty($s->getArrName())) {
                    $address = $this->nice($this->re("#Details:\s*(?i)([^:]*{$this->opt($s->getArrName())}[^:]*)$#", $dropoffInfo));
                }

                if (!empty($address)) {
                    $s->arrival()->address($address);
                }

                $duration = '';
                $duration_temp = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Duration:'))}]", $root, null, "/Duration:\s*(.+?)\|/");

                if ($duration_temp !== null) {
                    $duration = $duration_temp;
                }

                if (empty($duration)) {
                    // it-3.eml
                    $notesFragment = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Max Passengers:'))}]", $root);

                    if (!empty($notesFragment)) {
                        $parts = preg_split('/\s*\|\s*/', $notesFragment);

                        if (count($parts) === 3) {
                            $duration = end($parts);
                        }
                    }
                }

                if ($s->getDepDate() && preg_match("/(hour|minute)/i", $duration)) {
                    $hours = $this->re("/\b(\d+)\s+hours?\b/i", $duration);
                    $minutes = $this->re("/\b(\d+)\s+minutes?\b/i", $duration);
                    $endDate = $s->getDepDate();

                    if (!empty($hours)) {
                        $endDate = strtotime("+" . $hours . ' hours', $endDate);
                    }

                    if (!empty($minutes)) {
                        $endDate = strtotime("+" . $minutes . ' minutes', $endDate);
                    }

                    if ($endDate !== $s->getDepDate()) {
                        $s->arrival()->date($endDate);
                    }
                } elseif (preg_match("#Departure Time:\s+(.+)#", $dropoffInfo, $m)) {
                    /* WTF?
                    $s->arrival()->date(strtotime("-2 hours", $this->normalizeDate($m[1])));
                    */
                    $s->arrival()->date($this->normalizeDate($m[1]));
                } else {
                    $s->arrival()->noDate();
                }

                $this->setGeoTips($s);
            }
        } else {
            ///////////////////////////////
            //      EVENT
            ///////////////////////////////

            // nameTour = `Coasteering Adventure in Arrábida With Transfer`

            $r = $email->add()->event();
            $r->setEventType(EVENT_EVENT);

            $r->ota()
                ->phone($phoneOta, 'Project Expedition')
                ->confirmation($confNoOta, 'Ref');

            if (!empty($this->normalizeDate($date))) {
                $r->booked()
                    ->start($this->normalizeDate($date));
            }
            $meetingTime = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Meeting Time:'))}]", $root,
                true, "/Meeting Time:\s*({$this->patterns['time']})/i");

            if (empty($this->normalizeDate($date)) && !empty($meetingTime)) {
                $date = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Ref:')][1]/ancestor::td[1]/descendant::text()[normalize-space()!=''][1]", $root, true, "/([A-z]+\s\d{1,2},\s\d{4})/");
                $r->booked()
                    ->start(strtotime($date . " " . $meetingTime));
            }

            if ($r->getStartDate()) {
                $this->date = $r->getStartDate();
            }
            $r->booked()->guests($guests);

            $address = $this->findAddress($root, $notes);

            if (stripos($address, 'please provide name & address') !== false) {
                $addressInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Notes:']/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

                if (preg_match("/Notes\:\n((?:.+\n){2,4})Contact Info\:/", $addressInfo, $m)) {
                    $address = str_replace("\n", " ", $m[1]);
                }
            }
            $r->place()->name($nameTour)->address($address);

            $duration = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Duration:'))}]/following::text()[normalize-space()!=''][1]", $root);

            if (!empty($duration)) {
                // 1 hour    |    3 hours    |    4 hours 30 minutes | 2 days
                $days = $this->re("/\b(\d{1,3})\s+days?/i", $duration);
                $hours = $this->re("/\b(\d{1,3})\s+hours?/i", $duration);
                $minutes = $this->re("/\b(\d{1,3})\s+minutes?/i", $duration);
                $endDate = $r->getStartDate();

                if (!empty($days)) {
                    $endDate = strtotime("+" . $days . ' days', $endDate);
                }

                if (!empty($hours)) {
                    $endDate = strtotime("+" . $hours . ' hours', $endDate);
                }

                if (!empty($minutes)) {
                    $endDate = strtotime("+" . $minutes . ' minutes', $endDate);
                }

                if ($endDate !== $r->getStartDate()) {
                    $r->booked()->end($endDate);
                }
            } else {
                $txt1 = $this->http->FindSingleNode("descendant::text()[{$this->eq('Questions:')}]/following::text()[normalize-space()!=''][1]",
                    $root, false, "#arriving (.+)#");
                $txt2 = $this->http->FindSingleNode("descendant::text()[{$this->eq('Questions:')}]/following::text()[normalize-space()!=''][2]",
                    $root, false, "#departing (.+)#");

                if (!empty($txt1) && !empty($txt2)
                    && ($date1 = $this->normalizeDate($txt1))
                    && ($date2 = $this->normalizeDate($txt2))
                    && $date1 == $r->getStartDate()
                ) {
                    $r->booked()->end($date2);
                } else {
                    $r->booked()->noEnd();
                }
            }
            $confNo = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confirmation'))}]", $root, true, "/[:\s#]+([-A-z\d]{5,})[,.;\s]*$/")
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Tour Operator:'))}]/ancestor::td[1]/descendant::text()[{$this->starts($this->t('confirmation'))}]", $root, true, "/[:\s#]+([-A-z\d]{5,})[,.;\s]*$/")
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Tour Operator:'))}]/ancestor::td[1]/descendant::text()[{$this->eq($this->t('References:'))}]/following::text()[normalize-space()][1]", $root, true, "/^[:\s#]*([-A-z\d]{5,})[,.;\s]*$/")
            ;
            //$this->logger->debug($confNo);

            if (!empty($confNo)) {
                $r->general()->confirmation($confNo);
            } else {
                $r->general()->noConfirmation();
            }
        }

        // details info, reservation
        $r->general()
            ->status($status)
            ->cancellation($cancellation);

        if (isset($travellers)) {
            $r->general()->travellers(array_unique($travellers), true);
        }

        // USA/CA +1-954-837-6290 | JA +1-876-619-1565
        // +52 (322) 226 8413, 1 888.526.2238
        // Local Operator: (0039) 339 298 7284 (Backup: (0039) 0687 569 050)
        $phone = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Tour Operator:'))}][1]/ancestor::td[1]/descendant::text()[{$this->eq($this->t('Contact Info:'))}][1]/following::text()[normalize-space()][1]", $root, false, "/(?:^|[A-Z:]\)?)(?:​)?[\s‪‬]*({$this->patterns['phone']})[\s‪‬]*(?:[,;\|\/]| or |[A-Z(]{2}|[\s(]{2}|$)/mu");
        $operator = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Tour Operator:'))}][1]/following::text()[normalize-space()][1]", $root);
        $r->program()->phone($phone, $operator);

        $sum = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Total Price:'))}]/following::text()[normalize-space()!=''][1]", $root);

        if (!empty($sum)) {
            $sum = $this->getTotalCurrency($sum);
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        if (strlen($notes) < 5000) {
            $r->general()->notes($notes);
        }
    }

    private function setGeoTips(TransferSegment $s): void
    {
        $places = [
            // Argentina
            'ar' => ['Buenos Aires International Airport'],
        ];

        foreach ($places as $countryCode => $keywords) {
            foreach ($keywords as $value) {
                $nameDep = trim($s->getDepName());

                if (!empty($nameDep) && strcasecmp($nameDep, $value) === 0) {
                    $s->departure()->geoTip($countryCode);
                }

                $nameArr = trim($s->getArrName());

                if (!empty($nameArr) && strcasecmp($nameArr, $value) === 0) {
                    $s->arrival()->geoTip($countryCode);
                }
            }
        }
    }

    private function findAddress(\DOMNode $root, string $notes = ''): ?string
    {
        // order is matter

        $xpathTourOperator = "descendant::text()[{$this->eq($this->t('General:'))}]/ancestor::*[../self::tr][1]";
        $pNodes = $this->http->XPath->query($xpathTourOperator . "/descendant::p[normalize-space()]", $root);

        foreach ($pNodes as $replaceNode) { // transformation <p> in <br>
            $innerText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $replaceNode));
            $htmlFragment = $this->http->DOM->createDocumentFragment();
            $htmlFragment->appendXML('<br />' . nl2br(htmlspecialchars($innerText)) . '<br />');
            $replaceNode->parentNode->replaceChild($htmlFragment, $replaceNode);
        }

        $tourOperatorText = $this->htmlToText($this->http->FindHTMLByXpath($xpathTourOperator, null, $root));
        $tourOperatorText = preg_replace(['/[ ]+/', '/[ ]*(\n+)[ ]*/'], [' ', '$1'], $tourOperatorText);
        $general = preg_match("/(?:^\s*|\n){$this->opt($this->t('General:'))}\s*([\s\S]+?)(?:\n{2}|\s*$)/", $tourOperatorText, $m) ? $m[1] : null;

        foreach (array_filter([$general, $notes]) as $text) {
            if (preg_match("/Please(?i) check in.*? at our .+? location, located at\s+(?<a>.+?)(?:[,\s]+at \d|$)/", $text, $m)
                || preg_match("/Please(?i) check in \d{1,3} MINUTES? EARLY at (?<a>our .+)\./", $text, $m)
                || preg_match("/Check(?i)-in \d{1,3} minutes? prior to departure at (?<a>our booth located .+?)\./", $text, $m)
                || preg_match("/We(?i) pickup at (?<a>all Hotels .{3,}?)\./", $text, $m)
                || preg_match("/departs at\s+{$this->patterns['time']}\s+from\s+(?:the\s+)?(?<a>.{2,70}?\([A-Z\d ]{3,}\))\s*(?:[,.;!?]|$)/i", $text, $m)
                || preg_match("/(?:Meet by|Meet at) (?i)(?:the )?(?<a>.{5,50}? (?:statue|square)\b)/", $text, $m)
                || preg_match("/(?<a>The tour operates\D+Airport\.)/", $text, $m)
                /* above - special cases, below - general cases */
                || preg_match("/(?:Address:|The easiest taxi address is for the café:|You will meet your expert guide in front of the refreshment kiosk in|Our address is|^Meet at|located at)\s*(?<a>[^.]*\d[^.]*)\./i", $text, $m)
                || preg_match("/(?:Our office is located at|Please meet at)\s+(?<a>\d.+?)(?:, please arrive|\. Please arrive)/", $text, $m)
                || preg_match("/Meet us at\s+(?<a>\d.+?)\s+\((?:opposite|nearly|behind|in front)/", $text, $m)
                || preg_match("/Please meet[\'’[:alpha:] ]* at \b[^.\[\])(]{3,}\b \(\s*(?<a>[^)(]*\d[^)(]*?)\s*\)$/u", $text, $m) && preg_match("/[[:alpha:]]{2}/u", $m[1])
                || preg_match("/(?:Departure|Depart|(?i)leaves and returns(?-i)) from\s+(?<a>.{10,70}?)[.;! ]*$/m", $text, $m) && preg_match("/[[:alpha:]]{2}/u", $m[1])
                || preg_match("/(?:Meet us at our office at|Meet us at the ranch -|The meeting point is(?:\s+in front of)?|Meet in)\s+(?<a>.{3,70}?)(?:[,\s]+at \d|, or .+|$)/", $text, $m)
                || preg_match("/Meet at\s+(?<a>.{15,70}?)\s*(?:[:].{3,}|, at the corner of)/", $text, $m)
                || preg_match("/We'll be waiting for you at (?:[Tt]he )?(?<a>.{15,70}?)(?:, the .+|\.)/", $text, $m)
                || preg_match("/Tour\s*Starts:(?<a>.+)\/\s*Departs/", $text, $m)
                || preg_match("/We depart from (?<a>.{15,70})\. We are located in/", $text, $m)
                || preg_match("/Your guide will meet you at (?:[Tt]he )?(?<a>.{15,70})\. This is/", $text, $m)
                || preg_match("/in front of\s*(?<a>.+?)\. Once at/", $text, $m)
                || preg_match("/Check in at our\s+(?<a>.+)\s+location\./", $text, $m)
                || preg_match("/(?:Tour departs at |Tour departs\s*:)\s*(?<a>.{3,100}?)(?-i)(?:[,\s]+at \d|\s*-\s|\s+[(])/i", $text, $m)
                || preg_match("/Our tours start right\s+(?<a>.+?)\./", $text, $m)
                || preg_match("/\[(?<a>.+)\]/", $text, $m)
                || preg_match("/^(?<a>.+)\nPlease be here/su", $text, $m)
            ) {
                return $m['a'];
            }
        }

        $pickup = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Pickup from our office')]", $root,
            false, "#{$this->opt($this->t('Pickup from our office'))}[,:\s]*(.+?)(?: at \d+|\(\d+:\d+|$)#");

        if (!empty($pickup)) {
            return $pickup;
        }

        $pickup = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'Pickup Location:')]", $root, false,
            "#{$this->opt($this->t('Pickup Location:'))}[,:\s]*(.+?)(?: at \d+|\(\d+:\d+|$)#");

        if (!empty($pickup)) {
            return $pickup;
        }

        $point = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Meeting point')][1]", $root,
            false,
            "#{$this->opt($this->t('Meeting point'))}\s*(.+?)(?: at \d+|\(\d+:\d+|$)#");

        if (!empty($point)) {
            return $point;
        }

        $point = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Meet us at our office at')]",
            $root, false, "#{$this->opt($this->t('Meeting point'))}\s*(.+?)(?: at \d+|\(\d+:\d+|$)#");

        if (!empty($point)) {
            return $point;
        }

        $point = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Tour departs from') and contains(normalize-space(),'office located on')]",
            $root, false, "#{$this->opt($this->t('office located on'))}\s*(.+)#");

        if (!empty($point)) {
            return $point;
        }

        $point = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'please note that the meeting point for this tour is in')]",
            $root, false, "#please note that the meeting point for this tour is in (.+?)\. Please be there no later#");

        if (!empty($point)) {
            return $point;
        }

        $point = $this->http->FindSingleNode("descendant::text()[{$this->contains(['The address to our location is', 'at the following location:'])}]",
            $root, false, "/{$this->opt(['The address to our location is', 'at the following location:'])}\s*(.+?)\.(?:[^\.]+(?:opens at|\d{1,2}:\d{2})|$)/i");

        if (!empty($point)) {
            return $point;
        }

        $point = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Meeting Point Directions')]/following-sibling::text()[normalize-space()][1]", $root)
            ?? $this->http->FindSingleNode("descendant::text()[normalize-space()='Meeting point']/following-sibling::text()[normalize-space()][1]", $root, true, "/^(.{3,70}?)[.;!\s]*$/");

        if (!empty($point)) {
            return $point;
        }

        $nameTour = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Ref:')]/ancestor::td[1]/preceding::text()[normalize-space()!=''][1]", $root);

        if (preg_match("#^.+? at ([A-Z]{3} Airport)$#", $nameTour, $m)) {
            return $m[1];
        }

        $meetAddressRows = $this->http->FindNodes("descendant::text()[ preceding::text()[normalize-space()!=''][position()<5][{$this->contains($this->t('Please meet us at the following address:'))}] and following::text()[normalize-space()!=''][position()<5][{$this->contains($this->t('Tour Operator:'))}] ]", $root);

        if (count($meetAddressRows)) {
            $meetAddressRows = array_map(function ($item) {
                return trim($item, ', ');
            }, $meetAddressRows);

            return implode(', ', $meetAddressRows);
        }

        $meetAddress = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('You will be picked up'))} and {$this->contains($this->t('from in opposite of'))}]",
            $root, false, "/{$this->t('from in opposite of')}\s*(.+), in front of/");

        if (!empty($meetAddress)) {
            // You will be picked up at 9am from in opposite of Amsterdam Centraal Station, in front of the Saint Nicholas church and hotel NH Barbizon Palace, Prins Hendrikkade 59-73
            return $meetAddress;
        }

        $meetAddress = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('will pick you up at'))} and {$this->contains($this->t('Address'))}]",
            $root, false, "/\(\s*{$this->t('Address')}\s*:\s*(.+?)\)\./");

        if (!empty($meetAddress)) {
            // Good morning, thank you for your booking. Your guide should be Audrey (+33 6 31 07 47 34 / +33 2 47 79 40 20). She will pick you up at 09.00am at the tourist office of Tours (Address : 78-82 Rue Bernard Palissy, 37000 Tours). Have a great day. Kind regards, Freddy
            return $meetAddress;
        }

        $meetAddress = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Our meet-up is at our office located across the'))}]",
            $root, false, "/{$this->t('Our meet-up is at our office located across the')}\s*(.+)/");

        if (!empty($meetAddress)) {
            //Our meet-up is at our office located across the Honoapilani Hwy (HWY 30) from the Napili Market on Napilihau St.
            return $meetAddress;
        }

        $meetAddress = $this->http->FindSingleNode("descendant::text()[{$this->contains('We meet at the')} and {$this->contains('Yogurt Bar located on 3, Dionysiou Areopagitou St.')}]", $root);

        if (!empty($meetAddress)) {
            // We meet at the "FRESKO" Yogurt Bar located on 3, Dionysiou Areopagitou St. which is at the beginning of the pedestrian walkway that takes you from Hadrian's Arch to the Acropolis.
            return "Yogurt Bar located on 3, Dionysiou Areopagitou St.";
        }

        // it-8.eml, it-9.eml
        $accomodation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Accommodation:'))}]/following::text()[normalize-space()!=''][1][not(contains(normalize-space(), 'Pick up please'))]", $root);

        if (empty($accomodation)) {
            $accomodation = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Accommodation:'))}]/following::text()[normalize-space()!=''][2]", $root, true, "#\s+at\s+(.+\([A-Z]{3}\))#u");
        }

        if (preg_match("/\s+at\s+(.+\([A-Z]{3}\))/u", $accomodation, $m)) {
            $accomodation = $m[1];
        }

        if (!empty($accomodation)) {
            return $accomodation;
        }

        // GPS Coordinates: 51.501119, -0.123778
        $gpsCoordinates = null;
        $gpsValues = array_filter($this->http->FindNodes("descendant::text()[{$this->starts($this->t('GPS Coordinates'))}]", $root, "/^{$this->opt($this->t('GPS Coordinates'))}[:\s]+({$this->patterns['gps']}[ ]*,[ ]*{$this->patterns['gps']})(?:\s*[;!]|\s*[,.][ ]*\D|[.;!\s]*$)/u"));

        if (count(array_unique($gpsValues)) === 1) {
            $gpsCoordinates = array_shift($gpsValues);

            return $gpsCoordinates;
        }
        $url = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tour Operator:'))}][1]/ancestor::td[1]//a[contains(., 'goo.gl/maps')]/@href");

        if (!empty($url)) {
            $http2 = clone $this->http;
            $http2->GetURL($url);
            $location = $http2->FindSingleNode('//meta[@property="og:title"]/@content');

            if (!empty($location)) {
                return $location;
            }
        }

        /*
            Always last! Don't remove!
            This is legal life hack for success parsing events. Approved by Project Expedition. For more information, see case in Redmine.
        */
        $specialAddress = $this->http->FindSingleNode("descendant::*[not(.//tr) and contains(@id,'project-expedition-address')]", $root)
            ?? $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Booked through Project Expedition'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][1]/descendant::text()[normalize-space()='188 Grand Street, New York City, New York, 10013, US']", $root);

        if (!empty($specialAddress)) {
            $this->logger->notice('Collected special address!');

            return $specialAddress;
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            // September 23, 2019, 9:00 am  |  October 31, 2019 8:15 AM
            '/^([-[:alpha:]]+)\s+(\d{1,2})[,\s]+(\d{4})[,\s]+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$/u',
            // Nov 16 at 245pm
            '/^([-[:alpha:]]+)\s+(\d{1,2})\s+at\s+(\d{1,2})(\d{2}(?:\s*[ap]\.?m\.?)?)$/iu',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$2 $1 ' . $year . ', $3:$4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(?string $text = null): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Traveler Names']) || empty($phrases['Notes from Tour Operator'])) {
                continue;
            }

            if (empty($text)
                && $this->http->XPath->query("//*[{$this->contains($phrases['Traveler Names'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Notes from Tour Operator'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            } elseif (!empty($text)
                && $this->stripos($text, $phrases['Traveler Names'])
                && $this->stripos($text, $phrases['Notes from Tour Operator'])
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function stripos($haystack, $arrayNeedle): bool
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
}
