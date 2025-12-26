var plugin = {
    // keepTabOpen: true, // todo
    hosts: {
        'giantfood.com': true,
        'www.giantfood.com': true,
    },

    getStartingUrl: function (params) {
        return 'http://www.giantfood.com/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params.account);
            if (isLoggedIn !== null && counter > 1) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account)) {
                        plugin.loginComplete(params);
                    } else {
                        plugin.logout(params);
                    }
                } else {
                    plugin.runAngular(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null || counter > 10) {
                clearInterval(start);

                const message = $('h4:contains("Site Temporarily Down"):visible');

                if (message.length > 0) {
                    provider.setError([message.text(), util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState, true);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },


    runAngular: function (params) {
        provider.setNextStep('login', function () {
            provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
            browserAPI.log('location: ' + document.location.href);
            document.location.href = plugin.getStartingUrl(params);
            browserAPI.log('location: ' + document.location.href);
        });
    },

    isLoggedIn: function (account) {
        browserAPI.log("isLoggedIn");
        let name = plugin.getElement("#header-account-button").text().trim();
        browserAPI.log("name: " + name);
        if (name && name === "Sign In") {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (name && name !== "Sign In") {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = plugin.getElement("#header-account-button").text().trim();
        browserAPI.log("name: " + name);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.Name !== 'undefined'
            && account.properties.Name !== ''
            && name
            && account.properties.Name.toLowerCase().indexOf(name.toLowerCase()) !== -1;
    },

    logout: function () {
        browserAPI.log("logout");
        if ($('.account-menu_nav').length === 0) {
            $('#header-account-button').click();
        }
        provider.setNextStep('loadLoginForm', function () {
            setTimeout(function () {
                $('#nav-account-menu-log-out').click();
            }, 500);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        $("button#header-account-button").click();
        setTimeout(function () {
            browserAPI.log("open login form");
            $("button#nav-sign-in").click();
        }, 500);

        util.waitFor({
            selector: 'button:contains("Sign")',
            success: function () {
                $("button#nav-account-menu-sign-in").click();
                browserAPI.log("click 'Sign In / Create Account'");
                setTimeout(function () {
                    browserAPI.log("set up login form");
                    // vue.js
                    $('input[id = "login-username"]').val(params.account.login);
                    $('input[id = "LoginForm-password-password"], input[id = "current-password"]').val(params.account.password);

                    // vue.js
                    provider.eval(
                        'function createNewEvent(eventName) {' +
                        'var event;' +
                        'if (typeof(Event) === "function") {' +
                        '    event = new Event(eventName);' +
                        '} else {' +
                        '    event = document.createEvent("Event");' +
                        '    event.initEvent(eventName, true, true);' +
                        '}' +
                        'return event;' +
                        '}'+
                        'var email = document.querySelector(\'input[id = "login-username"]\');' +
                        'email.dispatchEvent(createNewEvent(\'input\')); email.dispatchEvent(createNewEvent(\'change\'));' +
                        'var pass = document.querySelector(\'input[id = "LoginForm-password-password"], input[id = "current-password"]\');' +
                        'pass.dispatchEvent(createNewEvent(\'input\')); pass.dispatchEvent(createNewEvent(\'change\'));'
                    );

                    browserAPI.log("click 'SignIn'");
                    provider.setNextStep('checkLoginErrors');
                    setTimeout(function () {
                        $('button#sign-in-button').click();
                        setTimeout(function () {
                            let btnOk = $('button[id = "alert-button_primary-button"]:visible');
                            if (btnOk.length > 0) {
                                btnOk.click();
                            } else {
                                plugin.checkLoginErrors(params);
                            }
                        }, 5000)
                    }, 1000);
                }, 500)
            },
            fail: function () {
                provider.setError(util.errorMessages.loginFormNotFound);
            },
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('p.message-box_message:visible');

        if (errors.length > 0) {
            let message = util.filter(errors.text());
            browserAPI.log("[Error]: " + message);

            if (errors.indexOf('The sign in information you entered does not match our records') !== -1) {
                provider.setError([message, util.errorCodes.invalidPassword], true);
                return;
            }

            provider.complete();
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (params.autologin) {
            provider.complete();
            return;
        }

        plugin.parse(params);
    },

    getElement: function (element) {
        return $(element).contents().filter(function () {
           return this.nodeType === Node.TEXT_NODE;
       });
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parsePage");
        let opco = $('#header-account-button').attr('opco');
        browserAPI.log("opco: " + opco);
        let data = {};
        let storeNumber = null;
        let userId;

        $.ajax({
            type: 'GET',
            url: 'https://giantfood.com/api/v1.0/current/user',
            async: false,
            beforeSend: request => request.setRequestHeader('Accept', 'application/json, text/plain, */*'),
            success: user => {
                browserAPI.log('success');
                browserAPI.log("---------------- current user data ----------------");
                browserAPI.log(JSON.stringify(user));
                browserAPI.log("---------------- current user data ----------------");

                if (isNaN(parseInt(user?.userId))) {
                    browserAPI.log("userId not found");
                    provider.complete();

                    return;
                }

                userId = user.userId;
            },
            error: response => {
                browserAPI.log(`fail: current user id status = ${response.status}`);
                provider.complete();
            }
        });

        $.ajax({
            type: 'GET',
            url: `https://giantfood.com/api/v4.0/user/${userId}/profile`,
            async: false,
            beforeSend: function(request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Content-Type', 'application/json');
            },
            dataType: 'json',
            cache: false,
            success: function (response) {
                browserAPI.log('success');
                browserAPI.log("---------------- profile data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- profile data ----------------");

                if (
                    typeof (response) == 'undefined'
                    || typeof (response.response) == 'undefined'
                    || typeof (response.response.retailerCard) == 'undefined'
                    || typeof (response.response.retailerCard. cardNumber) == 'undefined'
                ) {
                    browserAPI.log('cardNumber not found');
                    provider.complete();

                    return;
                }

                let cardNumber = response.response.retailerCard.cardNumber;
                let storeNumber = response.response.refData.deliveryServiceLocation.storeNumber;

                data.Number = cardNumber;
                browserAPI.log('Number: ' + data.Number);

                $.ajax({
                    type: 'GET',
                    url: 'https://giantfood.com/apis/loyaltyaccount/v3/'+opco+'/' + cardNumber,
                    async: false,
                    beforeSend: function(request) {
                        request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                        request.setRequestHeader('Cache-Control', 'no-cache');
                        request.setRequestHeader('Pragma', 'no-cache');
                        request.setRequestHeader('Content-Type', 'application/json');
                    },
                    dataType: 'json',
                    cache: false,
                    success: function (response) {
                        browserAPI.log('success');
                        browserAPI.log("---------------- loyaltyaccount data ----------------");
                        browserAPI.log(JSON.stringify(response));
                        browserAPI.log("---------------- loyaltyaccount data ----------------");

                        // Name
                        const firstName = response.firstName ?? '';
                        const lastName = response.lastName ?? '';
                        // Name
                        if (typeof (response.firstName) != 'undefined' && typeof (response.lastName) != 'undefined') {
                            data.Name = util.beautifulName((response.firstName + " " + response.lastName));
                            browserAPI.log('Name: '+ data.Name);
                        } else {
                            browserAPI.log('Name not found');
                        }

                        if (
                            // from where?
                            !storeNumber
                            && typeof (response.storeNumber) != 'undefined'
                            && response.storeNumber === '0000'
                        )
                        {
                            storeNumber = '0662';
                        }

                        if (!storeNumber) {
                            browserAPI.log("store number not found");
                            provider.complete();

                            return;
                        }

                        if (storeNumber.length === 3) {
                            storeNumber = '0' + storeNumber;
                        }
                        else if(storeNumber.length === 2)
                        {
                            storeNumber = '00' + storeNumber;
                        }
                    },
                    error: function (response) {
                        browserAPI.log(`fail: loyaltyaccount data status = ${response.status}`);
                    }
                });
                params.data.properties = data;
                params.data.properties.SubAccounts = [];
                provider.saveTemp(params.data);
            },
            error: function (response) {
                browserAPI.log(`fail: profile data status = ${response.status}`);
                provider.complete();
            }
        });

        let program = null;

        $.ajax({
            type: 'GET',
            url: 'https://giantfood.com/apis/rewards/v1/preferences/'+opco+'/' + data.Number,
            async: false,
            beforeSend: function(request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Content-Type', 'application/json');
            },
            dataType: 'json',
            cache: false,
            success: function (programInfo) {
                browserAPI.log('success');
                browserAPI.log("---------------- preferences data ----------------");
                browserAPI.log(JSON.stringify(programInfo));
                browserAPI.log("---------------- preferences data ----------------");

                if (typeof (programInfo.value) == 'undefined') {
                    browserAPI.log("program not found");
                    provider.complete();

                    return;
                }

                program = programInfo.value;

                if ($.inArray(program, ["flex", "fuel"]) === -1) {
                    browserAPI.log("Unknown program: " + program);
                    provider.complete();
                }
            },
            error: function (response) {
                browserAPI.log(`fail: preferences data status = ${response.status}`);
                provider.complete();
            }
        });

        let subAccounts = [];

        $.ajax({
            type: 'GET',
            url: 'https://giantfood.com/apis/balances/program/v1/balances/' + data.Number + '?details=true&storeNumber=' + storeNumber,
            async: false,
            beforeSend: function(request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Content-Type', 'application/json');
            },
            dataType: 'json',
            cache: false,
            success: function (response) {
                browserAPI.log('success');
                browserAPI.log("---------------- balances data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- balances data ----------------");

                if (program === "fuel") {
                    browserAPI.log('set Balance NA');
                    params.data.properties.Balance = 'null';
                }

                let balances = {};
                let i = 0;

                if (typeof (response.balances) != 'undefined') {
                    balances = response.balances;
                }

                for (const reward in balances) {
                    if (!balances.hasOwnProperty(reward)) {
                        browserAPI.log('skip wrong node');

                        continue;
                    }

                    let name = balances[reward].name;
                    let balance = balances[reward].balance;
                    // Balance - Available Points
                    if ($.inArray(name, [
                        "Rewards Points",
                        "Flex Points",
                        "SS GO Points",
                        "Flex Rewards for Giant Foods",
                    ]) !== -1
                    ) {
                        if (i > 0) {
                            browserAPI.log("Multiple balances");
                            break;
                        }

                        // Balance - Available Points
                        params.data.properties.Balance = balance;
                        browserAPI.log('Balance: ' + data.Balance);
                        if (
                            typeof (balances[reward].detail) !='undefined'
                            && typeof (balances[reward].detail.gasPoints) !='undefined'
                            && typeof (balances[reward].detail.gasPoints[0]) !='undefined'
                        ) {
                            // Points Expiring
                            if (
                                typeof (balances[reward].detail.gasPoints[0].balance) !='undefined'
                            ) {
                                params.data.properties.ExpiringBalance = balances[reward].detail.gasPoints[0].balance;
                                browserAPI.log('ExpiringBalance: ' + data.ExpiringBalance);
                            }
                            // Expiration Date
                            if (
                                typeof (balances[reward].detail.gasPoints[0].expirationDate) !='undefined'
                            ) {
                                let dateStr = new Date(balances[reward].detail.gasPoints[0].expirationDate);
                                let unixTime = dateStr / 1000;
                                browserAPI.log("Expiration Date: " + dateStr + " Unixtime: " + unixTime);
                                if (!isNaN(unixTime)) {
                                    params.data.properties.AccountExpirationDate = unixTime;
                                }
                            }

                            i++;
                        }
                    }

                    // Grocery Savings
                    // SS Grocery Dollars / Flex Grocery Dollars
                    if (name.indexOf(' Grocery Dollars') !== -1) {
                        if (balance === 0) {
                            browserAPI.log("[Grocery Savings]: do not collect zero balance");
                            return;
                        }

                        let savings = {
                            "Code"       : "stopshopGrocerySavings",
                            "DisplayName": "Grocery Savings",
                            "Balance"    : balance / 100,
                        };

                        if (
                            typeof (balances[reward].detail.gasPoints[0].balance) !='undefined'
                        ) {
                            savings['ExpiringBalance'] = balances[reward].detail.gasPoints[0].balance;
                        }
                        // Expiration Date
                        if (
                            typeof (balances[reward].detail.gasPoints[0].expirationDate) !='undefined'
                        ) {
                            let dateStr = new Date(balances[reward].detail.gasPoints[0].expirationDate);
                            let unixTime = dateStr / 1000;
                            if (!isNaN(unixTime)) {
                                browserAPI.log("Expiration Date: " + dateStr + " Unixtime: " + unixTime);
                                savings['ExpirationDate'] = unixTime;
                            }
                        }

                        subAccounts.push(savings);
                        browserAPI.log(JSON.stringify(subAccounts));
                    }// if ($name === "Flex Grocery Dollars")
                }// for (const reward in balances)

                params.data.properties.SubAccounts = subAccounts;
                provider.saveTemp(params.data);
            },
            error: function (response) {
                browserAPI.log(`fail: balances data status = ${response.status}`);
                provider.complete();
            }
        });

        // A+ School Rewards
        $.ajax({
            type: 'GET',
            url: 'https://giantfood.com/apis/aplus/v1/designated/schools/' + data.Number,
            async: false,
            beforeSend: function(request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Content-Type', 'application/json');
            },
            dataType: 'json',
            cache: false,
            success: function (response) {
                browserAPI.log('success');
                browserAPI.log("---------------- schools data ----------------");
                if (response)
                    browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- schools data ----------------");

                let schools = [];
                let balance = 0;

                if (response && typeof (response.schools) != 'undefined') {
                    schools = response.schools;
                }

                for (const school in schools) {
                    if (!schools.hasOwnProperty(school)) {
                        browserAPI.log('skip wrong node');

                        continue;
                    }

                    if (schools[school]) {
                        $.ajax({
                            type: 'GET',
                            url: 'https://giantfood.com/apis/aplus/v1/school/details/' + schools[school],
                            async: false,
                            beforeSend: function(request) {
                                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                                request.setRequestHeader('Cache-Control', 'no-cache');
                                request.setRequestHeader('Pragma', 'no-cache');
                                request.setRequestHeader('Content-Type', 'application/json');
                            },
                            dataType: 'json',
                            cache: false,
                            success: function (response) {
                                browserAPI.log('success');
                                browserAPI.log("---------------- school/details data ----------------");
                                browserAPI.log(JSON.stringify(response));
                                browserAPI.log("---------------- school/details data ----------------");

                                if (typeof (response.yearToDateTotal) != 'undefined' && response.yearToDateTotal) {
                                    balance = balance + response.yearToDateTotal;
                                }
                            },
                            error: function (response) {
                                browserAPI.log(`fail: school/details data status = ${response.status}`);
                            }
                        });
                    }
                }

                if (balance) {
                    params.data.properties.SubAccounts.push({
                        "Code"          : 'stopshopASchoolRewards',
                        "DisplayName"   : "A+ School Rewards",
                        "Balance"       : balance,
                    });

                    browserAPI.log(JSON.stringify(subAccounts));
                }// if (balance)
                provider.saveTemp(params.data);
            },
            error: function (response) {
                browserAPI.log(`fail: schools data status = ${response.status}`);
            }
        });

        // Gas Rewards
        $.ajax({
            type: 'GET',
            url: 'https://giantfood.com/apis/balances/program/v1/gas/points/' + data.Number + '?details=true&storeNumber=' + storeNumber,
            async: false,
            beforeSend: function(request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Content-Type', 'application/json');
            },
            dataType: 'json',
            cache: false,
            success: function (response) {
                browserAPI.log('success');
                browserAPI.log("---------------- gas/points data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- gas/points data ----------------");

                if (!response || typeof (response.calculatedRate) == 'undefined' || !response.calculatedRate) {
                    browserAPI.log("[Gas Savings]: do not collect zero balance");

                    return;
                }

                let gasSavings = {
                    "Code"          : 'stopshopGasSavings',
                    "DisplayName"   : "Gas Savings",
                    "Balance"       : response.calculatedRate,
                }

                if (typeof (response.gasPoints) != 'undefined') {
                    gasSavings["ExpiringBalance"] = response.gasPoints[0].balanceToExpire;
                    gasSavings["ExpirationDate"] = new Date(response.gasPoints[0].expirationDate + " UTC") / 1000;
                }

                params.data.properties.SubAccounts.push(gasSavings);
            },
            error: function (response) {
                browserAPI.log(`fail: gas/points data status = ${response.status}`);
            }
        });

        provider.saveTemp(params.data);
        params.account.properties = params.data.properties;
        // console.log(params.account.properties);//todo
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

};