var plugin = {
    hideOnStart: true,
    //keepTabOpen: true, // todo
    hosts: {'www.spirit.com': true},
    subscriptionKe: '3b6a6994753b4efc86376552e52b8432',
    getFocusTab: function (account, params) {
        return true;
    },

    getStartingUrl: function (params) {
        return "https://www.spirit.com/account/dashboard";
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));

        if (params.account.mode === 'confirmation') {
            provider.setNextStep('checkConfirmationNumberInternal', function () {
                document.location.href = "https://www.spirit.com/home-check-in";
            });
            return;
        }

        // logout issue
        if (document.location.href === 'https://www.spirit.com/') {
            plugin.loadLoginForm(params);
            return;
        }

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
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 6) {
                provider.logBody("start");
                clearInterval(start);

                const message = $('strong:contains("We’ll be done with our scheduled maintenance"):visible')

                if (message.length > 0) {
                    provider.setError([message.text(), util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 3000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Sign Out")').length > 0 && $('#free-spirit-number').text().length > 5) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('a[data-gtm-header="sign-in-link"]:visible, a.log-in:visible').length > 0 || $('form:has(#username):visible').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.filter($('#free-spirit-number').text().replace(/^#/g, ""));
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number !== '')
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            let logout = $('a:contains("Sign Out")');
            if (logout.length) {
                logout.get(0).click();
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.spirit.com/home-check-in";
            });
            return;
        }

        let cookie = $('#onetrust-reject-all-handler:visible');
        if (cookie.length > 0)
            cookie.get(0).click();

        setTimeout(function () {
            let form = $('form:has(#username):visible');

            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting saved credentials");
            util.setInputValue(form.find('#username'), params.account.login);
            util.setInputValue(form.find('#password'), params.account.password);

            util.sendEvent(form.find('#username').get(0), 'input');
            util.sendEvent(form.find('#password').get(0), 'input');

            provider.setNextStep('loginComplete', function () {
                form.find('button.btn-primary').click();
                // if we're still on this page, then login failed
                setTimeout(function () {
                    // An error has occurred. Please try again. If the error persists,
                    let error = $('div:contains("An error has occurred. Please try again. If the error persists,"):visible').eq(0);
                    if (error.length > 0) {
                        var message = error.text();
                        setTimeout(function () {
                            browserAPI.log("[Error]: " + message);
                            browserAPI.log("'An error has occurred', click login btn one more time");
                            form.find('button.btn-primary').get(0).click();
                            //provider.eval("$('button.btn-primary').click();");

                            setTimeout(function () {
                                provider.logBody("loginPageAfterError");
                                plugin.checkLoginErrors(params, message);
                            }, 7000);
                        }, 5000);
                        return;
                    }

                    plugin.checkLoginErrors(params);
                }, 5000);
            });
        }, 2000);
    },

    checkLoginErrors: function (params, errorOccurred) {
        browserAPI.log("checkLoginErrors");
        provider.logBody("checkLoginErrors");
        browserAPI.log('Current URL: ' + document.location.href);
        let error = $('div.s-error-text:visible');

        if (error.length === 0) {
            error = $('div.alert-danger:visible, div[role="alertdialog"][aria-describedby != "onetrust-policy-text"]:visible');
        }

        if (error.length > 0 && util.filter(error.text()) !== '') {
            let message = util.filter(error.text());
            browserAPI.log("[Error]: " + message);

            if (message.indexOf('Invalid email address or incorrect password. Please correct and re-try or select Sign Up.') !== -1) {
                provider.setError([message, util.errorCodes.invalidPassword], true);
                return;
            }
            // Please re-type in your temporary password.
            if (message.indexOf('Please re-type in your temporary password.') !== -1) {
                provider.setError(["Spirit Airlines (Free Spirit) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
                return;
            }

            if (
                message.indexOf('Multiple services match the specified type. More specific retrieval criteria is required.') !== -1
                || message.indexOf('errorMessages.defaultErrorMessage') !== -1
                || message.indexOf('An error has occurred. Please try again. If the error persists, please call our reservations department at 855-728-3555. Feel free to leave any comments regarding your website experience using the feedback button on your right hand side.') !== -1
                || message.indexOf('An error has occurred. Please try your request again. If the error persists, chat with us here for additional assistance') !== -1
            ) {
                provider.setError([message.replace('×', '').replace('Feedback', ''), util.errorCodes.providerError], true);
                return;
            }

            return;
        }

        if ($('form:has(#username):visible').length && typeof (errorOccurred) != 'undefined') {
            provider.setError([errorOccurred.replace('×', ''), util.errorCodes.providerError], true);
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            return provider.setNextStep('toItineraries', function () {
                document.location.href = "https://www.spirit.com/account/activity";
            });
        }

        if (params.autologin) {
            plugin.itLoginComplete(params);
            return;
        }

        plugin.parse(params);
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('td > span:contains("' + confNo +'")').closest('td').next('td').find('a:visible');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                    setTimeout(function () {
                        plugin.itLoginComplete(params);
                    }, 7000);
                });
            }// if (link.length > 0)
            if (link.length === 0 && counter > 10) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    ajax: function (params, url, callback, error) {
        browserAPI.log("ajax");
        browserAPI.log(url);
        let token = localStorage.getItem('token');
        $.ajax({
            type: 'GET',
            url: url,
            async: false,
            beforeSend: function (request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Ocp-Apim-Subscription-Key', plugin.subscriptionKe);
                request.setRequestHeader('Authorization', `${token}`);
                request.setRequestHeader('Content-Type', 'application/json');
            },
            dataType: 'json',
            cache: false,
            success: function (response) {
                callback(response);
            },
            error: function (response) {
                browserAPI.log(`fail: profile data status = ${response.status}`);
                error(response);
            }
        });
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parsePage");
        let token = localStorage.getItem('token');
        // browserAPI.log('token: ' + token);
        let data = {};
        plugin.ajax(params, 'https://api.spirit.com/prod-account/api/Account/accountdetail', function(response) {
            accountDetail(response);
        }, function(response) {
            browserAPI.log(">>> Retry");
            plugin.ajax(params, 'https://api.spirit.com/prod-account/api/Account/accountdetail', function(response) {
                accountDetail(response);
            }, function(response) {
                plugin.itLoginComplete(params);
            });
        });

        function accountDetail(response) {
            browserAPI.log('success');
            browserAPI.log("---------------- profile data ----------------");
            browserAPI.log(JSON.stringify(response));
            browserAPI.log("---------------- profile data ----------------");
            // Name
            let name = response.data.person.name;
            name = name.first + " " + name.last;
            data.Name = util.beautifulName((name));
            browserAPI.log('Name: '+ data.Name);

            let mainProgram = response.data.person.programs[0];

            if (response.data.person.programs.length > 1) {
                browserAPI.log('multiple programs were found');
                browserAPI.log(JSON.stringify(response.data.person.programs));

                let countOfPrograms = 0;

                for (const program in response.data.person.programs) {
                    if (!response.data.person.programs.hasOwnProperty(program)) {
                        browserAPI.log('skip wrong node');

                        continue;
                    }

                    if (response.data.person.programs[program].programCode === 'FS') {
                        browserAPI.log("skip program code 'FS'");

                        continue;
                    }

                    if (response.data.person.programs[program] === 'NK') {
                        mainProgram = response.data.person.programs[program];
                    }
                    countOfPrograms++;
                }

                if (countOfPrograms !== 1) {
                    return;
                }
            }

            let number = mainProgram.programNumber;

            if (!number) {
                browserAPI.log('programNumber not found');

                return;
            }

            // Balance - Your Current Miles
            data.Balance = mainProgram.pointBalance;
            browserAPI.log('Balance: ' + data.Balance);
            // Free Spirit Account Number
            data.Number = number;
            browserAPI.log('Number: ' + data.Number);
            // Mileage Earning Tier
            data.Status = response.data.tierStatus;
            browserAPI.log('Status: ' + data.Status);
            // Status Expiration - Free Spirit Silver  Valid through
            let tierEndDate = new Date (response.data.tierEndDate);

            if (tierEndDate >= new Date ()) {
                const month = tierEndDate.toLocaleString('en-us', { month: 'long' });
                data.StatusExpiration = month + " " + tierEndDate.getUTCDate() + ", " + tierEndDate.getUTCFullYear();
                browserAPI.log('StatusExpiration: ' + data.StatusExpiration);
            }

            let subAccounts = [];

            if (
                // Days left in membership
                typeof (response.data.clubMembership) != 'undefined'
                && response.data.clubMembership !== null
                && typeof (response.data.clubMembership.daysLeftInMembership) != 'undefined'
                && response.data.clubMembership.daysLeftInMembership > 0
                // Renewal Date
                && typeof (response.data.clubMembership.subscriptionEndDate) != 'undefined'
                && (new Date(response.data.clubMembership.subscriptionEndDate) / 1000)
            ) {
                // Spirit $9 Fare Club
                browserAPI.log('Savers$ Club Membership');
                data.CombineSubAccounts = false;
                // Days left in membership
                let day = response.data.clubMembership.daysLeftInMembership;
                // Renewal Date
                let exp = response.data.clubMembership.subscriptionEndDate;
                // Date Joined
                let DateJoined = null;

                if (typeof (response.data.clubMembership.subscriptionStartDate) != 'undefined') {
                    let joined = new Date (response.data.clubMembership.subscriptionStartDate);
                    const month = joined.toLocaleString('en-us', { month: 'long' });
                    DateJoined = month + " " + joined.getUTCDate() +", " + joined.getUTCFullYear();
                    browserAPI.log('DateJoined: ' + DateJoined);
                }

                subAccounts.push({
                    "Code"          : 'spiritSaversClubMembership',
                    "DisplayName"   : 'Savers$ Club Membership',
                    "Balance"       : null,
                    'DateJoined'    : DateJoined,
                    "ExpirationDate": new Date(response.data.clubMembership.subscriptionEndDate) / 1000
                });

                browserAPI.log(JSON.stringify(subAccounts));
            }
            data.SubAccounts = subAccounts;
            params.data.properties = data;
            provider.saveTemp(params.data);
        }

        $.ajax({
            type       : "POST",
            url        : "https://api.spirit.com/prod-account/graphql",
            async      : false,
            dataType   : "json",
            data: '{"operationName":null,"variables":{},"query":"{\n  memberTQPInfo(freeSpiritNumber: \"' + params.data.properties.Number + '\") {\n    creditCardTQP\n    extrasTQP\n    fareTQP\n    totalTQP\n    spiritTQPYTDBalance\n    spiritTQPMonthBalance\n    overrideTQP\n    __typename\n  }\n}\n"}',
            beforeSend: function(request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Ocp-Apim-Subscription-Key', plugin.subscriptionKe);
                request.setRequestHeader('Authorization', `Bearer ${token}`);
                request.setRequestHeader('Content-Type', 'application/json');
            },
            success: function (response) {
                browserAPI.log("---------------- StatusQualifyingPoints data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- StatusQualifyingPoints data ----------------");
                // Balance - Your Current Miles
                if (
                    typeof (response.data) != 'undefined'
                    && typeof (response.data.memberTQPInfo) != 'undefined'
                    && response.data.memberTQPInfo
                    && typeof (response.data.memberTQPInfo.totalTQP) != 'undefined'
                ) {
                    params.data.properties.StatusQualifyingPoints = response.data.memberTQPInfo.totalTQP;
                    browserAPI.log('StatusQualifyingPoints: ' + data.StatusQualifyingPoints);
                }
            },// success: function (response)
            error: function (response) {
                browserAPI.log("fail");
                response = $(response);
                browserAPI.log("---------------- fail StatusQualifyingPoints data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- fail StatusQualifyingPoints data ----------------");
            }
        });

        params.data.properties.HistoryRows = [];
        let date = new Date();
        let transactionPeriodStartDate = new Date();
        transactionPeriodStartDate.setFullYear(transactionPeriodStartDate.getFullYear() - 5);
        let startDate = params.account.historyStartDate;
        browserAPI.log("historyStartDate: " + startDate);

        let transactionPeriodStartDateValue = transactionPeriodStartDate.getUTCFullYear() + "-" + (transactionPeriodStartDate.getUTCMonth() + 1) + "-" + transactionPeriodStartDate.getUTCDate();
        let transactionPeriodEndDateValue = date.getUTCFullYear() + "-" + (date.getUTCMonth() + 1) + "-" + date.getUTCDate()

        $.ajax({
            type       : "POST",
            url        : "https://api.spirit.com/prod-account/graphql",
            async      : false,
            dataType   : "json",
            data: '{"operationName":null,"variables":{},"query":"{\n  mileageStatementInfo(\n    statementRequest: {accountNumber: \"' + params.data.properties.Number + '\", transactionPeriodStartDate: \"'+transactionPeriodStartDateValue+'\", transactionPeriodEndDate: \"'+transactionPeriodEndDateValue+'\", transactionType: \"ALL\", lastID: 0, pageSize: 10}\n  ) {\n    customerPointsBreakdown {\n      balance\n      category\n      credit\n      dateEarned\n      debit\n      description\n      ccQualifyingPoints\n      nkCcQualifyingPoints\n      nkQualifyingPoints\n      referenceNumber\n      __typename\n    }\n    startDate\n    startingBalance\n    startingBalanceSpecified\n    __typename\n  }\n}\n"}',
            beforeSend: function(request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Ocp-Apim-Subscription-Key', plugin.subscriptionKe);
                request.setRequestHeader('Authorization', `Bearer ${token}`);
                request.setRequestHeader('Content-Type', 'application/json');
            },
            success: function (response) {
                browserAPI.log("---------------- History data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- History data ----------------");
                if (
                    typeof (response.data) != 'undefined'
                    && typeof (response.data.mileageStatementInfo) != 'undefined'
                    && typeof (response.data.mileageStatementInfo.customerPointsBreakdown) != 'undefined'
                ) {
                    let transactions = response.data.mileageStatementInfo.customerPointsBreakdown;
                    browserAPI.log("Total " + transactions.length +  " transactions were found");

                    for (const transaction in transactions) {
                        if (!transactions.hasOwnProperty(transaction)) {
                            browserAPI.log('skip wrong node');

                            continue;
                        }

                        let credit = transactions[transaction].credit;
                        let dateStr = new Date(transactions[transaction].dateEarned + " UTC");
                        let postDate = dateStr / 1000;

                        if (
                            (credit && credit !== "" || transactions[transaction] !== "")
                            && postDate
                        ) {
                            // Last Activity
                            let lastActivity = (dateStr.getUTCMonth() + 1) + "/" + dateStr.getUTCDate() +"/" + dateStr.getUTCFullYear();
                            browserAPI.log("Last Activity: " + lastActivity);
                            params.data.properties.LastActivity = lastActivity;
                            // ExpirationDate = lastActivity" + "12 months"
                            dateStr.setMonth(dateStr.getMonth() + 12);
                            let unixTime = dateStr / 1000;
                            if (!isNaN(unixTime)) {
                                browserAPI.log("ExpirationDate = lastActivity + 12 months");
                                browserAPI.log("Expiration Date: " + dateStr + " Unixtime: " + unixTime);
                                params.data.properties.AccountExpirationDate = unixTime;
                            }

                            break;
                        }
                    }

                    for (const transaction in transactions) {
                        if (!transactions.hasOwnProperty(transaction)) {
                            browserAPI.log('skip wrong node');

                            continue;
                        }

                        let dateStr = new Date(transactions[transaction].dateEarned + " UTC");
                        let postDate = dateStr / 1000;

                        if (startDate > 0 && postDate < startDate) {
                            browserAPI.log("break at date " + dateStr + " " + postDate);
                            continue;
                        }// if (startDate > 0 && postDate < startDate)

                        let debit = transactions[transaction].debit;

                        if (debit === "") {
                            debit = "0";
                        }

                        debit = parseInt(debit.replace('/,/g', ''));

                        if (debit < 0) {
                            debit = -1 * debit;
                        }

                        let credit = transactions[transaction].credit;
                        credit = parseInt(credit.replace('/,/g', ''));

                        let row = {
                            'Date'       : postDate,
                            'Transaction': transactions[transaction].description,
                            'Points'     : credit - debit,
                            'Balance'    : transactions[transaction].balance
                        };

                        params.data.properties.HistoryRows.push(row);
                    }
                }
            },// success: function (response)
            error: function (response) {
                browserAPI.log("fail");
                response = $(response);
                browserAPI.log("---------------- fail History data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- fail History data ----------------");
            }
        });

        provider.saveTemp(params.data);

        if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
            return provider.setNextStep('parseItineraries', function () {
                document.location.href = "https://www.spirit.com/account/activity";
            });
        }
        else {
            params.account.properties = params.data.properties;
            // console.log(params.account.properties);//todo
            provider.saveProperties(params.account.properties);
            plugin.itLoginComplete(params);
        }
    },

    parseItineraries: function (params) {
        browserAPI.log("parseItineraries");
        let token = localStorage.getItem('token');
        provider.updateAccountMessage();
        params.data.Itineraries = [];

        $.ajax({
            type: 'GET',
            url: 'https://api.spirit.com/prod-user/graphql',
            data: '{"operationName":null,"variables":{},"query":"{\\n  findUserBookings(\\n    searchRequest: {includeDistance: true, returnCount: 100, includeAccrualEstimate: true, searchByCustomerNumber: true}\\n  ) {\\n    currentBookings {\\n      allowedToModifyGdsBooking\\n      bookingKey\\n      bookingStatus\\n      channelType\\n      destination\\n      distance\\n      editable\\n      expiredDate\\n      flightDate\\n      flightNumber\\n      name {\\n        first\\n        last\\n        __typename\\n      }\\n      origin\\n      passengerId\\n      recordLocator\\n      sourceAgentCode\\n      sourceDomainCode\\n      sourceOrganizationCode\\n      systemCode\\n      qualifyingPoints\\n      redeemablePoints\\n      __typename\\n    }\\n    __typename\\n  }\\n}\\n"}',
            async: false,
            beforeSend: function(request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Ocp-Apim-Subscription-Key', plugin.subscriptionKe);
                request.setRequestHeader('Authorization', `Bearer ${token}`);
                request.setRequestHeader('Content-Type', 'application/json');
            },
            dataType: 'json',
            cache: false,
            success: function (response) {
                browserAPI.log('success');
                browserAPI.log("---------------- booking data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- booking data ----------------");

                let currentBookings = response.data.findUserBookings.currentBookings;
                browserAPI.log('Total ' + currentBookings.length + ' itineraries were found');

                if (currentBookings.length === 0) {
                    params.account.properties = params.data.properties;
                    // no Itineraries
                    browserAPI.log('NoItineraries: true');
                    params.account.properties.Itineraries = [{NoItineraries: true}];
                    provider.saveProperties(params.account.properties);
                    plugin.itLoginComplete(params);

                    return;
                }

                for (const bookings in currentBookings) {
                    if (!currentBookings.hasOwnProperty(bookings)) {
                        browserAPI.log('skip wrong node');

                        continue;
                    }

                    let confNo = currentBookings[bookings].recordLocator;
                    let name = currentBookings[bookings].name.last;
                    browserAPI.log('Parse Itinerary #' + confNo);
                    let res = plugin.parseItineraryByConfNoRetrieve(params, token, name, confNo);
                    //console.log(res); //todo
                    browserAPI.log(JSON.stringify(res));
                    params.data.Itineraries.push(res);
                    provider.saveTemp(params.data);
                }
            },
            error: function (response) {
                browserAPI.log(`fail: booking data status = ${response.status}`);
            }
        });

        params.account.properties = params.data.properties;
        params.account.properties.Itineraries = params.data.Itineraries;
        // console.log(params.account.properties); // todo
        provider.saveProperties(params.account.properties);
        plugin.itLoginComplete(params);
    },


    // Retrieve

    getConfNoItinerary: function (params, callback) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        setTimeout(function () {
            var form = $('form.global-header-form-control');
            if (form.length > 0) {
                // angularjs
                function triggerInput(selector, enteredValue) {
                    const input = document.querySelector(selector);
                    var createEvent = function(name) {
                        var event = document.createEvent('Event');
                        event.initEvent(name, true, true);
                        return event;
                    };
                    input.dispatchEvent(createEvent('focus'));
                    input.value = enteredValue;
                    input.dispatchEvent(createEvent('change'));
                    input.dispatchEvent(createEvent('input'));
                    input.dispatchEvent(createEvent('blur'));
                }
                triggerInput('input[name = "lastName"]', properties.LastName);
                triggerInput('input[name = "recordLocator"]', properties.ConfNo);
                provider.setNextStep('itLoginComplete', function () {
                    form.find('button.btn-responsive-check-in').click();
                    setTimeout(function () {
                        plugin.itLoginComplete(params);
                    }, 7000);
                });
            }
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 3000);
    },

    checkConfirmationNumberInternal: function (params) {
        browserAPI.log("checkConfirmationNumberInternal");
        setTimeout(function () {
            var form = $('form.global-header-form-control');
            if (form.length > 0) {
                var properties = params.account;
                // angularjs
                function triggerInput(selector, enteredValue) {
                    const input = document.querySelector(selector);
                    var createEvent = function(name) {
                        var event = document.createEvent('Event');
                        event.initEvent(name, true, true);
                        return event;
                    };
                    input.dispatchEvent(createEvent('focus'));
                    input.value = enteredValue;
                    input.dispatchEvent(createEvent('change'));
                    input.dispatchEvent(createEvent('input'));
                    input.dispatchEvent(createEvent('blur'));
                }

                triggerInput('input[name = "lastName"]', properties.LastName);
                triggerInput('input[name = "recordLocator"]', properties.ConfNo);
                provider.setNextStep('parseItineraryByConfNo', function () {
                    form.find('button.btn-responsive-check-in').click();
                    setTimeout(function () {
                        plugin.parseItineraryByConfNo(params);
                    }, 5000);
                });
            }
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 3000);
    },

    parseItineraryByConfNo: function (params) {
        params.data.Itineraries = [];
        let token = localStorage.getItem('token');
        var properties = params.account;
        params.data.Itineraries.push(plugin.parseItineraryByConfNoRetrieve(params, token, properties.LastName, properties.ConfNo));
        //params.account.properties = params.data.properties;
        params.account.properties.Itineraries = params.data.Itineraries;
        //console.log(params.account.properties); // todo
        provider.saveProperties(params.account.properties);
        plugin.itLoginComplete(params);
    },

    parseItineraryByConfNoRetrieve: function (params, token, lastName, confNo) {
        browserAPI.log("parseItineraryByConfNo");

        if (!token || !confNo || !lastName) {
            browserAPI.log('Data not found!');
            return;
        }

        let res = {};
        $.ajax({
            type       : "POST",
            url        : "https://www.spirit.com/api/prod-booking/api/booking/retrieve",
            async      : false,
            dataType   : "json",
            data: JSON.stringify({
                "lastName"     : lastName,
                "recordLocator":  confNo,
            }),
            beforeSend: function(request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Ocp-Apim-Subscription-Key', plugin.subscriptionKe);
                request.setRequestHeader('Authorization', `Bearer ${token}`);
                request.setRequestHeader('Content-Type', 'application/json');
            },
            success: function (response) {
                res = plugin.parseItinerary(params, response, confNo);
            },
            error: function (response) {
                browserAPI.log("fail");
                response = $(response);
                browserAPI.log("---------------- fail Itinerary data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- fail Itinerary data ----------------");
            }
        });
        return res;
    },

    parseItinerary(params, response, confNo) {
        browserAPI.log("parseItinerary");

        browserAPI.log("---------------- Itinerary data ----------------");
        browserAPI.log(JSON.stringify(response));
        console.log(response);//todo
        browserAPI.log("---------------- Itinerary data ----------------");

        if (
            typeof (response.errors) != 'undefined'
            && response.errors
            && typeof (response.errors[0]) != 'undefined'
            && typeof (response.errors[0].rawMessage) != 'undefined'
            && response.errors[0].rawMessage
        ) {
            let message = response.errors[0].rawMessage;
            browserAPI.log('Error: ' + message);

            if (
                /Last name validation failed./i.test(message)
                || /The identifier 'RecordLocator' with value '{$confNo}' is invalid./i.test(message)
            ) {
                browserAPI.log('Error: ' + "We are unable to locate the itinerary. Please verify the information is correct and try again. The combination of the customer last name and the Confirmation Code is invalid. Please try again.");
            }

            return;
        }

        let data = response.data;
        // RecordLocator
        const res = {};
        browserAPI.log("Parse Itinerary #" + confNo);
        res.RecordLocator = confNo;

        if (!data) {
            browserAPI.log(">>>> wrong response");
            return;
        }

        // ReservationDate
        res.ReservationDate = parseInt(new Date(data.info.bookedDate) / 1000);
        browserAPI.log("ReservationDate: " + res.ReservationDate);

        if (
            typeof(data.isCancelled) != 'undefined'
            && data.isCancelled === true
        ) {
            res.Cancelled = true;
            browserAPI.log("Cancelled: " + res.Cancelled);
        }

        let status = null;
        switch (data.info.status) {
            case 2:
                status = 'Confirmed';
                break;

            case 3:
            case 4:
                status = 'Cancelled';
                break;

            default:
                browserAPI.log("Unknown status: " + data.info.status);
        }

        res.Status = status;
        browserAPI.log("Status: " + res.Status);
        // Currency
        res.Currency = data.currencyCode;
        browserAPI.log("Currency: " + res.Currency);
        // TotalCharge
        res.TotalCharge = data.breakdown.totalAmount;
        browserAPI.log("TotalCharge: " + res.TotalCharge);

        let breakdown = [];
        const fees = {};

        if (
            typeof (data.priceDisplay) != 'undefined'
            && typeof (data.priceDisplay.flightPrice) != 'undefined'
            && typeof (data.priceDisplay.flightPrice.breakdown) != 'undefined'
        ) {
            breakdown = data.priceDisplay.flightPrice.breakdown;
        }

        for (const price in breakdown) {
            if (!breakdown.hasOwnProperty(price)) {
                browserAPI.log('skip wrong node');
                continue;
            }

            const display = breakdown[price].display;

            if (display === 'Flight Price' && breakdown[price].price > 0) {
                continue;
            }

            fees[display] = breakdown[price].price;
        }

        if (data.priceDisplay.bags.total > 0) {
            fees["Bags"] = data.priceDisplay.bags.total;
        }

        if (data.priceDisplay.seats.total > 0) {
            fees["Seats"] = data.priceDisplay.seats.total;
        }

        res.Fees = fees;
        browserAPI.log("Fees: " + JSON.stringify(res.Fees));

        // Passengers
        let passengers = [];
        if (typeof (data.passengers) != 'undefined') {
            passengers = data.passengers;
        }

        // Account Numbers
        res.Passengers = [];
        res.AccountNumbers = [];

        for (const passenger in passengers) {
            if (!passengers.hasOwnProperty(passenger)) {
                browserAPI.log('skip wrong node');
                continue;
            }

            if (
                typeof (passengers[passenger].accountNumber) != 'undefined'
                && passengers[passenger].accountNumber
            ) {
                res.AccountNumbers.push(passengers[passenger].accountNumber);
            }

            res.Passengers.push(util.beautifulName(passengers[passenger].name.first + " " + passengers[passenger].name.last));
        }

        browserAPI.log("AccountNumbers: " + JSON.stringify(res.AccountNumbers));
        browserAPI.log("Passengers: " + JSON.stringify(res.Passengers));

        // Air Trip Segments
        let journeys = [];

        if (typeof (data.journeys) != 'undefined') {
            journeys = data.journeys;
        }

        browserAPI.log("Total " + journeys.length + " journeys were found");
        res.TripSegments = [];

        for (const journey in journeys) {
            if (!journeys.hasOwnProperty(journey)) {
                browserAPI.log('skip wrong node');
                continue;
            }

            let segments = {};
            let seg = {};
            if (typeof (journeys[journey].segments) != 'undefined') {
                segments = journeys[journey].segments;
            }

            browserAPI.log("Total " + segments.length + " segments were found");

            for (const segment in segments) {
                if (!segments.hasOwnProperty(segment)) {
                    browserAPI.log('skip wrong node');
                    continue;
                }

                let legs = [];
                if (typeof (segments[segment].legs) != 'undefined') {
                    legs = segments[segment].legs;
                }

                browserAPI.log("Total " + legs.length + " legs were found");
                for (const leg in legs) {
                    if (!legs.hasOwnProperty(leg)) {
                        browserAPI.log('skip wrong node');
                        continue;
                    }

                    // FlightNumber
                    seg.FlightNumber = segments[segment].identifier.identifier;
                    browserAPI.log("FlightNumber: " + seg.FlightNumber);
                    // AirlineName
                    seg.AirlineName = segments[segment].identifier.carrierCode;
                    browserAPI.log("AirlineName: " + seg.AirlineName);
                    // Aircraft
                    if (typeof (legs[leg].legInfo.equipmentType) != 'undefined') {
                        seg.Aircraft = legs[leg].legInfo.equipmentType;
                        browserAPI.log("Aircraft: " + seg.Aircraft);
                    }
                    // Cabin
                    seg.Cabin = segments[segment].cabinOfService;
                    browserAPI.log("Cabin: " + seg.Cabin);
                    // Duration
                    seg.Duration = legs[leg].travelTime.replace(/:\d+$/, "");
                    browserAPI.log("Duration: " + seg.Duration);
                    /*let hms = legs[leg].travelTime.replace(/:\d+$/, "");
                    let a = hms.split(':');
                    if (a.length == 2) {
                        let minutes = parseInt(a[0], 10) * 60 + parseInt(a[1], 10);
                        seg.Duration = `${Math.floor(minutes / 60)}h ${Math.floor(minutes % 60)}m`;
                        browserAPI.log("Duration: " + seg.Duration);
                    }*/


                    seg.Stops = journeys[journey].stops;
                    browserAPI.log("Stops: " + seg.Stops);

                    if (typeof (journeys[journey].distanceInMiles) != 'undefined') {
                        seg.TraveledMiles = journeys[journey].distanceInMiles;
                        browserAPI.log("TraveledMiles: " + seg.TraveledMiles);
                    }

                    // DepCode
                    seg.DepName = seg.DepCode = legs[leg].designator.origin;
                    browserAPI.log("DepCode: " + seg.DepCode);
                    // DepartureTerminal
                    if (typeof (legs[leg].legInfo.departureTerminal) != 'undefined') {
                        seg.DepartureTerminal = legs[leg].legInfo.departureTerminal;
                        browserAPI.log("DepartureTerminal: " + seg.DepartureTerminal);
                    }
                    // DepDate
                    let depTime = legs[leg].designator.departure;
                    browserAPI.log("depTime: " + depTime);
                    seg.DepDate = parseInt(new Date(depTime + 'Z') / 1000);
                    browserAPI.log("DepDate: " + seg.DepDate);

                    // ArrCode
                    seg.ArrName = seg.ArrCode = legs[leg].designator.destination;
                    browserAPI.log("ArrCode: " + seg.ArrCode);
                    // ArrivalTerminal
                    if (typeof (legs[leg].legInfo.arrivalTerminal) != 'undefined') {
                        seg.ArrivalTerminal = legs[leg].legInfo.arrivalTerminal;
                        browserAPI.log("ArrivalTerminal: " + seg.ArrivalTerminal);
                    }
                    // ArrDate
                    let arrTime = legs[leg].designator.arrival;
                    browserAPI.log("arrTime: " + arrTime);
                    seg.ArrDate = parseInt(new Date(arrTime+'Z') / 1000);
                    browserAPI.log("ArrDate: " + seg.ArrDate);

                    res.TripSegments.push(seg);
                }// for (const leg in legs)
            }// for (const segment in segments)
        }

        return res;

    },
};