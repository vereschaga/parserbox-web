<?php

namespace AwardWallet\Engine\turkish\Transfer;

// For use with LoadRegistrationOptionsCommand
use AwardWallet\Engine\ProxyList;

class RegistrationOptionsLoader extends \TAccountChecker
{
    use ProxyList;

    public $timeout = 10;
    protected $registrationUrl = 'https://www4.thy.com/tkmiles/membersignin.tk';

    public function InitBrowser()
    {
        $this->UseCurlBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy('localhost:8000');
        } // This provider should be tested via proxy even locally
    }

    public function load($step = null)
    {
        $logDir = $this->http->LogDir;
        echo "Logs dir: $logDir/\n";
        $logUrl = 'http://awardwallet.local/admin/common/logFile.php?Dir=' . urlencode($this->http->LogDir) . '&File=' . urlencode("log.html");
        echo "Logs link: $logUrl\n";

        if ($step == 1 or $step == null) {
            $this->loadCities();
        }

        if ($step == 2 or $step == null) {
            $this->loadDistricts();
        }
    }

    public function loadCities()
    {
        echo "Loading cities\n";
        $this->http->GetURL($this->registrationUrl);
        $countries = array_filter($this->http->FindNodes('//select[@name="homeCountryCode"]/option/@value'));
        $citiesByCountry = [];
        $i = 1;
        $countriesCount = count($countries);

        foreach ($countries as $c) {
            echo "[$i/$countriesCount] Loading cities for $c: ";
            $this->http->PostURL('https://www4.thy.com/tkmiles/membersignin.tk?method=listCities&countryCode=' . $c . '&targetList=homeCountry', []);
            $cityNodes = $this->http->XPath->query('//select[@name="homeCity"]/option');
            $count = 0;

            if ($cityNodes->length) {
                foreach ($cityNodes as $cn) {
                    $cityCode = $this->http->FindSingleNode('./@value', $cn);
                    $cityName = $this->http->FindSingleNode('./text()', $cn);

                    if (!$cityCode) {
                        continue;
                    }
                    $citiesByCountry[$c][$cityCode] = $cityName;
                    $count++;
                }
            }
            echo $count . "\n";
            $i++;
        }
        $path = 'cities.json';
        file_put_contents($path, json_encode($citiesByCountry, JSON_PRETTY_PRINT));
        echo "Saving result to $path\n";
    }

    public function loadDistricts()
    {
        echo "Loading districts\n";
        $this->http->GetURL($this->registrationUrl);
        $c = 'TR';
        $cities = [];
        $disctrictsByCity = [];
        $this->http->PostURL('https://www4.thy.com/tkmiles/membersignin.tk?method=listCities&countryCode=' . $c . '&targetList=homeCountry', []);
        $cityNodes = $this->http->XPath->query('//select[@name="homeCity"]/option');
        $count = 0;

        if ($cityNodes->length) {
            foreach ($cityNodes as $cn) {
                $cityCode = $this->http->FindSingleNode('./@value', $cn);
                $cityName = $this->http->FindSingleNode('./text()', $cn);

                if (!$cityCode) {
                    continue;
                }
                $cities[$cityCode] = $cityName;
                $count++;
            }

            $i = 1;
            $citiesCount = count($cities);

            foreach ($cities as $cityCode => $cityName) {
                echo "[$i/$citiesCount] Loading states for $cityName: ";
                $this->http->PostURL('https://www4.thy.com/tkmiles/membersignin.tk?method=listDistrict&cityCode=' . $cityCode . '&targetList=homeCity', []);
                $disctrictNodes = $this->http->XPath->query('//select[@name="homeDistrict"]/option');
                $count = 0;

                if ($disctrictNodes->length) {
                    foreach ($disctrictNodes as $dn) {
                        $districtCode = $this->http->FindSingleNode('./@value', $dn);
                        $districtName = $this->http->FindSingleNode('./text()', $dn);

                        if (!$districtCode) {
                            continue;
                        }
                        $disctrictsByCity[$cityCode][$districtCode] = $districtName;
                        $count++;
                    }
                }
                echo "$count\n";
                $i++;
            }
        }
        $path = 'districts.json';
        file_put_contents($path, json_encode($disctrictsByCity, JSON_PRETTY_PRINT));
        echo "Saving result to $path\n";
    }
}
