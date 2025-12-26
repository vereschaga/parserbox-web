var plugin = {
    hideOnStart: true,
    // keepTabOpen: true, // todo

    hosts: {
        'www.elal-matmid.com': true,
        'www.elal.co.il': true,
        'app.elal.co.il': true,
        'booking.elal.co.il': true,
        'fly.elal.co.il': true,
        'www.elal.com': true,
        'booking.elal.com': true,
        'book.elal.com': true,
        'matmid.elal.com': true,
    },

    itineraryLink: 'https://www.elal-matmid.com/en/MyReservations/Pages/MyFlights.aspx',

    getStartingUrl: function (params) {
        return "https://www.elal.com/eng/frequentflyer/myffp/myaccount";
    },

    getFocusTab: function (account, params) {
        return true;
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        // fixed redirect
        if (document.location.href.indexOf('://www.elal-matmid.com/he/Login/Pages/Login.aspx') > -1) {
            plugin.loadLoginForm(params);
            return;
        }
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
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
                // maintenance
                var error = $('h1:contains("We are doing maintenance work on the website..."):visible');
                if (error.length == 0)
                    error = $('p:contains("EL AL\'s website is being upgraded,"):visible');
                if (error.length == 0)
                    error = $('h2:contains("Current session has been terminated."):visible');
                if (error.length > 0)
                    provider.setError([error.text(), util.errorCodes.providerError], true);
                else
                    provider.setError(util.errorMessages.unknownLoginState, true);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= LogOff], button:contains("Log Off"), button:contains("log off"), button:contains("Log out")').length > 0
            // mobile fix
            || (provider.isMobile && $('span.personal-name-id:visible').length > 0)) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('input[value = "Sign In"]:visible').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        let number = util.findRegExp($('.inner-container span:contains("Member Number")').text(), /:\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return (((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MemberNo) != 'undefined')
            && (account.properties.MemberNo !== '')
            && number
            && (number.indexOf(account.properties.MemberNo) > -1))
                || ((typeof(account.login) != 'undefined')
            && (number && number.indexOf(account.login) > -1)));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function() {
            document.location.href = 'https://www.elal-matmid.com/en/Login/Pages/LogOff.aspx';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        provider.logBody("loginPage");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://booking.elal.com/manage/login?lang=en&LANG=EN";
            });
            return;
        }
        var form = $('form#aspnetForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            util.setInputValue(form.find('input[id *= "MembertxtID"]'), params.account.login);
            util.setInputValue(form.find('input[id *= "PasswordtxtID"]'), params.account.password);
            provider.setNextStep('checkLoginErrors', function() {
                form.find('input[value = "Sign In"]').get(0).click();

                setTimeout(function () {
                    browserAPI.log("search error on login page...");
                    var errors = $('span.error[id *= "txtID-error"]:visible:eq(0)');
                    if (errors.length > 0)
                        provider.setError(errors.text(), true);
                }, 10000)
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        provider.logBody("checkLoginErrorsPage");
        browserAPI.log('Current URL: ' + document.location.href);
        var errors = $('#ctl00_ContentPlaceHolder1_FrqFlyerSignIn1_errorMessage');
        if (errors.length == 0)
            errors = $('label:contains("Member Number Is Invalid"):visible');
        if (errors.length == 0)
            errors = $('span:contains("Member And/Or Password Incorrect"):visible, span:contains("The data you entered do not match the details in our database")');
        if (errors.length == 0)
            errors = $('label:contains("User or password are invalid!"):visible');
        if (errors.length == 0) {
            errors = $('strong:contains("Dear Customer, your account has been blocked"):visible, h2:contains("Your account has been blocked"):visible');
            if (errors.length > 0) {
                provider.setError([errors.text(), util.errorCodes.lockout], true);
                return;
            } // if (errors.length > 0)
        }// if (errors.length == 0)
        if (errors.length == 0) {
            errors = $('label:contains("Error occurred, please try again later"):visible, span:contains("Error occurred, please try again later."):visible');
            if (errors.length > 0) {
                provider.setError([errors.text(), util.errorCodes.providerError], true);
                return;
            }// if (errors.length > 0)
        }// if (errors.length == 0)
        if (errors.length > 0)
            provider.setError(errors.text(), true);
        else {
            // Need to change a password
            if (((-1 < document.location.href.indexOf('ChangeTempPassword.aspx')) && $('h2:contains("Change Password")').length)
                || $('b:contains("In order to protect your account privacy, please replace your login password"):visible').length) {
                provider.setError(['EL AL Israel Airlines website is asking you to change your password, until you do so we would not be able to retrieve your account information.', util.errorCodes.providerError], true);
                return;
            }
            plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function(){
                document.location.href = 'https://www.elal-matmid.com/en/MyReservations/Pages/MyFlights.aspx';
            });
            return;
        }

        plugin.loadAccount(params);
    },

    toConfNoItineraryPage: function (params) {
        browserAPI.log("toConfNoItineraryPage");
        provider.setNextStep('getConfNoItinerary', function () {
            setTimeout(function () {
                plugin.getConfNoItinerary(params);
            }, 10000);
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        let properties = params.account.properties.confFields;
        let form = $('.container form');
        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return
        }
        // angularjs 10
        provider.eval(
            "function triggerInput(enteredName, enteredValue) {\n" +
            "      const input = document.getElementById(enteredName);\n" +
            "      var createEvent = function(name) {\n" +
            "            var event = document.createEvent('Event');\n" +
            "            event.initEvent(name, true, true);\n" +
            "            return event;\n" +
            "      }\n" +
            "      input.dispatchEvent(createEvent('focus'));\n" +
            "      input.value = enteredValue;\n" +
            "      input.dispatchEvent(createEvent('change'));\n" +
            "      input.dispatchEvent(createEvent('input'));\n" +
            "      input.dispatchEvent(createEvent('blur'));\n" +
            "}\n" +
            "triggerInput('form-bookingCode', '" + properties.ConfNo + "');\n" +
            "triggerInput('form-lastName', '" + properties.LastName + "');"
        );
        provider.setNextStep('itLoginComplete', function() {
            form.find('button.ui-button').click();
            setTimeout(function () {
                let errors = $('span.ui-alert__message:visible, small.ui-form-group__error:visible');
                if (errors.length > 0) {
                    provider.setError(errors.text(), true);
                }
            }, 7000)
        });
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        plugin.itLoginComplete();
        /*setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber.trim();
            confNo = util.findRegExp(confNo, /(\w+)/);
            var link = $('a[href *= "REC_LOC=' + confNo + '"]');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function() {
                    document.location.href = link[0].href;
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);*/
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        provider.logBody("loadAccountPage");

        if (params.autologin) {
            provider.complete();
            return;
        }

        if (document.location.href !== 'https://www.elal.com/eng/frequentflyer/myffp/myaccount') {
            provider.setNextStep('parse', function () {
                document.location.href = 'https://www.elal.com/eng/frequentflyer/myffp/myaccount';
            });
        }
        else
            plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parsePage");
        let data = {};

        // Status
        const status = $('em.status');
        if (status.length > 0) {
            data.CurrentClubStatus = status.text();
            browserAPI.log("Status: " + data.CurrentClubStatus);
        }
        else
            browserAPI.log(">>> Status is not found");
        // Status valid until
        const statusExp = $('span:contains("Status valid until") + span');
        if (statusExp.length > 0) {
            data.StatusExpiration = statusExp.text();
            browserAPI.log("Status valid until: " + data.StatusExpiration);
        }
        else
            browserAPI.log(">>> Status valid until is not found");
        // Points In Your Account
        const balance = $('em.balance');
        if (balance.length > 0) {
            data.Balance = util.trim(balance.text());
            browserAPI.log("Balance: " + data.Balance);
        } else {
            browserAPI.log(">>> Balance not found");
        }
        // Name
        const name = $('.inner-container span.name');
        if (name.length > 0) {
            data.Name = util.beautifulName(name.text());
            browserAPI.log("Name: " + data.Name);
        } else
            browserAPI.log(">>> Name not found");
        // Member Number
        const number = $('.inner-container span:contains("Member Number")');
        if (number.length > 0) {
            data.MemberNo = util.findRegExp(number.text(), /:\s*(\d+)/i);
            browserAPI.log("Member Number: " + data.MemberNo);
        }
        else {
            browserAPI.log(">>> Member Number is not found");
        }
        // Diamonds for next Tier
        const diamonds = $('p:contains("To upgrade to"):eq(0)').prev('div').find('div > span > b');
        if (diamonds.length > 0) {
            data.DiamondsForNextTier = diamonds.text();
            browserAPI.log("Diamonds for next Tier: " + data.DiamondsForNextTier);
        } else
            browserAPI.log(">>> Diamonds for next Tier not found");
        // Flight segments for next Tier
        const flights = $('div > p:contains("To upgrade to"):eq(1)').parent('div').find('div:eq(0) div > span > b');
        if (flights.length > 0) {
            data.FlightForNextTier = flights.text();
            browserAPI.log("Flight segments for next Tier: " + data.FlightForNextTier);
        } else
            browserAPI.log(">>> Flight segments for next Tier not found");
        // Diamonds to maintain Tier
        const diamondsToMaintainTier = $('p:contains("To maintain"):eq(0)').prev('div').find('div > span > b');
        if (diamondsToMaintainTier.length > 0) {
            data.DiamondsToMaintainTier = diamondsToMaintainTier.text();
            browserAPI.log("Diamonds to maintain Tier: " + data.DiamondsToMaintainTier);
        } else
            browserAPI.log(">>> Diamonds to maintain Tier not found");
        // Flight segments to maintain Tier
        const flightsToMaintainTier = $('div > p:contains("To maintain"):eq(1)').parent('div').find('div:eq(0) div > span > b');
        if (flightsToMaintainTier.length > 0) {
            data.FlightToMaintainTier = flightsToMaintainTier.text();
            browserAPI.log("Flight segments to maintain Tier: " + data.FlightToMaintainTier);
        } else
            browserAPI.log(">>> Flight segments to maintain Tier not found");

        // Expiration date  // refs #6806
        // var details = $('.pointsAmount_time a:contains("Details")');
        // if (details.length > 0) {
        //     details.get(0).click();
        //     var counter = 0;
        //     var expDatePopup = setInterval(function () {
        //         browserAPI.log("waiting exp date... " + counter);
        //         var expXpath = $('div.matmidPop_pointsTbody').find('tr:first');
        //         if (expXpath || counter > 15) {
        //             clearInterval(expDatePopup);
        //
        //             // Expiring balance
        //             var expiringBalance = expXpath.find('td:eq(2) > span:not(:contains("RemainPoints"))');
        //             if (expiringBalance.length > 0) {
        //                 data.ExpiringBalance = util.trim(expiringBalance.text());
        //                 browserAPI.log("Expiring balance: " + data.ExpiringBalance);
        //             } else
        //                 browserAPI.log(">>> Expiring balance not found");
        //             // Expiration date
        //             var exp = expXpath.find('td:eq(3) > span');
        //             if (exp.length > 0) {
        //                 exp = util.modifyDateFormat(exp.text(), '/');
        //                 browserAPI.log("Expiration Date: " + exp);
        //                 var date = new Date(exp + ' UTC');
        //                 if (!isNaN(date)) {
        //                     var unixtime = date / 1000;
        //                     if ( date != 'NaN' && !isNaN(unixtime) ) {
        //                         browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime) );
        //                         data.AccountExpirationDate = unixtime;
        //                     }
        //                 }// if (!isNaN(date))
        //                 else
        //                     browserAPI.log("Invalid Expiration Date");
        //             }// if (exp.length > 0)
        //             else
        //                 browserAPI.log("Exp date not found");
        //
        //             completeParseProfile();
        //         }// if (expXpath || counter > 15)
        //         counter++;
        //     }, 500);
        // }// if (details.length > 0)
        // else
            completeParseProfile();

        function completeParseProfile() {
            // console.log(params.data);
            params.data.properties = data;
            params.account.properties = params.data.properties;
            // Save properties
            provider.saveProperties(params.account.properties);

            // Parsing Itineraries
            if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
                provider.setNextStep('parseLastName', function () {
                    document.location.href = 'https://www.elal.com/ClubsN/MemberProfileRedirection?Lang=EN&_ga=2.45096108.987617546.1636553332-1266547677.1636553332';
                });
            } else
                provider.complete();
        }
    },

    parseLastName: function (params) {
        browserAPI.log("parseLastName");
        params.data.lastName = $('#PersonalDetails_LastName').val();

        // Parsing Itineraries
        if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            provider.setNextStep('parseItineraries', function () {
                document.location.href = plugin.itineraryLink;
            });
        } else
            provider.complete();
    },

    parseItineraries: function (params) {
        browserAPI.log("parseItineraries");
        var iframe = $('div#WebPartWPQ2 iframe');
        if (iframe.length > 0) {
            provider.setNextStep('parseItineraries2', function () {
                document.location.href = iframe.attr('src');
            });
        }
        else
            plugin.parseItinerariesAll(params);
    },

    parseItinerariesAll: function (params) {
        browserAPI.log("parseItineraries2");
        browserAPI.log('lastName: ' + params.data.lastName);
        var recordLocators = [];

        /* TODO
        var urlPattern = util.findRegExp(
            $('head script').text(),
            ///window\.open\(['"]([^)]+changeOrder\.do[^)]+)['"]\s*,\s*['"]_blank['"]\)/ig
            /window\.open\(['"]([^)]+checkmytrip_ELAL\/RetrievePNR\.action[^)]+)['"]\s*,\s*['"]_blank['"]\)/ig
        );
        if (urlPattern) {
            params.data.urlPattern = urlPattern;
            params.data.urlReplace = /"\s*\+\s*PNR\s*\+\s*"/;
        }
        var recordLocators = [];

        $('.voucherTable .blue').each(function (elem) {
            var recordLocatorElem = $(this).find('td').eq(0);
            if (1 === recordLocatorElem.length) {
                var recordLocator = util.trim(recordLocatorElem.text());
                if (recordLocator.length > 0 && recordLocators.indexOf(recordLocator) === -1) {
                    browserAPI.log("recordLocator -> " + recordLocator);
                    recordLocators.push(recordLocator);
                }
            }
        });
        */

        // new design
        $('span:contains("Reservation Number")').each(function () {
            var recordLocator = util.trim($(this).contents().eq(1).text());
            if (recordLocator.length > 0 && recordLocators.indexOf(recordLocator) === -1) {
                browserAPI.log("recordLocator -> " + recordLocator);
                recordLocators.push(recordLocator);
            }
        });
        if (recordLocators.length > 0) {
            params.data.recordLocators = recordLocators;
            params.data.linkIndex = 0;
            params.data.Itineraries = [];
            provider.saveTemp(params.data);
            provider.setNextStep('loadNextFormRetrieve', function () {
                document.location.href = 'https://booking.elal.com/manage/login?lang=en&LANG=EN';
            });
            return;
        }
        //
        if ($('div.ferror b:contains("No reservations were found"), div.noFlights_Details_header:contains("No future flights are listed in your account."):visible').length > 0) {
            params.account.properties.Itineraries = [{NoItineraries: true}];
            provider.saveProperties(params.account.properties);
            provider.complete();
        }
        else {
            params.account.properties.Itineraries = params.data.Itineraries;
            //console.log(params.account.properties);//todo
            provider.saveProperties(params.account.properties);
            provider.complete();
        }
    },

    loadNextFormRetrieve: function (params) {
        browserAPI.log("loadNextFormRetrieve");
        browserAPI.log('lastName: ' + params.data.lastName);
        browserAPI.log('recordLocators: ' + params.data.recordLocators);

        if (params.data.linkIndex < params.data.recordLocators.length) {
            let confNo = params.data.recordLocators[params.data.linkIndex++];
            browserAPI.log('confNo: ' + confNo);
            let lastName = params.data.lastName;
            let form = $('.main-container form');
            if (form.length === 0) {
                //provider.setError(util.errorMessages.itineraryFormNotFound);
                return
            }
            // angularjs 10
            provider.eval(
                "function triggerInput(enteredName, enteredValue) {\n" +
                "      const input = document.querySelectorAll('[placeholder^=\"'+enteredName+'\"]')[0];\n" +
                "      var createEvent = function(name) {\n" +
                "            var event = document.createEvent('Event');\n" +
                "            event.initEvent(name, true, true);\n" +
                "            return event;\n" +
                "      }\n" +
                "      input.dispatchEvent(createEvent('focus'));\n" +
                "      input.value = enteredValue;\n" +
                "      input.dispatchEvent(createEvent('change'));\n" +
                "      input.dispatchEvent(createEvent('input'));\n" +
                "      input.dispatchEvent(createEvent('blur'));\n" +
                "}\n" +
                "triggerInput('Booking code', '" + confNo + "');\n" +
                "triggerInput('Last name', '" + lastName + "');"
            );
            provider.setNextStep('waitItineraryPage', function() {
                form.find('button.ui-button').click();
                setTimeout(function () {
                    plugin.waitItineraryPage(params);
                }, 2000)
            });
        } else {
            browserAPI.log("Stop parse itineraries");
            console.log(params.data.Itineraries);
            // params.account.properties.Itineraries = params.data.Itineraries;
            // console.log(params.account.properties);//todo
            //provider.saveProperties(params.account.properties);
            params.account.properties.Itineraries = params.data.Itineraries;
            provider.saveProperties(params.account.properties);
            provider.complete();

        }
    },

    waitItineraryPage: function(params) {
        browserAPI.log('waitItineraryPage');
        var counter = 0;
        var waitItineraryPage = setInterval(function () {
            browserAPI.log("[waitItineraryPage]: waiting... " + counter);
            var flight = $('.container h3:contains("Your upcoming flights"):visible');
            if (flight.length > 0) {
                clearInterval(waitItineraryPage);
                plugin.parseItinerary(params);
            }
            if (counter > 35) {
                clearInterval(waitItineraryPage);
                plugin.parseItinerary(params);
            }
            counter++;
        }, 1000);
    },

    parseItinerary: function (params) {
        browserAPI.log("parseItinerary");
        // var offering = $('#pg_offering').text();
        // let data = eval(offering + ';pg_offering;');
        // console.log(data);

        function getQueryVariable(variable) {
            var query = window.location.search.substring(1);
            var vars = query.split('&');
            for (var i = 0; i < vars.length; i++) {
                var pair = vars[i].split('=');
                if (decodeURIComponent(pair[0]) == variable) {
                    return decodeURIComponent(pair[1]);
                }
            }
            browserAPI.log('Query variable %s not found', variable);
        }
        var result = {
            Passengers: [],
            TripSegments: [],
            TicketNumbers: [],
        };
        var response = null;
        $.ajax({
            url: 'https://booking.elal.com/bfm/service/extly/retrievePnr/secured/manageMyBooking?enc=' + encodeURIComponent(getQueryVariable('enc')),
            async: false,
            method: 'GET',
            contentType: 'application/json',
            beforeSend: function (request) {
                request.setRequestHeader('authorization', 'Bearer ' + sessionStorage.getItem('sessionId').replace(/"/g, ''));
            },
            dataType: 'json',
            success: function(data){
                console.log(data);
                response = data;
            }
        })
        result.RecordLocator = response.data.bookingSummary.booking.reference;
        for (const pass of response.data.bookingSummary.booking.passengers) {
            result.Passengers.push(pass.firstName + ' ' + pass.lastName);
        }
        for (const extra of Object.entries(response.data.passengersExtras)) {
            if (extra[1]) {
                for (const ticket of extra[1]) {
                    result.TicketNumbers.push(ticket.ticketNumber);
                }
            }
        }
        for (const trip of response.data.bookingSummary.booking.trip) {
            var segment = {};

            for (const fare of trip.bound.fares) {
                segment.BookingClass = fare.rbd;
                segment.Cabin = fare.bookingClassName;
            }
            for (const seg of trip.bound.segments) {
                if (typeof response.data.selection.seat.boundSegments[trip.bound.id] != 'undefined') {
                    let seatsMap = response.data.selection.seat.boundSegments[trip.bound.id].passengers
                    let seats = [];
                    for (const key of Object.keys(seatsMap)) {
                        const seat = seatsMap[key];
                        seats.push(seat.seatName);
                    }
                    segment.Seats = seats.join(', ');
                    browserAPI.log("Seats: " + segment.Seats);
                }

                segment.FlightNumber = seg.flightNumber;
                segment.AirlineName = seg.airline.name;
                segment.DepCode = seg.departureAirport.code;
                segment.ArrCode = seg.arrivalAirport.code;
                segment.DepartureTerminal = seg.departureTerminal.name;
                segment.ArrivalTerminal = seg.arrivalTerminal.name;

                // Duration
                var duration = new Date(seg.duration * 1000);
                var hh = duration.getUTCHours();
                var mm = duration.getUTCMinutes();
                if (hh < 10) {
                    hh = "0" + hh;
                }
                if (mm < 10) {
                    mm = "0" + mm;
                }
                var t = hh + "h " + mm + "m";
                if (hh > 0 || mm > 0)
                    segment.Duration = t;

                // 2022-08-08T17:20:00.000Z
                var date = null;
                if (seg.departureDate) {
                    var departureDate = seg.departureDate.replace(/\.\d+Z/g, '').replace(/T/g, ' ').replace(/-/g, '/');
                    browserAPI.log("DepDate: " + departureDate);
                    date = (new Date(departureDate + ' UTC')).getTime() / 1000;
                    browserAPI.log("DepDate: " + date);
                    if (!isNaN(date))
                        segment.DepDate = date;
                }

                if (seg.arrivalDate) {
                    var arrivalDate = seg.arrivalDate.replace(/\.\d+Z/g, '').replace(/T/g, ' ').replace(/-/g, '/');
                    browserAPI.log("ArrDate: " + arrivalDate);
                    // new Date('2022-05-15 07:00:00 UTC').getTime()
                    date = (new Date(arrivalDate + ' UTC')).getTime() / 1000;
                    browserAPI.log("ArrDate: " + date);
                    if (!isNaN(date))
                        segment.ArrDate = date;
                }
                result.TripSegments.push(segment);
            }
        }
        //console.log(result);
        params.data.Itineraries.push(result);
        provider.saveTemp(params.data);

        provider.setNextStep('loadNextFormRetrieve', function () {
            document.location.href = 'https://booking.elal.com/manage/login?lang=en&LANG=EN';
        });
    },

    /*parseItineraryInput: function (params) {
        browserAPI.log("parseItineraryInput");
        var result = {
            Passengers: [],
            TripSegments: []
        };
        // RecordLocator
        var recordLocator = $('input#pnrNbr');
        if (recordLocator.length > 0) {
            result.RecordLocator = recordLocator.val();
        }
        browserAPI.log("ConfirmationNumber: " + result.RecordLocator);
        // Status
        result.Status = $('td:contains("Trip status") > span').text();
        browserAPI.log("Status: " + result.Status);
        // passengers data
        result.AccountNumbers = [];
        $('.tablePassengerIndent:has(td:contains("Frequent flyer")), .tablePassengerIndent:has(td:contains("Known Traveller Number"))').each(function (el) {
            // account numbers
            var accountNumber = util.trim($(this).find('tr > td:contains("Frequent flyer") + td:nth(0), tr > td:contains("Known Traveller Number") + td:nth(0)').text());
            if (accountNumber) {

                if (/\,/.test(accountNumber)) {
                    result.AccountNumbers.push(accountNumber.split(/\s*\,\s*!/i));
                }
                else
                    result.AccountNumbers.push(accountNumber);
            }
            // names
            var passenger = util.trim($(this).parents('tr').eq(0)
                .prevAll('tr:has(span.textBold)').eq(0)
                .find('span.textBold')
                .text());
            if (passenger)
                result.Passengers.push(passenger);
        });
        browserAPI.log("Passengers: "+ JSON.stringify(result.Passengers));
        browserAPI.log("AccountNumbers: "+ JSON.stringify(result.AccountNumbers));
        // trip segments
        var segmentCounter = 0;
        var segments = $('input[id *= "flightNumber"]');
        segments.each(function (el) {
            browserAPI.log(">>> segment " + segmentCounter);
            var segment = {};
            var DT = null;
            var unixtime = null;
            // FlightNumber
            segment.FlightNumber = $('input[id *= "flightNumber"]:eq(' + segmentCounter + ')').val();
            browserAPI.log("FlightNumber: " + segment.FlightNumber);
            // AirlineName
            segment.AirlineName = $('input[id *= "airlineCode"]:eq(' + segmentCounter + ')').val();
            browserAPI.log("AirlineName: " + segment.AirlineName);
            // Operator
            var operatedBy = $('td[id *= "segOpBy_"]:eq(' + segmentCounter + ')');
            if (operatedBy.length > 0 && (operatedBy = util.findRegExp(operatedBy.text(), /Operated\s*by\s*(.+)/i))) {
                segment.Operator = operatedBy;
                browserAPI.log("Operator: " + segment.Operator);
            }
            else
                browserAPI.log("Operator not found");
            // DepCode
            segment.DepCode = $('input[id *= "departureCode"]:eq(' + segmentCounter + ')').val();
            browserAPI.log("DepCode: " + segment.DepCode);
            // DepName
            segment.DepName = $('input[id *= "departureCity_"]:eq(' + segmentCounter + ')').val();
            browserAPI.log("DepName: " + segment.DepName);
            // DepartureTerminal
            var depInfo = $('table[id *= "tabFgtReview"]:eq(' + segmentCounter + ')')
                .find(' span:contains("Departure:")')
                .closest('td')
                .siblings('td:eq(1)')
                .text();
            var terminal1 = util.findRegExp(depInfo, /terminal\s+(\w+)/i);
            segment.DepartureTerminal = terminal1;
            browserAPI.log("DepartureTerminal: " + segment.DepartureTerminal);
            // DepDate
            var date1 = $('input[id *= "departureDate"]:eq(' + segmentCounter + ')').val();
            browserAPI.log("DepDate: " + date1);
            date1 = (new Date(date1)).getTime() / 1000;
            browserAPI.log("DepDate: " + segment.DepDate);
            segment.DepDate = date1;
            // ArrCode
            segment.ArrCode = $('input[id *= "arrivalCode"]:eq(' + segmentCounter + ')').val();
            browserAPI.log("ArrCode: " + segment.ArrCode);
            // ArrName
            segment.ArrName = $('input[id *= "arrivalCity_"]:eq(' + segmentCounter + ')').val();
            browserAPI.log("ArrName: " + segment.ArrName);
            // ArrivalTerminal
            var arrInfo = $('span:contains("Arrival:"):eq(' + segmentCounter + ')')
                .closest('td')
                .siblings('td:eq(1)')
                .text();
            var terminal2 = util.findRegExp(arrInfo, /terminal\s+(\w+)/i);
            segment.ArrivalTerminal = terminal2;
            browserAPI.log("ArrivalTerminal: " + segment.ArrivalTerminal);
            // ArrDate
            var date2 = $('input[id *= "arrivalDate"]:eq(' + segmentCounter + ')').val();//todo
            browserAPI.log("ArrDate: " + date2);
            date2 = (new Date(date2)).getTime() / 1000;
            segment.ArrDate = date2;
            browserAPI.log("ArrDate: " + segment.ArrDate);
            // Duration
            segment.Duration = $('td[id *= "segDuration"]:eq(' + segmentCounter + ')').text().trim();
            browserAPI.log("Duration: " + segment.Duration);
            // Aircraft
            segment.Aircraft = $('td[id *= "segAircraft"]:eq(' + segmentCounter + ')').text().trim();
            browserAPI.log("Aircraft: " + segment.Aircraft);
            // Cabin
            var cabinElem = $('form[name *= "frmSeatMap"]').eq(segmentCounter);
            // BookingClass
            segment.BookingClass = cabinElem.find('input[name*="BOOKING_CLASS"]').val();
            browserAPI.log("BookingClass: " + segment.BookingClass);
            // Seats
            var seats = [];
            cabinElem.find('input[name *= "PREF_AIR_SEAT_ASSIGMENT"]').each(function(el) {
                var seat = $(this).val();
                if (seat && seat != "") {
                    seats.push(seat);
                }
            });
            if (seats.length === 0) {
                var text = segment.DepName + ' - ' + segment.ArrName;
                $('li:contains("' + text + '")').next('li').find('td.right').each(function(el) {
                    var seat = $(this).text().trim();
                    if (seat && seat != "") {
                        seats.push(seat);
                    }
                });
            }
            if (seats.length > 0) {
                segment.Seats = seats;
                browserAPI.log("Seats: " + segment.Seats);
            }
            else
                browserAPI.log("Seats not found");
            // Duration
            segment.Duration = $('td[id *= "segDuration"]:eq(' + segmentCounter +')').text().trim();
            browserAPI.log("Duration: " + segment.Duration);

            browserAPI.log("<<< segment " + segmentCounter);
            result.TripSegments.push(segment);
            segmentCounter++;
        });

        // console.log(result);//todo
        params.data.Itineraries.push(result);
        plugin.loadNextItinerary(params);
    }*/

};
