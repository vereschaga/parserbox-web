var plugin = {
    // keepTabOpen: true,//todo
    // hideOnStart: true,
    clearCache: true,
    // mobileUserAgent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.2 Safari/605.1.15",
    hosts: {
        'bestwestern.com': true,
        "book.bestwestern.com": true,
        "www.bestwestern.com": true,
        'www.bestwestern.co.uk': true,
    },
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.bestwestern.com/en_US/rewards/member-dashboard.html';
    },

    getFocusTab: function(account, params){
        return true;
    },

    // for Firefox, refs #19191, #note-24
    getXMLHttp: function () {
        if (typeof content !== 'undefined' && content && content.XMLHttpRequest) {
            return new content.XMLHttpRequest();
        }
        return new XMLHttpRequest();
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log('Current URL -> ' + document.location.href);
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        // IE not working properly
        if (!!navigator.userAgent.match(/Trident\/\d\./)) {
            provider.eval('jQuery.noConflict()');
        }
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.logBody("lastPage");
                // debug
                if (
                    $('form[id = "guest-login-form"]:visible').length > 0
                    && $('#points-available').length == 0
                ) {
                    provider.setNextStep('login', function () {
                        document.location.href = plugin.getStartingUrl(params);
                    });
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 20)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[id = "guest-login-form"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (
            $('#rewards-card-number:contains("Account "):visible').length > 0
            && $('div#logged-in-user-name:visible, #nav-logged-in-user-name:visible').length > 0
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        // Session Timeout
        var sessionTimeout = $('h4:contains("Session Timeout"):visible').parent('div').children('button');
        if (sessionTimeout.length > 0) {
            browserAPI.log("Session Timeout");
            sessionTimeout.get(0).click();
            return true;
        }
        // open login form
        const menuBtn = $('a.loginLink:visible');

        if (!plugin.openLoginForm() && menuBtn.length > 0) {
            browserAPI.log("open menu");
            menuBtn[0].click();

            plugin.openLoginForm();
        }

        return null;
    },

    openLoginForm: function () {
        const loginBtn = $('#account-popover-log-in-link:visible, #btn-log-in:visible');

        if (loginBtn.length > 0) {
            browserAPI.log("open login form");
            loginBtn[0].click();

            return true;
        }

        return false;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp( $('#rewards-card-number:contains("Account "):visible').text(), /Account\s*([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $("button.logoutButton").get(0).click();

            setTimeout((params) => {
                browserAPI.log('Current URL -> ' + document.location.href);
                if (document.location.href === 'https://www.bestwestern.com/en_US.html') {
                    browserAPI.log("force call loadLoginForm");
                    plugin.loadLoginForm(params);
                }
            }, 3000)
        });
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log('Current URL -> ' + document.location.href);

		if (
            typeof(params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
			provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = "https://www.bestwestern.com/content/best-western/en_US.html";
            });
			return;
		}

        var form = $('form[id="guest-login-form"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#guest-user-id-1').val(params.account.login);
            form.find('input#guest-password-1').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function(){
                    provider.setTimeout(function() {
                        var captcha = $('form[id="guest-login-form"]').find('div#recaptcha:not([data-type = "invisible"])');
                        if (captcha && captcha.length > 0) {
                            provider.reCaptchaMessage();
                            $('#awFader').remove();
                            provider.setTimeout(function () {
                                waiting();
                            }, 0);
                        }// if (captcha && captcha.length > 0)
                        else {
                            browserAPI.log("captcha is not found");
                            form.find('#login-button-modal-recaptcha')[0].click();
                            provider.setTimeout(function () {
                                waiting();
                            }, 0);
                        }
                    }, 2000)
                });

            function waiting() {
                browserAPI.log("waiting...");
                var counter = 0;
                var login = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    let error = $('div.alert > span.defaultMessage:visible');
                    if (error.length > 0 && error.text().trim() !== '') {
                        clearInterval(login);
                        if (
                            error.text().indexOf('The captcha is required and can') === -1
                            && error.text().indexOf('Please re-enter captcha') === -1
                        ) {
                            provider.setError(error.text(), true);
                        }
                        else
                            provider.setError([error.text(), util.errorCodes.providerError], true);
                    }// if (error.length > 0 && error.text().trim() != '')
                    let error2 = form.find('input#guest-password-1.validationError');
                    if (error2.length > 0 && error2.attr('placeholder').trim() !== '') {
                        clearInterval(login);
                        provider.setError(error2.attr('placeholder'), true);
                    }// if (error2.length > 0 && error2.attr('placeholder').trim() != '')
                    // refs #14909
                    let success = $('#logged-in-user-name, #nav-logged-in-user-name:visible');
                    if (
                        $('p:contains("Account Number"):visible, p#rewards-card-number:visible').length > 0
                        || (success.length === 1 && util.filter(success.text()) !== '')
                    ) {
                        clearInterval(login);
                        plugin.checkLoginErrors(params);
                    }// if ($('p:contains("Account Number"):visible').length > 0)
                    if (counter > 120) {
                        clearInterval(login);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }
                    counter++;
                }, 500);
            }
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert > span.defaultMessage:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            if (
                errors.text().indexOf('The captcha is required and can') === -1
                && errors.text().indexOf('Please re-enter captcha') === -1
            ) {
                provider.setError(errors.text(), true);
            }
            else
                provider.setError([errors.text(), util.errorCodes.providerError], true);
        }// if (error.length > 0 && error.text().trim() != '')
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        browserAPI.log('Current URL -> ' + document.location.href);
		if (typeof(params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('itLoginComplete', function () {
                document.location.href = "https://book.bestwestern.com/bestwestern/reservationView.do?opt=view&confirmationNumber=" + params.account.properties.confirmationNumber + "&isCRData=false";
            });
			return;
		}
        // Please scroll to the bottom of the page to save any profile changes. -
        // this caption is always visible
        // if ($('span:contains("Please scroll to the bottom of the page to save any profile changes."):visible').length) {
        //     provider.setError(["Best Western Rewards website needs you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
        //     return;
        // }

        var dashboardURL = 'https://www.bestwestern.com/en_US/rewards/member-dashboard.html';
		if (document.location.href !== dashboardURL)
            provider.setNextStep('loadAccount', function () {
                document.location.href = dashboardURL;
            });
		else
            plugin.loadAccount(params);
	},

	getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
		var properties = params.account.properties.confFields;
		var form = $('form[id = "check-res-by-confirmation-form"]');
		if (form.length > 0) {
			form.find('input[name = "check-res-first-name"]').val(properties.FirstName);
			form.find('input[name = "check-res-last-name"]').val(properties.LastName);
			form.find('input[name = "check-res-confirmation"]').val(properties.ConfNo);
			provider.setNextStep('itLoginComplete', function(){
                form.find('button.confirmationCheckButton')[0].click();
            });
		}
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
	},

	itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
		provider.complete();
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        browserAPI.log('Current URL: ' + document.location.href);
        if (params.autologin) {
            plugin.itLoginComplete(params);
            return;
        }
        var counter = 0;
        var loadAccount = setInterval(function () {
            browserAPI.log("[loadAccount]: waiting... " + counter);
            // if the page completely loaded
            var number = $('#rewards-card-number:contains("Account "):visible');
            if (number.length > 0 && number.text() != '') {
                clearInterval(loadAccount);
                plugin.parse(params);
            }// if (number.length > 0 && number.text() != '')
            if (counter > 10) {
                clearInterval(loadAccount);
                plugin.parse(params);
            }// if (counter > 10)
            counter++;
        }, 500);
    },

    parse: function(params) {
        browserAPI.log("parse");

        if (provider.isMobile) {
            provider.command('hide', function () {
            });
        }

        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);

        var data = {};
        // Balance - Points Balance
        var balance = $('#points-available');
        if (balance.length > 0) {
            data.Balance = util.trim(balance.text());
            browserAPI.log("Balance: " + data.Balance );
        }
        else
            browserAPI.log("Balance is not found");
        // Rewards Number
        var number = $('#rewards-card-number:contains("Account "):visible');
        if (number.length > 0) {
            data.Number = util.findRegExp( number.text(), /Account\s*([\d]+)/i);
            browserAPI.log("Rewards Number: " + data.Number);
        }
        else
            browserAPI.log("Rewards Number not found");
        // Status
        var status = $('#rewards-card-tier');
        if (status.length > 0) {
            data.Level = util.findRegExp( status.text(), /(.+)\s*Member/i);
            browserAPI.log("Level: " + data.Level);
        }
        else
            browserAPI.log("Level not found");
        // Nights to Next Level
        var nights = $('#progress-nights');
        if (nights.length > 0) {
            data.Nights = util.trim(nights.attr('data-needed'));
            browserAPI.log("Nights: " + data.Nights);
        }
        else
            browserAPI.log("Nights not found");
        // Stays to Next Level
        var stays = $('#progress-stays');
        if (stays.length > 0) {
            data.Stays = util.trim(stays.attr('data-needed'));
            browserAPI.log("Stays: " + data.Stays);
        }
        else
            browserAPI.log("Stays not found");
        // Points to Next Level
        var points = $('#progress-points');
        if (points.length > 0) {
            data.PointsToNextLevel = util.trim(points.attr('data-needed'));
            browserAPI.log("Points to Next Level: " + data.PointsToNextLevel);
        }
        else
            browserAPI.log("Points to Next Level not found");
        // refs #8349
        browserAPI.log("Region " + params.account.login2);
        if ($.inArray(params.account.login2, ["America", "Mexico", "Asia"]) !== -1) {
            browserAPI.log("expiration date set to never");
            data.AccountExpirationDate = 'false';
        }
        else
            browserAPI.log("expiration date set to unknown");

        params.data.properties = data;
        //console.log(data);
        provider.saveTemp(params.data);

        provider.setNextStep('parseName', function () {
            document.location.href = "https://www.bestwestern.com/content/best-western/en_US/rewards/profile-and-preferences.html";

            setTimeout((params) => {
                browserAPI.log('Current URL -> ' + document.location.href);

                if (document.location.href === "https://www.bestwestern.com/content/best-western/en_US/rewards/profile-and-preferences.html") {
                    browserAPI.log("force call parseName");
                    plugin.parseName(params);
                }
            }, 3000)
        });
    },

    parseName: function (params) {
        browserAPI.log("parseName");
        // Name
        var name = $('span#full-name');
        if (name.length > 0) {
            name = util.beautifulName( name.text() );
            browserAPI.log("Name: " + name );
            params.data.properties.Name = name;
        }
        else
            browserAPI.log("Name not found");

        params.account.properties = params.data.properties;
        //console.log(params.account);
        provider.saveProperties(params.account.properties);
        // Parsing Itineraries
        if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            provider.setNextStep('parseItineraries', function () {
                document.location.href = 'https://www.bestwestern.com/content/best-western/en_US/reservations/reservation-index.html';
            });
        }
        else
            plugin.itLoginComplete(params);
    },

    parseItineraries: function (params) {
        browserAPI.log("parseItineraries");
        browserAPI.log('currentUrl: ' + document.location.href);
        provider.updateAccountMessage();
        // parse Itineraries
        params.data.Reservations = [];
        var counter = 0;
        var parseItineraries = setInterval(function () {
            browserAPI.log("[parseItineraries]: waiting... " + counter);
            // No itineraries
            if ($('p:contains("No upcoming reservations."):visible').length > 0) {
                clearInterval(parseItineraries);
                    params.account.properties.Reservations = [{ NoItineraries: true }];
                    // console.log(params.account.properties);
                    provider.saveProperties(params.account.properties);
                    plugin.itLoginComplete(params);
                    return;
            }// if (number.length > 0 && number.text() != '')
            var itineraries = $('div#upcoming-container div.reservationCard:visible');
            if (itineraries.length > 0 || counter > 40) {
                clearInterval(parseItineraries);

                browserAPI.log('Total ' + itineraries.length + ' reservations were found');
                if (itineraries.length > 0) {
                    // save data
                    provider.saveTemp(params.data);

                    for (var i = 0; i < itineraries.length; i++) {
                        browserAPI.log(">>> Reservation " + i);
                        plugin.parseItinerary(params, itineraries.eq(i));
                        browserAPI.log("<<< Reservation " + i);
                    }

                    browserAPI.log(">>> success");
                    params.account.properties.Reservations = params.data.Reservations;
                    // console.log(params.account.properties);//todo
                    provider.saveProperties(params.account.properties);
                    plugin.itLoginComplete(params);
                }// if (itineraries.length > 0)
                else
                    plugin.itLoginComplete(params);
            }// if (itineraries.length > 0 || counter > 40)
            counter++;
        }, 500);
    },

    parseItinerary: function (params, details) {
        browserAPI.log("parseItinerary");
        provider.updateAccountMessage();
        var data = {};
        // ConfirmationNumber
        var confirmationNumber = details.find('p.confirmationNumber');
        if (confirmationNumber.length > 0) {
            data.ConfirmationNumber = util.filter(confirmationNumber.text());
            browserAPI.log('Conf #: ' + data.ConfirmationNumber);
            // get reservation info
            var detailsLink = 'https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=RESERVATION_BOOKING&confirmationnumber=' + data.ConfirmationNumber + '&langCode=en_US&isArchived=false&clientType=WEB';
            browserAPI.log("detailsLink -> " + detailsLink);
            $.ajax({
                url: detailsLink,
                async: false,
                xhr: plugin.getXMLHttp,
                success: function (response) {
                    browserAPI.log("parse reservation info");
                    response = $(response);
                    var reservationInfo = JSON.stringify(response);
                    // console.log("---------------- reservationInfo ----------------");
                    // console.log(reservationInfo);
                    // console.log("---------------- reservationInfo ----------------");

                    data.Guests = util.findRegExp(reservationInfo, /"numAdult":(\d+)/);
                    data.Kids = util.findRegExp(reservationInfo, /"numChild":(\d+)/);
                    data.Total = util.findRegExp(reservationInfo, /"roomPrice":([\d\.\s]+)/);
                    data.Currency = util.findRegExp(reservationInfo, /"roomCurrency":"([^\"]+)"/);
                    data.RoomTypeDescription = util.findRegExp(reservationInfo, /"roomBedInfo":"[^\"]+","description":"([^\"]+)"/);

                    var resortLink = 'https://www.bestwestern.com/bin/bestwestern/proxy?gwServiceURL=RESORT_SUMMARY&hotelid=' + util.findRegExp(reservationInfo, /"resort":"([^\"]+)"/);
                    browserAPI.log("resortLink -> " + resortLink);
                    $.ajax({
                        url: resortLink,
                        async: false,
                        xhr: plugin.getXMLHttp,
                        success: function (resortInfo) {
                            resortInfo = $(resortInfo);
                            // console.log("---------------- resortInfo[0] ----------------");
                            // console.log(resortInfo[0]);
                            // console.log("---------------- resortInfo[0] ----------------");

                            data.HotelName = typeof (resortInfo[0].name) != 'undefined' ? resortInfo[0].name : null;
                            data.Phone = typeof (resortInfo[0].phoneNumber) != 'undefined' ? resortInfo[0].phoneNumber : null;
                            data.Fax = (typeof (resortInfo[0].faxNumber) != 'undefined' && resortInfo[0].faxNumber != '.') ? resortInfo[0].faxNumber : null;
                            if (data.Fax == '.' || data.Fax.length < 5) {
                                browserAPI.log("remove bad fa number -> " + data.Fax);
                                data.Fax = null;
                            }

                            if(typeof resortInfo[0].address1 !== 'undefined' && typeof resortInfo[0].city !== 'undefined')
                                data.Address = resortInfo[0].address1 + ', ' + resortInfo[0].city + ', ' + resortInfo[0].state + ', ' + resortInfo[0].country;

                            // =============================
                            // CheckInDate and CheckOutDate
                            // ============================
                            try {
                                var checkInDate = util.findRegExp(reservationInfo, /"checkinDate":"(\d{4}-\d+-\d+)"/) + ' '
                                    + resortInfo[0].checkInNoticeTime.replace(/noon/i, 'pm').replace(new RegExp('\\.', 'g'), '');

                                var checkOutDate = util.findRegExp(reservationInfo, /"checkoutDate":"(\d{4}-\d+-\d+)"/) + ' '
                                    + resortInfo[0].checkOutNoticeTime.replace(/noon/i, 'pm').replace(new RegExp('\\.', 'g'), '');

                                browserAPI.log('checkInDate: ' + checkInDate + ', checkOutDate: ' + checkOutDate);
                                var checkIn = plugin.dateFormat(checkInDate);
                                var checkOut = plugin.dateFormat(checkOutDate);
                                var unixtimeIn = checkIn.getTime() / 1000;
                                var unixtimeOut = checkOut.getTime() / 1000;
                                if (!isNaN(unixtimeIn) && !isNaN(unixtimeOut)) {
                                    browserAPI.log('CheckInDate: ' + checkIn + ' Unixtime: ' + unixtimeIn);
                                    browserAPI.log('CheckOutDate: ' + checkOut + ' Unixtime: ' + unixtimeOut);
                                    data.CheckInDate = unixtimeIn;
                                    data.CheckOutDate = unixtimeOut;
                                } else
                                    throw new Error('Invalid CheckInDate or CheckOutDate');
                            } catch (e) {
                                browserAPI.log(e);
                            }
                        }
                    });

                }
            });
        }
        else
            browserAPI.log('Conf # not found');

        //fconsole.log(data);//todo
        params.data.Reservations.push(data);
        provider.saveTemp(params.data);
    },

    dateFormat: function (dateTime) {
        dateTime = plugin.dateConvert24Hour(dateTime);
        //dateTime = '2017-09-16 14:00';
        var time = dateTime.match(/(\d+)-(\d+)-(\d+) (\d+):(\d+)/i);
        var year = time[1],
            month = time[2],
            day = time[3],
            hour = time[4],
            minute = time[5];
        var dateObject = new Date(Date.UTC(year, month - 1, day, hour, minute, 0, 0));
        browserAPI.log('>>> dateFormat: ' + dateObject);
        return dateObject;
    },

    dateConvert24Hour: function (dateTime) {
        var time = dateTime.match(/(\d+-\d+-\d+) (\d+):(\d+) ([ap]m)/i);
        var hours = Number(time[2]);
        var minutes = Number(time[3]);
        var ampm = time[4].toLowerCase();
        if (ampm == 'pm' && hours < 12) hours = hours + 12;
        if (ampm == 'am' && hours == 12) hours = hours - 12;
        var sHours = hours.toString();
        var sMinutes = minutes.toString();
        if (hours < 10) sHours = '0' + sHours;
        if (minutes < 10) sMinutes = '0' + sMinutes;
        return time[1] + ' ' + sHours + ':' + sMinutes;
    },

    fullYearInDate: function (date, separator) {
        if (!separator)
            separator = '/';
        var LogSplitter = "-----------------------------";
        browserAPI.log(LogSplitter);
        browserAPI.log("Transfer Date In Full Format");
        browserAPI.log("Date: " + date);
        browserAPI.log("Separator: " + separator);

        if (date != null) {
            var new_date = date.split(separator);
            if (typeof(new_date[1]) != 'undefined' && new_date[2].length == 2)
                date = new_date[0] + '/' + new_date[1] + '/20' + new_date[2];
            else {
                browserAPI.log("Please set the correct separator!");
                browserAPI.log(LogSplitter);
                return null;
            }
            browserAPI.log("Date In New Format: " + date);
            browserAPI.log(LogSplitter);
            return date;
        }
        else {
            browserAPI.log("Date format is not valid!");
            browserAPI.log(LogSplitter);
            return null;
        }
    }
};