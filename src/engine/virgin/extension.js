var plugin = {

    hosts          : {'www.virginatlantic.com': true},
    blockImages    : /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),
    mobileUserAgent: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6 Safari/605.1.15",
    // keepTabOpen: true,//todo
    //hideOnStart: true,//todo

    cashbackLink     : '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        //return 'https://www.virginatlantic.com/custlogin/loginNow.action?stop_mobi=yes&type=mobileweb';
        return 'https://www.virginatlantic.com/login/loginPage?refreshURL=null';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn)
                    plugin.isSameAccount(params);
                else
                    plugin.loadLoginForm(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 25) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        var cookie = $('#privacy-btn-reject-all:visible');
        if (cookie.length) {
            cookie.get(0).click();
        }
        if ($('div.loginContentBody, button.login-btn:contains("Log in")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('td.MemberShipNo:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }

        provider.logBody("isLoggedInResultNull");

        return null;
    },

    checkNumber: function (params) {
        browserAPI.log("checkNumber");
        var number = $('td.MemberShipNo');
        browserAPI.log("number: " + number.text());
        var isSame = ((typeof (params.account.properties) != 'undefined')
              && (typeof (params.account.properties.Number) != 'undefined')
              && (params.account.properties.Number != '')
              && (number.length > 0)
              && (util.trim(number.text()) == params.account.properties.Number));
        if (isSame)
            plugin.loginComplete(params);
        else
            plugin.logout();
    },

    isSameAccount: function (params) {
        browserAPI.log("isSameAccount");
        if (document.location.href.indexOf('/myprofile/dashboard') === -1) {
            provider.setNextStep('checkNumber', function () {
                document.location.href = 'https://www.virginatlantic.com/myflyingclub/dashboard';
            });
        } else
            plugin.checkNumber(params);
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.virginatlantic.com/custlogin/logout.action';
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        if (document.location.href.indexOf('/login/loginPage') === -1) {
            provider.setNextStep('login', function () {
                document.location.href = 'https://www.virginatlantic.com/login/loginPage';
            });
        } else
            plugin.login(params);
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.virginatlantic.com/mytrips/findPnr.action";
            });
            return;
        }

        setTimeout(function () {
            var form = $('div.loginContentBody');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                var loginInput = $('input[id = "userId"]', form);
                loginInput.val(params.account.login);
                util.sendEvent(loginInput.get(0), 'input');
                var passInput = $('input[id = "password"]', form);
                passInput.val(params.account.password.substring(0, 20));
                util.sendEvent(passInput.get(0), 'input');

                provider.setNextStep('checkLoginErrors', function () {
                    $('button.loginButton').click();
                });
                plugin.waitForLoginError();

                setTimeout(function () {
                    var lastName = $('input[id = "lastName"]', form);
                    lastName.val(params.account.login2);
                    if (lastName.length > 0)
                        util.sendEvent(lastName.get(0), 'input');

                    provider.setNextStep('checkLoginErrors', function () {
                        $('button.loginButton').click();

                        var counter = 0;
                        var start = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            var errors = $('div:has(span.overlayErrorIcon) > div.overlayText:visible, div.errorMessageDiv:visible:eq(0)');
                            if (errors.length > 0 || counter > 40) {
                                clearInterval(start);
                                plugin.checkLoginErrors(params);
                            }// if (isLoggedIn !== null)

                            const question = $('h1.loginHeaderText:contains("Add security questions")');
                            if (question.length > 0 && util.filter(question.text()) != '') {
                                clearInterval(start);
                                plugin.checkLoginErrors(params);
                            };

                            counter++;
                        }, 500);
                    });
                }, 2000);
            } else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 2000);
    },

    waitForLoginError: function () {
        browserAPI.log("waitForLoginError");
        setTimeout(function () {
            var errors = $('div:has(span.overlayErrorIcon) > div.overlayText:visible');
            if (errors.length == 0)
                errors = $('div.errorMessageDiv:visible:eq(0)');
            if (errors.length > 0 && util.filter(errors.text()) != '' && !/Please check your last name is entered correctly/.test(errors.text()))
                provider.setError(util.filter(errors.text()));
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div:has(span.overlayErrorIcon) > div.overlayText:visible');

        if (errors.length == 0) {
            errors = $('div.errorMessageDiv:visible:eq(0)');
        };

        const question = $('h1.loginHeaderText:contains("Add security questions")');
        if (question.length > 0 && util.filter(question.text()) != '') {
            provider.showFader(' It seems that Virgin Atlantic needs to identify this computer before you can update this account. Please follow the instructions on the new tab to get this computer authorized .');
            let counter = 0;
            let login = setInterval(function () {
                browserAPI.log("waiting... " + counter);
    
                let success = $('button[aria-label="My booking"]');
                if (success.length > 0) {
                    clearInterval(login);
                    provider.logBody("2faSuccess");
                    plugin.loginComplete(params);
                }
                
                if (counter > 160) {
                    clearInterval(login);
                    let questionMessage = $('a.continue-link:visible');
                    if (questionMessage.length) {
                        provider.logBody("SecurityQuestionError");
                        provider.setError(['There are no security questions for your account. To continue, we need you to set some up', util.errorCodes.question], true);
                        return true;
                    }
                }
                counter++;
            }, 1000);
        } else {

            if (errors.length > 0 && util.filter(errors.text()) != '') {
                provider.setError(errors.text());
            } else {
                provider.setNextStep('loginComplete', function () {
                    if (document.location.href.indexOf('/myprofile/dashboard') === -1)
                        document.location.href = 'https://www.virginatlantic.com/myflyingclub/dashboard';
                });
            }
    
        };

    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin) {
            var futureFligths = $('#tab2');
            if (futureFligths.length > 0)
                futureFligths.click();
            else
                $('#mfPanelOpBtn').click();
            util.waitFor({
                selector: 'h2:contains("Future flights"):visible',
                success: function (elem) {
                    plugin.toItineraries(params);
                }
            });
            return;
        }

        if (params.autologin) {
            browserAPI.log("only autologin");
            provider.complete();
            return;
        }

        plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        var data = {};
        // Balance - Your Virgin Points balance
        const balance = $('h2:contains("Your"):contains("balance") + div:eq(0)');
        if (balance.length > 0) {
            data.Balance = util.trim(balance.text());
            browserAPI.log("Balance: " + data.Balance);
        } else {
            browserAPI.log("Balance not found");
        }
        // Member Number
        const number = $('td.MemberShipNo');
        if (number.length > 0) {
            data.Number = util.trim(number.text());
            browserAPI.log("Number: " + data.Number);
        } else
            browserAPI.log("Number not found");
        // Name
        const loginData = $('script:contains("loginData")');

        if (loginData.length > 0) {
            let name = util.findRegExp(loginData.text(), /"firstName":"([^"]+)/i);
            let lastName = util.findRegExp(loginData.text(), /","lastName":"([^"]+)/i);
            data.Name = util.beautifulName(name + ' ' + lastName);
            browserAPI.log("Name: " + data.Name);
        } else
            browserAPI.log("Name not found");
        // Member since
        const memberSince = $('td.memberSince');
        if (memberSince.length > 0) {
            data.MemberSince = util.trim(memberSince.text());
            browserAPI.log("MemberSince: " + data.MemberSince);
        } else
            browserAPI.log("MemberSince not found");
        // Tier points
        const tierPoints = $('h2:contains("Tier points") + div:eq(0)');
        if (tierPoints.length > 0) {
            data.TierPoints = util.trim(tierPoints.text());
            browserAPI.log("TierPoints: " + data.TierPoints);
        } else
            browserAPI.log("TierPoints not found");
        // ... member
        const tier = $('h2:contains("Tier points") + * + h3:eq(0)');
        if (tier.length > 0 && util.trim(tier.text()) !== '') {
            data.EliteStatus = util.findRegExp(tier.text(), /(.+) member/);
            browserAPI.log("EliteStatus: " + data.EliteStatus);
        } else
            browserAPI.log("EliteStatus not found");
        // Expiration date
        const exp = $('th:contains("Miles expiry date") + td:not([class *= "noDisplay"])');
        if (exp) {
            browserAPI.log("Exp date: " + exp.text());
            let date = new Date(exp.text() + ' UTC');
            let unixtime = date / 1000;
            if (date !== 'NaN' && !isNaN(unixtime)) {
                browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime);
                params.data.properties.AccountExpirationDate = unixtime;
            }
        }// if (exp)
        else
            browserAPI.log("Expiration date not found");

        params.data.properties = data;
        // save data
        // console.log(params.data.properties);//todo
        params.data.properties.HistoryRows = [];
        params.data.endHistory = false;
        provider.saveTemp(params.data);

        // Parsing History
        provider.setNextStep('parseHistory', function () {
            document.location.href = "https://www.virginatlantic.com/acctactvty/manageacctactvty.action";
        });
    },

    preParseHistory: function (params) {
        browserAPI.log("preParseHistory");
        provider.eval("" +
            "let form = document.forms['chooseDateForm'];" +
            "form['customSearch'].value = \"C\";" +
            "form['startDate'].value = " + plugin.getDate(3) +
            "form['endDate'].value = " + plugin.getDate() +
            "form.submit();" +
        "");

        setTimeout(function () {
            browserAPI.log("timeout");
            plugin.parseHistory(params);
        }, 5000);
    },

    parseHistory: function (params) {
        browserAPI.log("parseHistory");
        provider.updateAccountMessage();
        let history = [];
        let startDate = params.account.historyStartDate;

        browserAPI.log("historyStartDate: " + startDate);

        let nodes = $('table.activityTable tr:has(td[headers="tierPointCol"])');
        browserAPI.log('Total ' + nodes.length + ' items were found');
        for (let i = 0; i < nodes.length; i++) {
            let row = {};
            // Activity date
            let activityDate = util.filter(plugin.getElement($('td[headers="dateCol"]', nodes.eq(i))).text());
            // Transaction Date
            let transactionDate = util.filter(plugin.getElement($('td[headers="transDateCol"]', nodes.eq(i))).text());
            // Activity
            let description = util.filter(plugin.getElement($('td[headers="activityCol"]', nodes.eq(i))).text());
            // Tier Points
            let tierPoints = util.filter(plugin.getElement($('td[headers="mileageCol"]', nodes.eq(i))).text());
            // Avios
            let points = util.filter(plugin.getElement($('td[headers="tierPointCol"]', nodes.eq(i))).text());

            browserAPI.log("Date: " + activityDate + " / " + description + " / " + tierPoints + " / " + points);

            let dateStr = activityDate;
            activityDate = null;
            if ((typeof (dateStr) !== 'undefined') && (dateStr !== '')) {
                // IE, FF fix
                let date = new Date(dateStr + ' UTC');
                let unixtime = date / 1000;
                if (unixtime !== 'NaN') {
                    browserAPI.log("Activity date: " + date + " Unixtime: " + unixtime);
                    activityDate = unixtime;
                }// if (date != 'NaN')
            }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))

            dateStr = transactionDate;
            transactionDate = null;
            browserAPI.log("date: " + dateStr);
            if ((typeof (dateStr) !== 'undefined') && (dateStr !== '')) {
                // IE, FF fix
                let date = new Date(dateStr + ' UTC');
                let unixtime = date / 1000;
                if (unixtime !== 'NaN') {
                    browserAPI.log("Transaction Date: " + date + " Unixtime: " + unixtime);
                    transactionDate = unixtime;
                }// if (date != 'NaN')
            }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))

            if (startDate > 0 && activityDate < startDate) {
                browserAPI.log("break at date " + dateStr + " " + activityDate);
                params.data.endHistory = true;
                break;
            }// if (startDate > 0 && postDate < startDate)

            row = {
                'Date'            : activityDate,
                'Transaction Date': transactionDate,
                'Activity'        : description,
                'Description'     : description,
                'Tier points'     : tierPoints,
            };

            if (/Bonus/i.test(description)) {
                row['Bonus Mileage'] = points;
            } else {
                row['Mileage'] = points;
            }

            params.data.properties.HistoryRows.push(row);
        }// for (var i = 0; i < nodes.length; i++)


        if (
            typeof (params.account.parseItineraries) == 'boolean' &&
            params.account.parseItineraries
        ) {
            // Parsing Itineraries
            provider.setNextStep('parseItineraries', function () {
                document.location.href = "https://www.virginatlantic.com/myflyingclub/dashboard";
            });

            return;
        }

        // console.log(params.data.properties);//todo
        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    parseItineraries: function (params) {
        browserAPI.log("parseItineraries");
        $('li:contains("My Flights")').click();
        provider.updateAccountMessage();
        params.data.Itineraries = [];
        params.data.pnrs = [];
        params.data.confirmationNo = 0;
        params.data.confirmationNumbers = 0;

        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            if (
                $('h2:contains("Future flights")').length > 0
                || $('h2:contains("Are you missing a flight ?"):visible').length
                || counter > 15
            ) {
                clearInterval(start);

                let confirmationNumbers = $('form[id *= "view_details_"] input[name = "confirmationNo"]');
                browserAPI.log('Total ' + confirmationNumbers.length + ' itineraries were found');
                params.data.confirmationNumbers = confirmationNumbers.length;

                // console.log(params.data.properties);//todo
                provider.saveTemp(params.data);

                if (confirmationNumbers.length === 0) {
                    params.account.properties = params.data.properties;

                    // no Itineraries
                    if ($('h2:contains("Are you missing a flight ?"):visible').length > 0) {
                        params.account.properties.Itineraries = [{NoItineraries: true}];
                        browserAPI.log('NoItineraries: true');
                    }

                    provider.saveProperties(params.account.properties);
                    provider.complete();

                    return;
                }

                plugin.openItinerary(params);
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    openItinerary: function (params) {
        browserAPI.log("openItinerary");

        if (params.data.confirmationNo > 0) {
            $('li:contains("My Flights")').click();
        }

        let counter = 0;
        let openItinerary = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            if ($('h2:contains("Future flights")').length > 0 || counter > 15) {
                clearInterval(openItinerary);
                let form = $('form[id *= "view_details_"]:eq(' + params.data.confirmationNo + ')');
                browserAPI.log("open itinerary #" + params.data.confirmationNo);
                params.data.confirmationNo++;
                provider.saveTemp(params.data);
                provider.setNextStep('parseItinerary', function () {
                    form.submit();
                });
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    parseItineraryStep: function (params) {
        browserAPI.log('parseItineraryStep');
        util.waitFor({
            selector: 'div[id *= "itin_"]:visible',
            success: function (elem) {
                plugin.parseItinerary(params);
            },
            timeout: 7
        });
    },

    parseItinerary: function (params) {
        browserAPI.log("parseItinerary");

        let res = {};
        let referenceNum = $('span.bcReferenceNum').text();

        browserAPI.log("Parse Itinerary #" + referenceNum);
        res.RecordLocator = referenceNum;
        // Passengers
        let passengerInfo = $('div.PassDetailsBlock div.passengersNameUpper');
        let i = 0;
        res.Passengers = [];
        passengerInfo.each(function () {
            const node = $(this);
            // browserAPI.log(">>> node #" + i);
            let passenger = util.beautifulName(util.findRegExp(util.trim(node.text()), /^(.+?)\s*(?:\(|$)/));
            // browserAPI.log("<<< node #" + i);
            res.Passengers.push(passenger);
            i++;
        });
        browserAPI.log("Passengers: " + JSON.stringify(res.Passengers));
        // Ticket Numbers
        let ticketInfo = $('div.PassDetailsBlock').find('div:contains("eTicket"), div:contains("E-ticket")').find('span.number');
        i = 0;
        res.TicketNumbers = [];
        ticketInfo.each(function () {
            const node = $(this);
            // browserAPI.log(">>> node #" + i);
            let ticketNumber = util.trim(node.text());
            // browserAPI.log("<<< node #" + i);
            res.TicketNumbers.push(ticketNumber);
            i++;
        });
        browserAPI.log("TicketNumbers: " + JSON.stringify(res.TicketNumbers));
        // Account Numbers
        let numbersInfo = $('span#frequentFlyerNumberTrip');
        i = 0;
        res.AccountNumbers = [];
        numbersInfo.each(function () {
            const node = $(this);
            // browserAPI.log(">>> node #" + i);
            let number = util.trim(node.text());
            // browserAPI.log("<<< node #" + i);
            res.AccountNumbers.push(number);
            i++;
        });
        browserAPI.log("AccountNumbers: " + JSON.stringify(res.AccountNumbers));

        let segments = $('div[id *= "itin_"]');
        browserAPI.log("Total " + segments.length + " segments were found");
        let invalidSegment = false;
        let segment_i = 0;
        res.TripSegments = [];

        segments.each(function () {
            let node = $(this);
            let segment = {};
            browserAPI.log(">>> Segment " + segment_i);
            // FlightNumber and AirlineName
            let flightNumberStr = node.find('p.flightNumber').contents().filter(function () {
                return this.nodeType === 3; //Node.TEXT_NODE
            }).text();

            if (util.findRegExp(flightNumberStr, /^[A-Z]{2}$/)) {
                let depTime = node.find('span.departTimeFormat').text();
                let arrTime = node.find('span.arraivalTimeFormat').text();

                if (depTime === '12:00 AM' && arrTime === '12:00 AM') {
                    browserAPI.log('>>>> Skipping invalid segment');
                    browserAPI.log("<<< Segment " + segment_i);
                    invalidSegment = true;
                }

                return;
            }
            // AirlineName
            segment.AirlineName = util.findRegExp(flightNumberStr, /(\w{2})\s*\d+/i);
            browserAPI.log("AirlineName: " + segment.AirlineName);
            // FlightNumber
            segment.FlightNumber = util.findRegExp(flightNumberStr, /\w{2}\s*(\d+)/i);
            browserAPI.log("FlightNumber: " + segment.FlightNumber);
            // DepName, DepCode, ArrName, ArrCode
            let departReturnMainText = node.find('p.departReturnMainText').text();
            let locations = /([^\(]*)\s+\((\w{3})\),\s*[a-z]+\s*to\s+(.*)\s+\((\w{3})\)/ims.exec(departReturnMainText);

            if (locations) {
                segment.DepName = util.filter(locations[1]);
                segment.DepCode = locations[2];
                segment.ArrName = util.filter(locations[3]);
                segment.ArrCode = locations[4];
            }

            if (!locations) {
                locations = /(\w{3}),\s*to\s+(.*)\s+(\w{3})/ims.exec(departReturnMainText);
                if (locations) {
                    segment.DepName = segment.DepCode = util.filter(locations[1]);
                    segment.ArrName = locations[2];
                    segment.ArrCode = locations[3];
                }
            }

            if (!locations) {
                locations = /([^\(]*)\s+\((\w{3})\),\s*[a-z]+\s*to\s+(\w{3})/ims.exec(departReturnMainText);
                if (locations) {
                    segment.DepName = locations[1];
                    segment.DepCode = locations[2];
                    segment.ArrName = segment.ArrCode = util.filter(locations[3]);
                }
            }

            if (!locations) {
                // (VOU), to (UCH),
                locations = /\((\w{3})\),\s*to\s*\((\w{3})\)/ims.exec(departReturnMainText);
                if (locations) {
                    segment.DepName = segment.DepCode = util.filter(locations[1]);
                    segment.ArrName = segment.ArrCode = util.filter(locations[2]);
                }
            }

            if (!segment.FlightNumber || !segment.DepCode) {
                browserAPI.log('>>>> check invalid segment');
                browserAPI.log('segment: ' + JSON.stringify(segment));
                browserAPI.log('departReturnMainText: ' + departReturnMainText);

                return;
            }

            let xpath = 'input.itineraryFlags[ftnum *= ' + segment.FlightNumber + '][origcode = ' + segment.DepCode + '][destcode = ' + segment.ArrCode + ']';
            // console.log(node.prev(xpath)[0]); //todo
            let segmentid = node.prev(xpath).attr('segmentid');
            let legid = node.prev(xpath).attr('legid');
            browserAPI.log("segmentid: " + segmentid + " / legid: " + legid);

            if (!legid || !segmentid) {
                browserAPI.log('>>>> check invalid segment 2');

                return;
            }
            // Seats
            let seatsInfo = $('div.removeFocus:has(form:has(input[name = "legId"][value = "' + legid + '"]):has(input[name = "segmentNumber"][value = "' + segmentid + '"]))').prev('div.tripitineraryDiv').find('span.seatValignT > span:not(:contains("class")):not(:contains("Number"))');
            // console.log('div.removeFocus:has(form:has(input[name = "legId"][value = "' + legid + '"]):has(input[name = "segmentNumber"][value = "' + segmentid + '"]))'); //todo
            // console.log(seatsInfo[0]); //todo
            i = 0;
            segment.Seats = [];
            seatsInfo.each(function () {
                const node = $(this);
                // browserAPI.log(">>> node #" + i);
                let seat = util.trim(node.text());
                // browserAPI.log("<<< node #" + i);
                segment.Seats.push(seat);
                i++;
            });
            browserAPI.log("Seats: " + JSON.stringify(segment.Seats));
            // DepDate
            let depTime = node.prev(xpath).attr('scheddeptime');
            let depDate = util.findRegExp(node.prev(xpath).attr('depdate'), /,s*(.+)/) + " " + depTime;
            browserAPI.log("DepDate: " + depDate);

            if (util.trim(depDate) !== '') {
                let DT = new Date(depDate + ' UTC');
                let unixtime = DT / 1000;
                if (!isNaN(unixtime)) {
                    browserAPI.log("DepDate: " + depDate + " / Unixtime: " + unixtime);
                    segment.DepDate = unixtime;
                } else
                    browserAPI.log(">>> Invalid DepDate");
            }

            // ArrDate
            let arrTime = node.prev(xpath).attr('schedarrtime');
            let arrDate = util.findRegExp(node.prev(xpath).attr('arrdate'), /,s*(.+)/) + " " + arrTime;
            browserAPI.log("ArrDate: " + arrDate);

            if (util.trim(arrDate) !== '') {
                let DT = new Date(arrDate + ' UTC');
                let unixtime = DT / 1000;
                if (!isNaN(unixtime)) {
                    browserAPI.log("ArrDate: " + arrDate + " / Unixtime: " + unixtime);
                    segment.ArrDate = unixtime;
                } else
                    browserAPI.log(">>> Invalid ArrDate");
            }

            // Duration
            // 7.42 = 7hr 42m
            let duration = node.prev(xpath).attr('flighttime');
            if (duration) {
                segment.Duration = parseInt(duration) + "hr";
                segment.Duration = segment.Duration + " " + ((duration % 1).toFixed(2) * 100) + "m";
                browserAPI.log("Duration: " + segment.Duration);
            }

            segment.TraveledMiles = node.find('p.flightmiles > span').text();
            browserAPI.log("TraveledMiles: " + segment.TraveledMiles);
            segment.Aircraft = util.filter(node.find('span.aircraftName').text());
            browserAPI.log("Aircraft: " + segment.Aircraft);
            segment.Cabin = util.trim(node.find('div.flightStatusClass > span:not(:contains("Air")):not(:contains("All ")):not(:contains("Operated by "))').text());
            browserAPI.log("Cabin: " + segment.Cabin);

            if (segment.Cabin === '') {
                segment.Cabin = util.trim(node.find('span.fsrSmallFlightText > span:contains("Cabin Class") + span').text());
                browserAPI.log("Cabin: " + segment.Cabin);
            }

            // Operator
            segment.Operator = util.findRegExp(node.find('div.fsrSmallFlightText:contains("Operated by")').text(), /Operated bys*\s*(?:\w+\s+-)?(.+?)?(?:s+DBAs+|$)/);
            browserAPI.log("Operator: " + segment.Operator);

            if (
                (segment.DepCode === 'OPE' || segment.DepCode === 'OPB')
                && segment.ArrCode === 'NTK'
                && (
                    (
                segment.DepCode === segment.ArrCode
                && (arrTime === '12:00' || arrTime === '00:00')
                    )
                    // || (
                    //     segment.DepDate === segment.ArrDate
                    //     && (segment.ArrDate === '12-31' || segment.ArrDate === '08-31')
                    // )
                )
                && segment.Cabin === "YY YY"
                && segment.AirlineName === 'YY' && segment.FlightNumber === '101'
            ) {
                segment = {'Cancelled': true};
            }

            // console.log(segment); //todo
            res.TripSegments.push(segment);
            browserAPI.log("<<< Segment " + segment_i);
            segment_i++;
        });

        if (res.TripSegments.length === 0 && invalidSegment === true) {
            browserAPI.log(">>> Skipping invalid flight");

            return [];
        }

        let allCancelled = res.TripSegments.length > 0;

        for (let seg in res.TripSegments) {
            if (!res.TripSegments.hasOwnProperty(seg)) {
                browserAPI.log("skip bad card #" + seg);
                return;
            }

            if (typeof (res.TripSegments[seg].Cancelled) != 'undefined'
                || !res.TripSegments[seg].Cancelled
            ) {
                allCancelled = false;
            } else {
                res.TripSegments[seg].splice(seg, 1);
            }
        }

        if (allCancelled) {
            res.Cancelled = true;
        }
        // todo: wtf ???
        // let array = ['OPE', 'NTK', 'REM', 'VVD'];
        //
        // if (
        //     typeof (segment.Cabin) != 'undefined'
        //     && segment.Cabin === 'YY YY'
        //     && $.inArray(segment.DepCode, array) !== -1
        //     && $.inArray(segment.ArrCode, array) !== -1
        // ) {
        //     browserAPI.log('Skip: Flight on hold or something');
        //
        //     return [];
        // }

        browserAPI.log('Parsed Itinerary:');
        // console.log(res); //todo
        browserAPI.log(JSON.stringify(res));
        params.data.Itineraries.push(res);
        provider.saveTemp(params.data);

        if (params.data.confirmationNo === params.data.confirmationNumbers) {
            params.account.properties = params.data.properties;
            params.account.properties.Itineraries = params.data.Itineraries;
            // console.log(params.account.properties); // todo
            provider.saveProperties(params.account.properties);
            provider.complete();
            return;
        }

        provider.setNextStep('openItinerary', function () {
            document.location.href = "https://www.virginatlantic.com/myflyingclub/dashboard";
        });
    },

    getElement: function (element) {
        return element.contents().filter(function () {
            return this.nodeType === Node.TEXT_NODE;
        });
    },

    getDate: function (offset) {
        // browserAPI.log("getDate");
        let date = new Date();

        if (typeof (offset) != 'undefined')
            date.setFullYear(date.getUTCFullYear() - offset);

        let result = '';

        if (/^\d$/.test(date.getUTCDate()))
            result = result + '/0' + date.getUTCDate();
        else
            result = result + '/' + date.getUTCDate();

        if ((date.getUTCMonth() + 1) < 10)
            result = result + '0' + (date.getUTCMonth() + 1);
        else
            result = result + '' + (date.getUTCMonth() + 1);

        result = result + '/' + date.getUTCFullYear();

        // browserAPI.log(">>> Date: " + result);

        return result;
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            const confNo = params.account.properties.confirmationNumber;
            const link = $('.mtHeads:contains("' + confNo + '")').next('.headsLinks').find('.manageBookingButton:contains("Manage this booking")');

            if (link.length === 0) {
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }

            provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
        }, 2000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        const properties = params.account.properties.confFields;
        $('#mfPanelOpBtn').click();
        const form = $('#findTripsStandAlone');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        form.find('input[name = "confirmationNo"]').val(properties.ConfNo);
        form.find('input[name = "firstName"]').val(properties.FirstName);
        form.find('input[name = "lastName"]').val(properties.LastName);
        provider.setNextStep('itLoginComplete', function () {
            form.find('#findFltButtonStandAlone').get(0).click();
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }

};