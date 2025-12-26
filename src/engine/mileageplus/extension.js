var plugin = {
    hideOnStart: true,
    clearCache: true,
    //keepTabOpen: true,//todo
    hosts: {'www.united.com': true},

    getStartingUrl: function (params) {
        return 'https://www.united.com/en/us/myunited';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    plugin.login(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        const switchAccountsLink = $('button#switch-account-button');
        if(switchAccountsLink.length) {
            switchAccountsLink.click();
        };
        
        if ($('#MPNumber:visible, #MPIDEmailField:visible').length)
            return false;
        if ($('span:contains("MileagePlus Number"):visible').length)
            return true
        return null;
    },

    isSameAccount: function (account) {
        const number = util.findRegExp($('div span:contains("MileagePlus Number")').parent().text(), /MileagePlus\s*Number\s*([^<]+)/);
        browserAPI.log("number on page: " + number);
        browserAPI.log("login: " + account.login);
        return (number && number.toLowerCase() === account.login.toLowerCase());
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            plugin.clearCookie();
            $('button#loginButton').click();
            $('button span:contains("SIGN OUT")').click();
            setTimeout(function () {
                document.location.href = plugin.getStartingUrl();
            }, 3000);
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("Loading login form");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");
       /* let login = $('input[name = "MileagePlusLogin.MPIDEmailField"]');
        login.val(params.account.login);
        util.sendEvent(login.get(0), 'click');
        util.sendEvent(login.get(0), 'blur');
        util.sendEvent(login.get(0), 'change');
        util.sendEvent(login.get(0), 'input');*/

        provider.eval(
            "var FindReact = function (dom) {\n"
            + "for (var key in dom) if (0 == key.indexOf(\"__react\")) {\n"
            +    "return dom[key];\n"
            + "}\n"
            + "return null;\n"
            + "};\n"
            + "var createTextInputEvent = function(text) {\n"
            + "  var event = new CustomEvent('change', {\n"
            + "       'bubbles': true,\n"
            + "       'cancelable': true,\n"
            + "   });\n"
            + "   Object.defineProperty(event, 'target', {value: {value: text}, enumerable: true});\n"
            + "   return event;\n"
            + "};\n"
            + "FindReact(document.querySelector('#MPIDEmailField')).pendingProps.onChange(createTextInputEvent('" + params.account.login + "'));\n"
            //+ "FindReact(document.querySelector('#app > div > div > div > div.page > div.relativePosition > div > div > div > section > section > div')).memoizedProps.children.props.onSubmit({MPNumber:'" + params.account.login + "', password:'" + params.account.password + "', rememberMe: true})"
        );

        $('button[type = "submit"]').get(0).click();

        util.waitFor({
            timeout: 5,
            selector: 'input[name = "password"]:visible',
            success: function(elem) {
                setTimeout(function () {
                    provider.eval(
                        "var FindReact = function (dom) {\n"
                        + "for (var key in dom) if (0 == key.indexOf(\"__react\")) {\n"
                        +    "return dom[key];\n"
                        + "}\n"
                        + "return null;\n"
                        + "};\n"
                        + "var createTextInputEvent = function(text) {\n"
                        + "  var event = new CustomEvent('change', {\n"
                        + "       'bubbles': true,\n"
                        + "       'cancelable': true,\n"
                        + "   });\n"
                        + "   Object.defineProperty(event, 'target', {value: {value: text}, enumerable: true});\n"
                        + "   return event;\n"
                        + "};\n"
                        + "FindReact(document.querySelector('#password')).pendingProps.onChange(createTextInputEvent('" + params.account.password + "'));\n"
                        //+ "FindReact(document.querySelector('#app > div > div > div > div.page > div.relativePosition > div > div > div > section > section > div')).memoizedProps.children.props.onSubmit({MPNumber:'" + params.account.login + "', password:'" + params.account.password + "', rememberMe: true})"
                    );
                    // elem.val(params.account.password);
                    // util.sendEvent(elem.get(0), 'input');
                    $('label:contains("Remember me")').click();
                    util.sendEvent($('label:contains("Remember me")').get(0), 'input');

                    provider.setNextStep('checkQuestion', function () {
                        $('button[type = "submit"]').get(0).click();
                    });
                    setTimeout(function () {
                        plugin.checkQuestion(params);
                    }, 3000);
                }, 500);
            },
            fail: function() {
                plugin.checkQuestion(params);
            }
        });
    },

    clearCookie : function() {
        try {
            var cookies = document.cookie.split(';');
            for (var i = 0; i < cookies.length; i++) {
                var cookie      = cookies[i];
                var eqPos       = cookie.indexOf('=');
                var name        = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
                $.removeCookie(name);
            }
        } catch (error) {}
    },

    checkQuestion: function (params) {
        browserAPI.log('Checking security questions');
        setTimeout(function () {
            if ($('label:contains("Enter the verification code sent to ")').length > 0) {
                $('label:contains("require verification code again.")').click();
                util.sendEvent( $('label:contains("require verification code again.")').get(0), 'input');

                provider.setError(['It seems that United Airlines (Mileage Plus) needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Airfrance authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                return;
            }

            plugin.checkLoginErrors(params);
        }, 3000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("[checkLoginErrors]: Checking login status");
        const errors = $('#MPIDEmailField_error:visible, div[id^="alert-body-atm-x-autogenerated-id_"]');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
        //plugin.loginComplete(params);
    },

	loginComplete: function(params) {
        browserAPI.log("loginComplete");
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.united.com/web/en-US/apps/reservation/default.aspx?MobileOff=1';
            });
		}
		else {
            provider.setNextStep('loadAccount', function () {
                document.location.href = 'https://www.united.com/en/us/account';
            });
        }
	},

	toItineraries: function(params) {
		var numbers = $('span[id*="_spanConfNum"]');
		var confNo = params.account.properties.confirmationNumber;
		var j = -1;
		for (var i = 0; i < numbers.length; i++){
			var conf = $(numbers.get(i)).text();
			if (conf == confNo) {
				j = i;
				break;
			}
		}
		var links = $('span[id*="Flight_spanView"] a[id*="Flight_linkView"]');
		for (i = 0; i < links.length; i++) {
			if ($(links.get(i)).text() == 'View') {
				if (j == 0) {
					provider.setNextStep('itLoginComplete', function(){
                        document.location.href = ($(links.get(i)).attr('href'));
                    });
					return;
				}
				else
					j--;
			}
		}
		provider.complete();
	},

	itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
		provider.complete();
	},

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        if (params.autologin) {
            provider.complete();
            return;
        }
        provider.saveTemp(0); // retry count
        provider.setNextStep('regionalPreference', function () {
            document.location.href = 'https://www.united.com/web/en-US/apps/account/preference/regionalPreference.aspx?MobileOff=1';
        });
        browserAPI.log("Loading account");
    },

    regionalPreference: function (params) {
        browserAPI.log("Regional Preference");
        params.data.dateFormatUS = null;

        var preference = $('input:checked[name = "ctl00$ContentInfo$DateTime$Date"]').attr('value');
        if (typeof(preference) != 'undefined') {
            browserAPI.log("Regional Preference " + preference);
            if (preference.toLowerCase() == 'dateformatus')
                params.data.dateFormatUS = true;
            else {
                if (preference.toLowerCase() == 'dateformatuk')
                    params.data.dateFormatUS = false;
                else
                    browserAPI.log("Unknown Format Preferences >>>> " + preference);
            }
        }
        else {
            browserAPI.log('Date Format not Found');
            if($('#ctl00_ContentInfo_SignIn_onepass_txtField').length > 0){
                /**
                 * united bug, you are logged in on first page, but not logged in on other pages
                 *
                 * to reproduce:
                 *
                 *    scenario 1:
                 *      1. auto-login into united
                 *      2. keep browser open for 1 hour, do not surf on united.com, let session expire
                 *      3. close united tab
                 *      4. auto-login again
                 *      5. Seems like you are logged in
                 *      6. click on "View Account" button in yellow box with personal info
                 *      7. you will see login form again
                 *
                 *    scenario 2:
                 *      1. auto-login to united
                 *      2. modify SID cookie: change 1 char
                 *      3. .. same steps
                 *
                 *    scenario 3:
                 *      - check multiple united accounts in row
                 *
                 **/
                browserAPI.log('login form detected, trying to relogin, attempt: ' + params.data);
                if(params.data > 3){
                    browserAPI.log('too many attempts');
                    provider.setError(["Can't relogin", util.errorCodes.engineError]);
                    return;
                }
                else {
                    provider.saveTemp(params.data + 1);
                    $('#ctl00_ContentInfo_SignIn_onepass_txtField').val(params.account.login);
                    $('#ctl00_ContentInfo_SignIn_password_txtPassword').val(params.account.password);
                    provider.setNextStep('regionalPreference', function () {
                        setTimeout(function () {
                            provider.eval("$('#ctl00_ContentInfo_SignInSecure').click()");
                        }, 1500);
                    });
                    return;
                }
            }
        }

        browserAPI.log("Date Format: " + params.data.dateFormatUS);

        if (document.location.href != 'https://www.united.com/web/en-US/apps/account/account.aspx') {
            provider.setNextStep('parse', function () {
                document.location.href = 'https://www.united.com/web/en-US/apps/account/account.aspx';
            });
        }
        else
            plugin.parse();
    },

    basename: function (path) {
        return path.split('/').reverse()[0];
    },

    parse: function (params) {
        browserAPI.log("parse");
        var data = {};
        // Balance - Mileage balance
        var balance = $('span[id *= "lblMileageBalanceNew"]');
        if (balance.length > 0) {
            balance = util.findRegExp( balance.text(), /([\-\d\.\,\s]+)/i);
            browserAPI.log("Balance: " + balance);
            data.Balance = util.trim(balance);
        } else
            browserAPI.log("Balance not found");
        // Name
        var name = $('span[id *= "lblOPNameNew"]');
        if (name.length > 0) {
            browserAPI.log("Name: " + name.text());
            data.Name = util.beautifulName(name.text());
        } else
            browserAPI.log("Name not found");
        // MileagePlus number
        var number = $('span[id *= "lblOPNumberNew"]');
        if (number.length > 0) {
            browserAPI.log("MileagePlus number: " + number.text());
            data.Number = number.text();
        } else
            browserAPI.log("MileagePlus number not found");
        // Elite Level
        var memberStatus = $('img[id *= "imgMPLevel"]');
        if (memberStatus && memberStatus.length > 0) {
            memberStatus = plugin.basename(memberStatus.attr('src'));
            browserAPI.log("Logo: " + memberStatus);
            memberStatus = util.findRegExp( memberStatus, /(.+)_Status/);
            browserAPI.log("Elite Level: " + memberStatus);
            if (memberStatus) {
                if (memberStatus == 'GS')
                    data.MemberStatus = 'Global Services';
                else
                    data.MemberStatus = 'Premier '+ memberStatus;
            }
            else
                browserAPI.log("Bad Elite Level");
        } else {
            browserAPI.log("Elite Level not found");
            browserAPI.log("Elite Level: Member");
            data.MemberStatus = 'Member';
        }
        // Expiration Date // refs #4815
        var expiration = $('span[id *= "lblMileageExpireDateNew"]');
        if (expiration.length > 0) {
            browserAPI.log("Expiration Date: " + expiration.text());
            browserAPI.log("Date Format: " + params.data.dateFormatUS);
            if (params.data.dateFormatUS == false)
                expiration = util.modifyDateFormat(expiration.text());
            else
                expiration = expiration.text();
            if (expiration != null) {
                var date = new Date(expiration + ' UTC');
                var unixtime = date / 1000;
                if (!isNaN(date)) {
                    browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime);
                    data.AccountExpirationDate = unixtime;
                }else
                    browserAPI.log("Invalid Expiration Date");
            }
        } else
            browserAPI.log("Expiration Date not found");
        // YTD qualifying miles
        var eliteMiles = $('span[id *= "spanEliteMilesNew"]:visible');
        if (eliteMiles.length > 0) {
            browserAPI.log("YTD qualifying miles: " + eliteMiles.text());
            data.EliteMiles = eliteMiles.text();
        } else
            browserAPI.log("YTD qualifying miles not found");
        // YTD Premier qualifying segments
        var eliteSegments = $('span[id *= "spanEliteSegmentsNew"]:visible');
        if (eliteSegments.length > 0) {
            browserAPI.log("YTD Premier qualifying segments: " + eliteSegments.text());
            data.EliteSegments = eliteSegments.text();
        } else
            browserAPI.log("YTD Premier qualifying segments not found");
        // Star Alliance Status
        var starAllianceStatus = util.findRegExp( $('div[id *= "spanSAEliteLevel"]').text(), /Star\s*Alliance\s*([^<]+)/);
        if (starAllianceStatus && starAllianceStatus.length > 0) {
            browserAPI.log("Star Alliance Status: " + util.trim(starAllianceStatus));
            data.StarAllianceStatus = util.trim(starAllianceStatus);
        } else
            browserAPI.log("Star Alliance Status not found");
        // Regional Premier Upgrades
        /*var regionalUpgrades = $('#ctl00_ContentInfo_AccountSummary_spanRegionalUpgradeCount').text();
        if (regionalUpgrades.length > 0) {
            browserAPI.log("Regional Premier Upgrades: " + util.trim(regionalUpgrades));
            data.RegionalUpgrades = util.trim(regionalUpgrades);
        } else
            browserAPI.log("Regional Premier Upgrades not found");
        // Global Premier Upgrades
        var globalPremierUpgrades = $('#ctl00_ContentInfo_AccountSummary_spanSystemWideUpgradeCount').text();
        if (globalPremierUpgrades.length > 0) {
            browserAPI.log("Global Premier Upgrades: " + util.trim(globalPremierUpgrades));
            data.GlobalPremierUpgrades = util.trim(globalPremierUpgrades);
        } else
            browserAPI.log("Global Premier Upgrades not found");*/
        // Lifetime Flight Miles
        var lifetimeMiles = $('span[id *= "lblEliteLifetimeMilesNew"]:visible');
        if (lifetimeMiles.length > 0) {
            browserAPI.log("Lifetime Flight Miles: " + lifetimeMiles.text());
            data.LifetimeMiles = lifetimeMiles.text();
        } else
            browserAPI.log("Lifetime Flight Miles not found");

        //browserAPI.log("parseSubAccounts");
        //var subAccounts = [];
        //// Regional Premier Upgrades
        //subAccounts = plugin.parseSubAccounts($("div#spanRegionalUpgradeBreakout div div"), 'Regional');
        //// Global Premier Upgrades
        //$.merge(subAccounts, plugin.parseSubAccounts($("div#spanSystemUpgradeBreakout div div"), 'Global'));
        //console.log(subAccounts);
        //data.SubAccounts = subAccounts;
        //data.CombineSubAccounts = 'false';

        params.account.properties = data;
        provider.saveProperties(data);
        // Parsing itineraries
        if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {

            if ( util.findRegExp( $('#ctl00_ContentInfo_trNoCurrentReservations').text(), /(No Current Reservations)/i) ){
                browserAPI.log("No Current Reservations");
                params.account.properties.Itineraries = [{ NoItineraries: true }];
                provider.saveProperties(params.account.properties);
                provider.complete();
                return;
            }

            // open page with all itineraries
            provider.setNextStep('parseItineraries', function () {
                document.location.href = 'https://www.united.com/web/en-US/apps/reservation/default.aspx';
            });
        }
		else
			provider.complete();
    },

    parseSubAccounts: function (nodes, type) {
        browserAPI.log("SubAccounts: " + type);
        var i = 0;
        var subAccounts = [];
        nodes.each(function () {
            var node = util.trim($(this).text());
            browserAPI.log(">>> " + node);

            var balance = util.findRegExp(node, /^(\d+)/i);
//            browserAPI.log("Balance: " + balance);

            var exp = util.findRegExp(node, /Expiring[\n\t\r\s]*(.*)/i);
//            browserAPI.log("exp: " + exp);
//            browserAPI.log("Date Format: " + params.data.dateFormatUS);

            if (params.data.dateFormatUS == false)
                exp = util.ModifyDateFormat(exp);
            exp = new Date(exp + ' UTC');
            var unixtime = exp / 1000;
            if (!isNaN(exp) && balance > 0) {
//                browserAPI.log("Expiration Date: " + exp + " Unixtime: " + unixtime );
                subAccounts.push({
                    "Code": type + 'PremierUpgrades' + i,
                    "DisplayName": type + ' Premier Upgrades',
                    "Balance": balance,
                    "ExpirationDate": unixtime
                });
            } else if (balance > 0) {
                subAccounts.push({
                    "Code": type + 'PremierUpgrades' + i,
                    "DisplayName": type + ' Premier Upgrades',
                    "Balance": balance
                });
            }
            i++;
//            console.log(subAccounts);
        });

        return subAccounts;
    },


    parseItineraries: function(params){
        browserAPI.log("Parsing itineraries");

        if ( util.findRegExp( $('#ctl00_ContentInfo_trNoCurrentReservations').text(), /(No Current Reservations)/i) ){
            browserAPI.log("No Current Reservations");
            params.account.properties.Itineraries = [{ NoItineraries: true }];
            provider.saveProperties(params.account.properties);
            provider.complete();
            return;
        }

        params.data.Itineraries = [];
        params.data.links = [];
        params.data.TripSegments = [];
        params.data.Rentals = [];

        var links = $('a[id $= linkView]:visible');
        browserAPI.log('Total links ' + links.length);
        for (var i = 0; i < links.length; i++){
            var link = links.eq(i).attr('href');
            link = 'https://www.united.com/web/en-US/apps/reservation/' + link;
            params.data.links.push(link);
        }

        if(provider.isMobile){
            var nextLink = params.data.links.shift();
        }
        provider.setNextStep('parseItinerary', function () {
            if(provider.isMobile){
                document.location.href = nextLink;
            }else{
                document.location.href = params.data.links.shift();
            }
        });
    },

    parseItinerary: function(params){
        browserAPI.log("parseItinerary");

        var result = {};
        var depCode = null;
        var depart = null;
        var depTime = null;
        var DT = null;
        var arrCode = null;
        var arrive = null;
        var arrTime = null;
        var unixtime = null;
        var duration = null;
        var flightNumber = null;
        var aircraft = null;
        var bookingClass = null;
        var meal = null;

        // ConfirmationNumber
        result.RecordLocator = $('span.uaConfirmationNumber').text();
        browserAPI.log("RecordLocator: " + result.RecordLocator);

        // Passengers
        var passenger = plugin.unionArray( $('div.traveler h4'), ', ', true);
        browserAPI.log("Passengers: " + passenger);
        if (passenger)
            result.Passengers = passenger.replace(/, $/, '');
        if (result.Passengers == '')
            delete result.Passengers;
        if (typeof (result.Passengers) != 'undefined' && result.Passengers) {
            result.Passengers = util.beautifulName(result.Passengers);
            browserAPI.log("Passengers: " + result.Passengers);
        }

        // ReservationDate
        var reservationDate = util.findRegExp( $('span#ctl00_ContentInfo_ViewRes_spanTicketed, span#ctl00_ContentInfo_spanTicketed').text(), /confirmed on(.*)Central Time/i);
        if (reservationDate) {
            reservationDate = util.trim(reservationDate.replace(/\.m\.([^<]+)/g, 'm'));
            reservationDate = util.trim(reservationDate.replace(/\./g, ''));
            reservationDate = util.trim(reservationDate.replace(/at\s*/g, ''));
            reservationDate = new Date(reservationDate + ' UTC');
            unixtime = reservationDate / 1000;
            if (!isNaN(unixtime)) {
                browserAPI.log("ReservationDate: " + reservationDate + " Unixtime: " + unixtime);
                result.ReservationDate = unixtime;
            }else
                browserAPI.log(">>> Invalid ReservationDate");
        }
        else
            browserAPI.log(">>> ReservationDate not found");

        // Tax
        var tax = util.trim($(':has(span[id $= Taxes])').next('td').text());
        browserAPI.log("Tax: " + tax);
        result.Tax = tax;
        // BaseFare
        var baseFare = util.trim($(':contains("Adult")').next('td').text());
        browserAPI.log("BaseFare: " + baseFare);
        result.BaseFare = baseFare;
        if (result.BaseFare == '') delete result.BaseFare;
        // Total
        var total = $(':contains("Total")').next('td[id *= PriceRevenueSummary]').text();
        if (total.trim() == '' || typeof(total) == 'undefined'){
            total = $(':has(b:contains("Total"))').next('td[id *= PriceRewardSummary]').text();
            total = total.replace(/\s+/g, ' ');
        }
        browserAPI.log("TotalCharge: " + total);
        result.TotalCharge = total;
        // Currency
        if (util.findRegExp(result.TotalCharge, /(\$)/))
            result.Currency = 'USD';
        browserAPI.log("Currency: " + result.Currency);

        // Segments
        var i = 0;
        var totalSegments = $('div.divTrips div.divTrip');
        browserAPI.log(">>> Total segments: " + totalSegments.length);
        totalSegments.each(function () {
            var node = $(this);
            browserAPI.log(">>> Trip " + i);
            var k = 0;

            node.find('tr:odd, > div > div:odd').each(function () {
				var segments = {};
                browserAPI.log(">>> Segment " + k);
                var segment = $(this);

                // FlightNumber
                flightNumber = segment.find('td.tdSegmentDtl').find('div:contains("Flight") > b').text();
                if (!flightNumber)
                    flightNumber = segment.find('.tdSegmentDtl').find('li:contains("Flight") > strong').text();
                browserAPI.log("FlightNumber: " + flightNumber);
                segments.FlightNumber = flightNumber;

                // Aircraft
                aircraft = segment.find('td.tdSegmentDtl').find('div:contains("Aircraft") > b').text();
                if (!aircraft)
                    aircraft = segment.find('.tdSegmentDtl').find('li:contains("Aircraft") > strong').text();
                browserAPI.log("Aircraft: " + aircraft);
                segments.Aircraft = aircraft;

                // BookingClass
                bookingClass = segment.find('td.tdSegmentDtl').find(':contains("Fare Class") > b').text();
                if (!bookingClass)
                    bookingClass = segment.find('.tdSegmentDtl').find(':contains("Fare Class") > b').text();
                segments.BookingClass = util.findRegExp(bookingClass, /\(([^\)]+)/i);
                browserAPI.log("BookingClass: " + segments.BookingClass);

                // Meal
                meal = segment.find('td.tdSegmentDtl').find('div:contains("Meal") > b').html();
                if (!meal)
                    meal = segment.find('.tdSegmentDtl').find('li:contains("Meal") > strong').html();
                if (meal)
                    meal = util.trim(meal.replace(/<br>/g, ', '));
                browserAPI.log("Meal: " + meal);
                segments.Meal = meal;

                // DepDate
                depTime = segment.find('.tdDepart').find('div:has(strong)').text();
//                browserAPI.log("time: " + depTime);
                depart = util.findRegExp( segment.find('.tdDepart').find('div:has(b)').text(), /\,\s*(.+)/);
                if (!depart)
                    depart = util.findRegExp( segment.find('.tdDepart').find('div:has(strong)').text(), /\,\s*(.+)/);
//                browserAPI.log("depart: " + depart);
                var departDay = util.findRegExp(depart, /\.m\.\s*\+?([\d]+)\s*Day/i);
                DT = depart + ' ' + depTime;
                DT = util.trim(DT.replace(/\.m\.([^<]+)/g, 'm'));
//                browserAPI.log("DT: " + DT);
                DT = util.trim(DT.replace(/\./g, ''));
                browserAPI.log("DT: " + DT);
                DT = new Date(DT + ' UTC');
                if (departDay) {
                    browserAPI.log("day: +" + departDay);
                    DT.setDate(DT.getDate() + parseFloat(departDay));
                    browserAPI.log("Date: " + DT);
                }
                unixtime = DT / 1000;
                if (!isNaN(unixtime)) {
                    browserAPI.log("DepDate: " + depart + ' ' + depTime + " Unixtime: " + unixtime);
                    segments.DepDate = unixtime;
                }else
                    browserAPI.log(">>> Invalid DepDate");
                // DepName
                depCode = segment.find('.tdDepart').find('div:last-child').text();
//                depCode = segment.find('td.tdDepart').find('div:nth-child(4)').text();
                segments.DepName = util.findRegExp(depCode, /([^\(]+)/i);
                browserAPI.log("DepName: " + segments.DepName);
                // DepCode
                segments.DepCode = util.findRegExp(depCode, /\(([^\)\-]+)/i);
                browserAPI.log("DepCode: " + segments.DepCode);

                // ArrDate
                arrTime = segment.find('.tdArrive').find('div:has(strong)').text();
                browserAPI.log("arrive time: " + arrTime);
                arrive = util.findRegExp( segment.find('.tdArrive').find('div:has(b)').text(), /\,\s*(.+)/);
                if (!arrive)
                    arrive = util.findRegExp( segment.find('.tdArrive').find('div:has(strong)').text(), /\,\s*(.+)/);
                browserAPI.log("arrive: " + arrive);
                var arriveDay = util.findRegExp(arrTime, /\.m\.\s*\+?([\d]+)\s*Day/i);
                DT = arrive + ' ' + arrTime;
                DT = util.trim(DT.replace(/\.m\.([^<]+)/, 'm'));
//                browserAPI.log("DT: " + DT);
                DT = util.trim(DT.replace(/\./g, ''));
                browserAPI.log("DT: " + DT);
                DT = new Date(DT + ' UTC');
                if (arriveDay) {
                    browserAPI.log("day: +" + arriveDay);
                //    DT.setDate(DT.getDate() + parseFloat(arriveDay));
                //    browserAPI.log("Date: " + DT);
                }
                unixtime = DT / 1000;
                if (!isNaN(unixtime)) {
                    browserAPI.log("ArrDate: " + arrive + ' ' + arrTime + " Unixtime: " + unixtime);
                    segments.ArrDate = unixtime;
                }else
                    browserAPI.log(">>> Invalid ArrDate");
                arrCode = segment.find('.tdArrive').find('div:eq(3)').text();
                // ArrName
                segments.ArrName = util.findRegExp(arrCode, /([^\(]+)/i);
                browserAPI.log("ArrName: " + segments.ArrName);
                // ArrCode
                segments.ArrCode = util.findRegExp(arrCode, /\(([^\)\-]+)/i);
                browserAPI.log("ArrCode: " + segments.ArrCode);
                // Duration
                duration = util.findRegExp( segment.find('.tdTrvlTime').find('span:contains("Flight Time"):eq(0)').text(), /:\s*(.+)Travel Time/i);
                browserAPI.log("duration: " + duration);
                if (!duration)
                    duration = segment.find('.tdTrvlTime').find('span:not(:contains("Travel Time")):eq(0)').text();
                if (!duration)
                    duration = segment.find('.tdTrvlTime').find('span:contains("Travel Time"):eq(0)').text();
                if (duration)
                    duration = duration.replace(/.+:/i, '');
                browserAPI.log("duration: " + duration);
                segments.Duration = duration;

                // Seats
                var seats = '';
                var seatNodes = $('th:contains("Seat Assignments")').next('td');
                if (seatNodes.length == 0)
                    seatNodes = $('span:contains("Seat Assignments")').next('label');
                seatNodes.each(function(){
                    var reg = new RegExp(segments.DepCode + '\\s*\\-\\s*' + segments.ArrCode + ':\\s*([^<]+)', 'i');
                    var st = util.findRegExp( $(this).html(), reg);
                    //browserAPI.log("st: " + st);
                    if (st != null && st != '')
                        seats = seats + st + ', ';
                });
                segments.Seats = seats.replace(/, $/, '');
                browserAPI.log("Seats: " + segments.Seats);

                browserAPI.log("<<< Segment " + k);
                params.data.TripSegments.push(segments);
                k++;
            });
            browserAPI.log("<<< Trip " + i);
            i++;
        });

        // Car Itinerary
        if ($('span:contains("Car Company Reservation Number:")').text()) {
            browserAPI.log("Car Itinerary");

            // Duration
            delete params.data.Duration;
            params.data.Duration = $('#ctl00_ContentInfo_ViewRes_ConfirmCarDetails_lblDuration').text();
            browserAPI.log("Duration: " + params.data.Duration);

            var link = $('a[id $= linkPrint]').attr('href');
            link = link.replace('main.aspx', 'https://www.united.com/web/en-US/apps/reservation/main.aspx');
            provider.setNextStep('parseCarItinerary', function () {
                document.location.href = link;
            });
        }// if ($('span:contains("Car Company Reservation Number:")').text())
        else {
            result.TripSegments = params.data.TripSegments;
            params.data.Itineraries.push(result);
            params.data.TripSegments = [];

            if (params.data.links.length == 0) {
                params.account.properties.Itineraries = params.data.Itineraries;
                params.account.properties.Rentals = params.data.Rentals;
                provider.saveProperties(params.account.properties);
                //console.log(params.account.properties);//todo
                provider.complete();
            }// if (params.data.links.length == 0)
            else {
                if(provider.isMobile){
                    var nextLink = params.data.links.shift();
                }
                provider.setNextStep('parseItinerary', function () {
                    if(provider.isMobile){
                        document.location.href = nextLink;
                    }else{
                        document.location.href = params.data.links.shift();
                    }
                });
            }
        }
    },

    parseCarItinerary: function(){
        browserAPI.log("parseCarItinerary");
        var result = {};
        var unixtime = null;

        // Number
        result.Number = $('#ctl00_ContentInfo_displayInfo_divConfirmation').text();
        browserAPI.log("Number: " + result.Number);
        // RentalCompany
        result.RentalCompany = $('#ctl00_ContentInfo_displayInfo_divCarCompany').text();
        browserAPI.log("RentalCompany: " + result.RentalCompany);
        // PickupPhone
        result.PickupPhone = $('#ctl00_ContentInfo_displayInfo_divPhone').text();
        if (result.PickupPhone.indexOf('>') + 1)
            result.PickupPhone = util.findRegExp( result.PickupPhone, />([^<]+)/i);
        browserAPI.log("PickupPhone: " + result.PickupPhone);
        // CarType
        result.CarType = $('#ctl00_ContentInfo_displayInfo_divCarType').text();
        browserAPI.log("CarType: " + result.CarType);
        // PickupLocation
        var pickupLocation = $('#ctl00_ContentInfo_displayInfo_divCarPickUp').text();
        result.PickupLocation = util.findRegExp( pickupLocation, /([^\)<]+\))/i);
        browserAPI.log("PickupLocation: " + result.PickupLocation);
        // PickupDatetime
        var pickupDatetime = util.findRegExp( pickupLocation, /\)([^<]+)/i);
        browserAPI.log("PickupDatetime: " + pickupDatetime);
        pickupDatetime = util.trim(pickupDatetime.replace(/\./g, ''));
        pickupDatetime = new Date(pickupDatetime + ' UTC');
        unixtime = pickupDatetime / 1000;
        if (!isNaN(unixtime)) {
            browserAPI.log("PickupDatetime: " + pickupDatetime + " Unixtime: " + unixtime);
            result.PickupDatetime = unixtime;
        }else
            browserAPI.log(">>> Invalid PickupDatetime");
        // DropoffLocation
        var dropoffLocation = $('#ctl00_ContentInfo_displayInfo_divCarReturn').text();
        result.DropoffLocation = util.findRegExp( dropoffLocation, /([^\)<]+\))/i);
        browserAPI.log("DropoffLocation: " + result.DropoffLocation);
        // DropoffDatetime
        var dropoffDatetime = util.findRegExp( dropoffLocation, /\)([^<]+)/i);
        browserAPI.log("DropoffDatetime: " + dropoffDatetime);
        dropoffDatetime = util.trim(dropoffDatetime.replace(/\./g, ''));
        dropoffDatetime = new Date(dropoffDatetime + ' UTC');
        unixtime = dropoffDatetime / 1000;
        if (!isNaN(unixtime)) {
            browserAPI.log("DropoffDatetime: " + dropoffDatetime + " Unixtime: " + unixtime);
            result.DropoffDatetime = unixtime;
        }else
            browserAPI.log(">>> Invalid DropoffDatetime");
        // TotalCharge
        result.TotalCharge = util.findRegExp( $('#ctl00_ContentInfo_displayInfo_divCarRate2').text() , /([\$\.\,\d]+)/i);
        browserAPI.log("TotalCharge: " + result.TotalCharge);
        // RentalRate
        result.RentalRate = $('#ctl00_ContentInfo_displayInfo_divCarRate').text();
        browserAPI.log("RentalRate: " + result.RentalRate);
        // RenterName
        result.RenterName = $('#ctl00_ContentInfo_displayInfo_divDriver').text();
        browserAPI.log("RenterName: " + result.RenterName);
        // Duration
        result.Duration = params.data.Duration;
        browserAPI.log("Duration: " + result.Duration);

        params.data.Rentals.push(result);
        delete params.data.Duration;

        if (params.data.links.length == 0) {
            params.account.properties.Itineraries = params.data.Itineraries;
            params.account.properties.Rentals = params.data.Rentals;
            provider.saveProperties(params.account.properties);
            provider.complete();
        }
        else{
            if(provider.isMobile){
                var nextLink = params.data.links.shift();
            }
            provider.setNextStep('parseItinerary', function () {
                if(provider.isMobile){
                    document.location.href = nextLink;
                }else{
                    document.location.href = params.data.links.shift();
                }
            });
        }
    },

    unionArray: function ( elem, separator, unique ){
        // $.map not working in IE 8, so iterating through items
        var result = [];
        for(var i = 0; i < elem.length; i++){
            var text = util.trim(elem.eq(i).text());
            if(text != "" && (!unique || result.indexOf(text) == -1))
                result.push(text);
        }
        return result.join( separator );
    }
};