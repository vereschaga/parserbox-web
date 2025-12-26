var plugin = {
    hideOnStart: true,
    clearCache: true,
    hosts: {
        'www.hawaiianairlines.com': true,
        'mobile.hawaiianairlines.com' : true,
        'www2.hawaiianairlines.com': true,
        'mytrips.hawaiianairlines.com': true,
    },
    //keepTabOpen: true, // todo
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),

    cashbackLinkMobile : false,
    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	getStartingUrl: function(params){
		return 'https://www.hawaiianairlines.com/my-account#/dashboard';
	},

    getFocusTab: function(account, params){
        return true;
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        /*provider.setNextStep('parseItinerariesInit', function () {
            document.location.href = 'https://www.hawaiianairlines.com/my-account/my-trips';
        });
        return;*/
        browserAPI.log("start");
        browserAPI.log('Location: ' + document.location.href);
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        // Sorry! Your session has timed out.
        if (document.location.href.indexOf('https://www.hawaiianairlines.com/book/error?ErrorType=SessionTimeout') !== -1
            || document.location.href === 'about:blank'
            || document.location.href === 'https://www.hawaiianairlines.com/#/dashboard'
        ) {
            browserAPI.log("Sorry! Your session has timed out.");
            plugin.loadLoginForm(params);
            return;
        }// if (document.location.href.indexOf('https://www.hawaiianairlines.com/book/error?ErrorType=SessionTimeout') != -1)
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
                        plugin.logout();
                } else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("lastPage");
                // Access to this website has been temporarily blocked
                var error = $('div.page-not-found-content div:contains("Access to this website has been temporarily blocked. Please try again after 10 minutes."):visible, div[class *= "page-not-found-content"] div:contains("Your request to access this website has been temporarily blocked for security reasons. Please try again after 10 minutes."):visible');
                if (
                    error.length === 0
                    && (
                        ($('p:contains("net::ERR_TOO_MANY_REDIRECTS"):visible').length > 0 && $('h2:contains("Webpage not available"):visible').length > 0)
                        || $('h2:contains("Internal Server Error - Read"):visible').length > 0
                    )
                ) {
                    provider.setError(util.errorMessages.providerErrorMessage, true);
                    return;
                }
                if (error.length > 0)
                    provider.setError([util.filter(error.text()), util.errorCodes.providerError], true);
                else
                    provider.setError(util.errorMessages.unknownLoginState, true);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
	},

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('label:contains("Member Number") + span').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
		if ($('form#login:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
		let number = $('label:contains("Member Number") + span').text();
		browserAPI.log("number: " + number);
		return ((typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.AccountNumber) != 'undefined')
			&& (account.properties.AccountNumber != '')
			&& (number == account.properties.AccountNumber.replace(/\s/g, "")));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.hawaiianairlines.com/MyAccount/Login/SignOut?area=';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.hawaiianairlines.com/my-account/my-trips/manage-trip-itinerary';
            });
            return;
        }
        var form = $('form#login:visible');
		if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            var login = params.account.login.replace(/\s+/ig, '');
            //form.find('input[name = "UserName"]').val(login);
            //form.find('input[name = "Password"]').val(params.account.password);
            // refs #11326
            //util.sendEvent(form.find('input[name = "UserName"]').get(0), 'input');
            //util.sendEvent(form.find('input[name = "Password"]').get(0), 'input');

            // angularjs
            provider.eval(
                "var scope = angular.element(document.querySelector('form#login')).scope();"
                + "scope.loginModel.UserName = '" + login + "';"
                + "scope.loginModel.Password = '" + params.account.password + "';"
            );

            provider.setNextStep('checkLoginErrors', function() {
                // captcha recognize
                setTimeout(function() {
                    var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    //browserAPI.log("waiting captcha -> " + captcha);
                    if (captcha && captcha.length > 0) {
                        browserAPI.log("waiting...");
                        if(provider.isMobile){
                            provider.command('show', function(){
                                provider.reCaptchaMessage();
                                var submit = form.find('[type=submit]');
                                submit.bind('click', function(event){
                                    var scope = angular.element(form.eq(0)).scope();
                                    provider.command('hide', function(){
                                        provider.setNextStep('checkLoginErrors', function(){
                                            scope.submitLogin();
                                            browserAPI.log("captcha entered by user");
                                        });
                                    });
                                    event.preventDefault();
                                    return false;
                                });
                            });
                        } else {
                            provider.reCaptchaMessage();
                            browserAPI.log("waiting...");
                            var counter = 0;
                            var login = setInterval(function () {
                                browserAPI.log("waiting... " + counter);
                                var errors = $('div#LoginError div.alert-content:visible');
                                if (errors.length > 0) {
                                    clearInterval(login);
                                    if (/Your account is locked/.test(errors.text())) {
                                        provider.setError([errors.text(), util.errorCodes.lockout], true);
                                        return;
                                    }
                                    if (/We apologize. Login is under maintenance\./.test(errors.text())) {
                                        provider.setError([errors.text(), util.errorCodes.providerError], true);
                                        return;
                                    }

                                    provider.setError(errors.text(), true);
                                }// if (errors.length > 0)
                                if (counter > 80) {
                                    clearInterval(login);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                }
                                counter++;
                            }, 500);
                        }
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.find('button[type = "submit"]').click();
                        setTimeout(function() {
                            plugin.checkLoginErrors(params);
                        }, 20000);
                    }
                }, 2000);
                //setTimeout(function () {
                //    var captcha = $('img#CaptchaImage');
                //
                //    provider.captchaMessageDesktop();
                //    if (captcha.length > 0) {
                //        browserAPI.log("waiting...");
                //        plugin.saveImage('https://www.hawaiianairlines.com' + captcha.attr('src'), form);
                //    }// if (captcha.length > 0)
                //    else
                //        browserAPI.log("captcha is not found");
                //}, 2000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    /*saveImage: function (url, form, params) {
        var img = document.createElement("img");
        img.src = url;
        img.onload = function () {
            var key = encodeURIComponent(url),
                canvas = document.createElement("canvas");

            canvas.width = img.width;
            canvas.height = img.height;
            var ctx = canvas.getContext("2d");
            ctx.drawImage(img, 0, 0);
            //localStorage.setItem(key, canvas.toDataURL("image/png"));
            var dataURL= canvas.toDataURL("image/png");
            browserAPI.log("dataURL: " + dataURL);
            // recognize captcha
            browserAPI.send("awardwallet", "recognizeCaptcha", {
                captcha: dataURL,
                "extension": "gif"
            }, function (response) {
                console.log(JSON.stringify(response));
                if (response.success === true) {
                    console.log("Success: " + response.success);
                    form.find('input[name = "CaptchaInputText"]').val(response.recognized);
                    util.sendEvent(form.find('input[name = "CaptchaInputText"]').get(0), 'input');

                    provider.setNextStep('checkLoginErrors');
                    form.find('input[name = "submit"]').click();
                    setTimeout(function() {
                        plugin.checkLoginErrors(params);
                    }, 7000)
                }// if (response.success === true))
                if (response.success === false) {
                    console.log("Success: " + response.success);
                    provider.setError(['We could not recognize captcha. Please try again later.', util.errorCodes.providerError], true);
                }// if (response.success === false)
            });
        }
    },*/

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div#LoginError div.alert-content:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {

            let message = util.filter(errors.text().replace(/^error\s*/, ''));

            if (/Your account is locked/.test(message))
                provider.setError([message, util.errorCodes.lockout], true);
            else {
                if (/403 - Forbidden: Access is denied/.test(message)) {
                    provider.complete();
                    return;
                }
                if (/Error An error occurred while processing your request\./.test(message)
                    || /Service Unavailable/.test(message)
                    || /502 Bad Gateway/.test(message)
                    || /We apologize. Login is under maintenance\./.test(message)
                ) {
                    provider.setError([message, util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(message, true);
                return;
            }
		}// if (errors.length > 0 && util.filter(errors.text()) != '')

        plugin.loginComplete(params);
    },

	toItineraries: function(params) {
        browserAPI.log("toItineraries");
		setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('span:contains("'+ confNo +'"), h3:contains("'+ confNo +'")').parents('div.col').find('a:contains("Open")');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
            }
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
	},

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        console.log(properties);
        var form = $('form#ManageTrip');
        if (form.length > 0) {
            form.find('input[name = "code_or_ticket"]').val(properties.ConfNo);
            form.find('input[name = "last_name"]').val(properties.LastName);
            // refs #11326
            util.sendEvent(form.find('input[name = "code_or_ticket"]').get(0), 'input');
            util.sendEvent(form.find('input[name = "last_name"]').get(0), 'input');
            provider.setNextStep('itLoginComplete', function () {
                form.find('button[name = "submit"]').click();
            });
        }
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

	itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
		provider.complete();
	},

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://mytrips.hawaiianairlines.com/';
            });
            return;
        }
        if (typeof(params.account.fromPartner) == 'string') {
            setTimeout(provider.close, 1000);
        }
        /*
         * Please take a moment to make sure we have your latest information
         * and create a Username to access your new HawaiianMiles dashboard!
         */
        var errors = $(":contains('Please take a moment to make sure we have your latest information and create a Username to access your new HawaiianMiles dashboard!'):visible, :contains('Update your profile to access your new HawaiianMiles dashboard!'):visible, :contains('Update your profile to access your HawaiianMiles dashboard!'):visible, :contains('Update your Security Questions to access your HawaiianMiles dashboard!'):visible");
        if (errors.length > 0) {
            provider.setError(["Hawaiian Airlines (HawaiianMiles) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
            return;
        }
        // RESET YOUR PASSWORD
        errors = $("h1 > em:contains('Reset Your Password'):visible");
        if (errors.length > 0) {
            provider.setError(["Hawaiian Airlines website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
            return;
        }
        // parse account
        if (params.autologin) {
            provider.complete();
            return;
        }
        var counter = 0;
        var parsing = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            if ($('div:contains("Your session has expired. Please sign in again to continue."):visible').length > 0) {
                clearInterval(parsing);
                browserAPI.log("Start login again...");
                provider.logBody("startLoginAgain");
                plugin.start(params);
            }
            if ($('span#current-balance, #my_account_user_details_dropdown').length > 0 || counter > 30) {
                clearInterval(parsing);
                browserAPI.log("Force start parsing...");

                if(document.location.href === plugin.getStartingUrl(params)) {
                    plugin.parse(params);
                } else {
                    provider.setNextStep('parse', function () {
                        provider.logBody("forceStartParsing");
                        document.location.href = plugin.getStartingUrl(params);
                    });    
                }
            }
            counter++;
        }, 500);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        var data = {};

        // Balance - Current Balance
        var balance = $('span#current-balance');
        if (balance.length > 0) {
            data.Balance = util.trim(balance.attr('end-val'));
            browserAPI.log("Balance: " + data.Balance);
        } else
            browserAPI.log("Balance not found");
        // Name
        var name = $('h2.hamiles-logo-header + p');
        if (name.length > 0) {
            data.Name = util.beautifulName(util.trim(util.filter(name.text())));
            browserAPI.log("Name: " + data.Name);
        } else
            browserAPI.log("Name not found");
        // Member Number
        var number = $('label:contains("Member Number") + span');
        if (number.length > 0) {
            data.AccountNumber = number.text();
            browserAPI.log("Member #: " + data.AccountNumber);
        } else
            browserAPI.log("Member # not found");
        // Status
        const status = $('h2.hamiles-logo-header + p + h3');
        if (status.length > 0) {
            data.Status = status.text();
            browserAPI.log("Status: " + data.Status);
        } else
            browserAPI.log("Status not found");
        /*
        // Last Activity
        var lastActivity = $('table.data-table tr:has(td):eq(0) > th:eq(0)');
        if (lastActivity.length > 0) {
            data.LastActivity = lastActivity.text();
            browserAPI.log("Last Activity: " + data.LastActivity);
            if (data.LastActivity) {
                var date = new Date(data.LastActivity + ' UTC');
                if (!isNaN(date)) {
                    // ExpirationDate = lastActivity" + "18 months"
                    date.setMonth(date.getMonth() + 18);
                    var unixtime = date / 1000;
                    if ( date != 'NaN' && !isNaN(unixtime) ) {
                        browserAPI.log("ExpirationDate = lastActivity + 18 months");
                        browserAPI.log("Expiration Date: " + date + " Unixtime: " + util.trim(unixtime) );

                        // refs #19209
                        if (unixtime < 1609459200) {
                            browserAPI.log("correcting exp date to January 1, 2021");
                            unixtime = 1609459200;
                        }

                        data.AccountExpirationDate = unixtime;
                    }
                } else
                    browserAPI.log("Invalid Expiration Date");
            }//if (data.LastActivity)
        } else
            browserAPI.log("Last Activity not found");
        */

        // save data
        params.data = data;
        //console.log(params.data);
        provider.saveTemp(params.data);

        provider.setNextStep('parseProperties', function () {
            document.location.href = 'https://www.hawaiianairlines.com/my-account/hawaiianmiles/mileage-statement';
        });
    },

    parseProperties: function (params) {
        browserAPI.log("parse properties");
        provider.updateAccountMessage();
        // Member Since
        var response = $('script:contains("MileageStatementModelJson")');
        if (response.length > 0) {
            response = response.html();

            var memberSince = util.findRegExp(response, /"MemberSince":"([^\"]+)/);
            if (memberSince) {
                params.data.MemberSince = memberSince;
                browserAPI.log("Member Since: " + params.data.MemberSince);
            } else
                browserAPI.log("Member Since not found");
            // Prior Balance
            var priorBalance = util.findRegExp(response, /"PriorBalance":"([^\"]+)/);
            if (priorBalance) {
                params.data.PriorBalance = priorBalance;
                browserAPI.log("Prior Balance: " + params.data.PriorBalance);
            } else
                browserAPI.log("Prior Balance not found");
            // Miles Credited this Month
            var creditedthisMonth = util.findRegExp(response, /"MilesCredited":"([^\"]+)/);
            if (creditedthisMonth) {
                params.data.CreditedthisMonth = creditedthisMonth;
                browserAPI.log("Miles Credited this Month: " + params.data.CreditedthisMonth);
            } else
                browserAPI.log("Miles Credited this Month not found");
            // Miles Redeemed this Month
            var milesRedeemed = util.findRegExp(response, /"MilesRedeemed":"([^\"]+)/);
            if (milesRedeemed) {
                params.data.RedeemedthisMonth = milesRedeemed;
                browserAPI.log("Miles Redeemed this Month: " + params.data.RedeemedthisMonth);
            } else
                browserAPI.log("Miles Redeemed this Month not found");
            // Qualifying Flight Miles
            var qualifyingMiles = util.findRegExp(response, /"QualifyingMiles":"([^\"]+)/);
            if (qualifyingMiles) {
                params.data.QualifyingFlightMiles = qualifyingMiles;
                browserAPI.log("Qualifying Flight Miles: " + params.data.QualifyingFlightMiles);
            } else
                browserAPI.log("Qualifying Flight Miles not found");
            // Qualifying Flight Segments
            var qualifyingSegments = util.findRegExp(response, /"QualifyingSegments":"([^\"]+)/);
            if (qualifyingSegments) {
                params.data.QualifyingFlightSegments = qualifyingSegments;
                browserAPI.log("Qualifying Flight Segments: " + params.data.QualifyingFlightSegments);
            } else
                browserAPI.log("Qualifying Flight Segments not found");

            // todo: history
            var history = [];
            var startDate = params.account.historyStartDate;
            browserAPI.log("historyStartDate: " + startDate);
            params.data.HistoryRows = [];

            var nodes = util.findRegExp(response, /MilageActivityDetails":((?:null|[^\]]+\]))\,/);
            if (nodes && nodes.length) {
                browserAPI.log('Nodes length: ' + nodes.length);
                nodes = JSON.parse(nodes);
                if (nodes) {
                    console.log(nodes);//todo
                    browserAPI.log('Total ' + nodes.length + ' items were found');
                }
                else {
                    browserAPI.log("history not found");
                    browserAPI.log(JSON.stringify(util.findRegExp(response, /MilageActivityDetails":((?:null|[^\]]+\]))\,/)));
                }
                for (var transaction in nodes) {

                    if (!nodes.hasOwnProperty(transaction)) {
                        continue;
                    }

                    var dateStr = util.filter(nodes[transaction].PostedDateDisplay);
                    var postDate = null;
                    browserAPI.log("date: " + dateStr );
                    if ((typeof(dateStr) != 'undefined') && (dateStr != '')) {
                        var date = new Date(dateStr + ' UTC');
                        var unixtime =  date / 1000;
                        if (unixtime != 'NaN') {
                            browserAPI.log("Date: " + date + " Unixtime: " + util.trim(unixtime) );
                            postDate = unixtime;
                        }// if (date != 'NaN')
                    }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))
                    else
                        postDate = null;

                    if (startDate > 0 && postDate < startDate) {
                        browserAPI.log("break at date " + dateStr + " " + postDate);
                        break;
                    }

                    var activityDateDisplay = util.filter(nodes[transaction].ActivityDateDisplay);
                    if ((typeof(activityDateDisplay) != 'undefined') && (activityDateDisplay != '')) {
                        var activityDate = new Date(activityDateDisplay + ' UTC');
                        var unixtime =  activityDate / 1000;
                        if (unixtime != 'NaN') {
                            browserAPI.log("Date: " + activityDate + " Unixtime: " + util.trim(unixtime) );
                            activityDate = unixtime;
                        }// if (date != 'NaN')
                    }// if ((typeof(dateStr) != 'undefined') && (dateStr != ''))
                    else
                        activityDate = null;

                    var row = {
                        'Posted Date': postDate,
                        'Activity Date': activityDate,
                        'Description': util.filter(nodes[transaction].Description),
                        'Segments': util.filter(nodes[transaction].Segments),
                        'Miles': util.filter(nodes[transaction].Miles),
                        'Bonus Miles': util.filter(nodes[transaction].BonusMiles),
                        'Total Miles': util.filter(nodes[transaction].TotalMiles)
                    };

                    params.data.HistoryRows.push(row);
                }// for (var i = 0; i < nodes.length; i++)
                // console.log(params.data.history);
            }// if (nodes)
            else {
                browserAPI.log("history not found");
            }
        }
        else
            browserAPI.log("script not found");

        params.account.properties = params.data;
        provider.saveProperties(params.account.properties);
        // console.log(params.account.properties);//todo

        /*provider.setNextStep('parseDiscounts', function() {
            document.location.href = 'https://www.hawaiianairlines.com/my-account/member-discounts';
        });*/

        if (typeof (params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            if (document.location.href != 'https://mytrips.hawaiianairlines.com/') {
                provider.setNextStep('parseItinerariesInit', function () {
                    document.location.href = 'https://mytrips.hawaiianairlines.com/';
                });
            }
            else
                plugin.parseItinerariesInit(params);
        }// if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries)
        else
            provider.complete();
    },

    parseDiscounts: function(params) {
        browserAPI.log('parseDiscounts');

        var discounts = $('div.discount.row');
        browserAPI.log('Total ' + discounts.length + ' Discounts were found');
        var subAccounts = [];
        $.each(discounts, function (_, node) {
            node = $(node);
            var nameNodes = node.find('h2').contents().filter(function () { return this.nodeType === Node.TEXT_NODE; });
            var fullName = util.unionArray(nameNodes, ' ');
            var name = util.findRegExp(fullName, /^(.+?\b(?:RT|Roundtrip)\b)/i);
            if (!name) {
                name = util.findRegExp(fullName, /^(.+?)s*[-–]/i);
            }
            if (!name) {
                name = fullName;
            }
            var code = util.findRegExp(node.text(), /E-certificate # (\w+)/);
            var passengers = util.findRegExp(node.text(), /# of Passengers:\s+(\d+)/);
            var expUnix = null;
            var expStr = util.findRegExp(node.text(), /Book: Now [-–] (\d+\/\d+\/\d{4})/u);
            if (!expStr) {
                expStr = util.findRegExp(node.text(), /Booking Periods*Now through (d+\/d+\/d{4})/u);
            }
            if (expStr) {
                var expDate = new Date(expStr + ' UTC');
                if (expDate.getTime()) {
                    expUnix = Math.floor(expDate.getTime() / 1000);
                }
            }
            var acc = {
                'Balance':           null,
                'Code':              'hawaiian' + code,
                'DisplayName':       name,
                'CertificateNumber': code,
                'Passengers':        passengers,
                'ExpirationDate':    expUnix
            };
            browserAPI.log(">> adding SubAccount: " + JSON.stringify(acc));
            subAccounts.push(acc);
        });
        params.account.properties.SubAccounts = subAccounts;
        provider.saveProperties(params.account.properties);

        if (typeof (params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            if (document.location.href != 'https://mytrips.hawaiianairlines.com/') {
                provider.setNextStep('parseItinerariesInit', function () {
                    document.location.href = 'https://mytrips.hawaiianairlines.com/';
                });
            }
            else
                plugin.parseItinerariesInit(params);
        }// if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries)
        else
            provider.complete();
    },

    parseItinerariesInit: function (params) {
        browserAPI.log("parseItinerariesInit");
        params.data.stepItinerary = 0;
        params.data.Itineraries = [];
        plugin.parseItinerariesStep(params);
    },

    parseItinerariesStep: function(params) {
        browserAPI.log("parseItinerariesStep");
        provider.updateAccountMessage();
        util.waitFor({
            selector: "h1:contains('My trips'):visible",
            success: function() {
                plugin.parseItineraryStep(params);
            },
            fail: function() {
                provider.complete();
            },
            timeout: 15
        });
    },

    parseItineraryStep: function (params) {
        browserAPI.log("parseItineraryStep");
        let btn = $('.trips__group').find('ha-button:contains("View itinerary")').eq(params.data.stepItinerary);
        browserAPI.log('parseItineraryStep: ' + params.data.stepItinerary + ', btn.length: ' + btn.length);
        if (btn.length) {
            params.data.stepItinerary++;
            btn.click();
            util.waitFor({
                selector: "h1:contains('Your itinerary'):visible",
                success: function () {
                    setTimeout(function () {
                        plugin.parseItinerary(params);
                        setTimeout(function () {
                            window.history.back();
                            plugin.parseItinerariesStep(params);
                        }, 3000);
                    }, 2000);
                },
                fail: function () {
                },
                timeout: 20
            });
        } else {
            browserAPI.log('End of reservation parsing');
            if (params.data.stepItinerary === 0 && $('div:contains("You do not have any upcoming trips connected to your HawaiianMiles account."):visible').length) {
                browserAPI.log("No upcoming reservations");
                params.account.properties.Itineraries = [{ NoItineraries:true }];
            } else
                params.account.properties.Itineraries = params.data.Itineraries;
            //console.log(params.account.properties);//todo
            provider.saveProperties(params.account.properties);
            provider.complete();
        }
    },

    parseItinerary: function (params) {
        browserAPI.log("parseItinerary");
        let data = JSON.parse(JSON.parse(sessionStorage.getItem('state')));
        //console.log(data);
        var result = {};
        if (typeof data.tripState == 'undefined' || typeof data.tripState.trip.results == 'undefined') {
            browserAPI.log('Not found trip');
            return;
        }

        data.tripState.trip.results.forEach(function (trip, key) {
            var ticketNumbers = [];

            // ConfirmationNumber
            result.RecordLocator = trip.confirmationCode;
            browserAPI.log("RecordLocator: " + result.RecordLocator);
            // Passengers
            result.Passengers = [];
            trip.passengers.entries.forEach(function (passenger) {
                result.Passengers.push(/*util.beautifulName*/(passenger.passengerName.firstName + ' ' + passenger.passengerName.lastName));
            });
            browserAPI.log("Passengers: " + result.Passengers);
            // Segments
            result.TripSegments = [];
            browserAPI.log(">>> Total segments were found: " + trip.flights.entries.length);
            trip.flights.entries.forEach(function (seg, k) {
                browserAPI.log(">>> Segment " + k);
                var segment = {};
                var unixtime;
                var date;
                // AirlineName
                if (typeof seg.operatedBy != 'undefined')
                    segment.AirlineName = seg.operatedBy;
                else if (typeof seg.marketedBy != 'undefined')
                    segment.AirlineName = seg.marketedBy;
                browserAPI.log("AirlineName: " + segment.AirlineName);
                // FlightNumber
                segment.FlightNumber = seg.flightNumber;
                browserAPI.log("FlightNumber: " + segment.FlightNumber);

                // DepCode
                segment.DepCode = seg.origin;
                browserAPI.log("DepCode: " + segment.DepCode);
                // ArrCode
                segment.ArrCode = seg.scheduledDestination;
                browserAPI.log("ArrCode: " + segment.ArrCode);

                // DepDate
                var depDate = seg.scheduledDeparture.airportDateTimeString;
                date = new Date(plugin.correctDateTime(depDate) + ' UTC');
                if (date.getTime()) {
                    unixtime = date.getTime() / 1000;
                    browserAPI.log("DepDate: " + depDate + " Unixtime: " + unixtime);
                    segment.DepDate = unixtime;
                } else {
                    browserAPI.log("DepDate " + depDate + " failed");
                }
                // ArrDate
                var arrDate = seg.scheduledArrival.airportDateTimeString;
                date = new Date(plugin.correctDateTime(arrDate) + ' UTC');
                if (date.getTime()) {
                    unixtime = date.getTime() / 1000;
                    browserAPI.log("ArrDate: " + arrDate + " Unixtime: " + unixtime);
                    segment.ArrDate = unixtime;
                } else {
                    browserAPI.log("ArrDate " + arrDate + " failed");
                }
                let seats = [];
                // segments.entries[0].details[0].flightDetails
                trip.segments.entries.forEach(function (entries) {
                    entries.details.forEach(function (details) {
                        details.flightDetails.forEach(function (flightDetails) {
                            if (seg.id == flightDetails.flightId ) {
                                seats.push(flightDetails.seatNumber);
                                ticketNumbers.push(flightDetails.ticketNumber);
                            }
                        });

                    });
                });
                segment.Seats = seats.join(', ')
                browserAPI.log("Seats: " + segment.Seats);
                result.TripSegments.push(segment);
                browserAPI.log("<<< Segment id " + k);
            });
            result.TicketNumbers = ticketNumbers;
            browserAPI.log("TicketNumbers: " + result.TicketNumbers);
        });
        params.data.Itineraries.push(result);
        provider.saveTemp(params.data);
    },

    // 2023-09-26T15:10:00.000
    correctDateTime: function(date) {
        return date.replace('T', ', ').replace(/.000$/, '')
    }

    // TODO: not working
    /*parseItineraries: function(params) {
        browserAPI.log("parseItineraries");
        //provider.updateAccountMessage();
        plugin.getBookingsData(function (data) {
            let response = JSON.parse(data)
            if (typeof response === 'undefined') {
                //provider.complete();
                return;
            }
            console.log(response);

            for (const trip of response.results) {
                plugin.parseItinerary(params, trip);
            }

            console.log(params.data.Itineraries);
            params.account.properties.Itineraries = params.data.Itineraries;
            provider.saveProperties(params.account.properties);
            provider.complete();
        }, function () {
            browserAPI.log('Error booking data');
            plugin.itLoginComplete(params);
        });
    },

    getBookingsData: function(callback, error) {
        var script = document.createElement("script");
        let oldXHROpen = window.XMLHttpRequest.prototype.open;
        window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
            this.addEventListener("load", function() {
                if (/\/exp-web-trips\/v1\/api\/trips/g.exec( url )) {
                    console.log(this.responseText);
                    localStorage.setItem("awData", this.responseText);
                }
            });
            return oldXHROpen.apply(this, arguments);
        };
        script.textContent =
            '            let oldXHROpen = window.XMLHttpRequest.prototype.open;\n' +
            '            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {\n' +
            '                this.addEventListener("load", function() {\n' +
            '                    if (/\\/exp-web-trips\\/v1\\/api\\/trips/g.exec(url)) {\n' +
            '                        console.log(method + " -> " + this.responseText);\n' +
            '                        localStorage.setItem("awData", this.responseText);\n' +
            '                    }\n' +
            '                });\n' +
            '                return oldXHROpen.apply(this, arguments);\n' +
            '            };';
        (document.getElementsByTagName('head')[0] || document.body).appendChild(script);

        util.waitFor({
            selector: "h1:contains('My trips'):visible",
            success: function() {
                console.log('success');
                var response = localStorage.getItem('awData');
                localStorage.removeItem('awData');
                callback(response);
            },
            fail: function() {
                console.log('error');
                localStorage.removeItem('awData');
                error();
            },
            timeout: 40
        });
    },*/
};
