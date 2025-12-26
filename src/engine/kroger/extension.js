var plugin = {
    //hideOnStart: true,//todo
    //keepTabOpen: true,

    hosts: {
        "www.bakersplus.com": true,
        "www.citymarket.com": true,
        "www.dillons.com": true,
        "www.food4less.com": true,
        "www.foodsco.net": true,
        "www.fredmeyer.com": true,
        "www.frysfood.com": true,
        "www.gerbes.com": true,
        "www.jaycfoods.com": true,
        "www.kingsoopers.com": true,
        "www.kroger.com": true,
        "www.kwikshop.com": true,
        "www.loafnjug.com": true,
        "www.owensmarket.com": true,
        "www.pay-less.com": true,
        "www.ralphs.com": true,
        "www.smithsfoodanddrug.com": true,
        "www.tomt.com": true,
        "www.turkeyhillstores.com": true,
        "www.qfc.com": true,
        "www.quikstop.com": true,
        "www.harristeeter.com": true,
        "login.kroger.com": true,
    },

    getDomain: function (account) {
        let  domain = "kroger.com";

        if (account.login2 !== '') {
            domain = account.login2;
        }

        return domain;
    },

    getStartingUrl: function (params) {
        const domain = plugin.getDomain(params.account);

        return "https://www." + domain + "/account/update";
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        if (provider.isMobile) {
            provider.setNextStep('startAuth', function () {
                setTimeout(function () {
                    document.location.reload();
                }, 2000)
            });
        }
        else
            plugin.startAuth(params);
    },

    startAuth: function (params) {
        browserAPI.log("startAuth");
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
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.logBody("lastPage");
                provider.setError(['Can\'t determine login state [Brand: ' + plugin.getDomain(params.account) + ']', util.errorCodes.engineError]);
                return;
            }// if (isLoggedIn === null && counter > 10)

            // A Verification Link has been sent to jakel5564@gmail.com.
            if ($('main > div > #ConfirmEmail-sendEmail:visible').length) {
                provider.setError([`It seems that ${plugin.getDomain(params.account)} needs to verify your email before you can update this account. Please follow the instructions on the new tab (the one that shows your ${plugin.getDomain(params.account)} authentication options) to get your email verified and then please try to update this account again.`, util.errorCodes.providerError], true);
                return;
            }
            counter++;
        }, 1000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");

        if ($('form#signInForm:visible').length > 0
            || $('form#SignIn-form:visible').length > 0
            || $('form#localAccountForm:visible').length > 0
            || $('form[ng-submit="vm.getUserEmail()"]:visible').length // harristeeter
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#WelcomeDesktop-welcome').length > 0
            || $('.Page-inner-block #MyAccount').length > 0
            || $('div[data-qa="Email Address-value"]:visible').length > 0
            || (provider.isMobile && $('label#email').length > 0)
            || $('a[ng-click="vm.changeEmail()"]:visible').length // harristeeter
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const email = $('[data-qa="Email Address-value"]:visible, [data-qa="Current Email: -value"]:visible').text();
        browserAPI.log("email: " + email);
        return ((typeof(account.login) != 'undefined')
            && email
            && (email === account.login));
    },

    logout: function (params) {
        browserAPI.log("logout");
        const domain = plugin.getDomain(params.account);

        provider.setNextStep('loadLoginForm', function () {
            document.location.href = "https://www." + domain + "/auth/api/sign-out";
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form#SignIn-form:visible, form#localAccountForm:visible');

        if (form.length === 0) {
            provider.setError(['Login form not found [Brand: ' + plugin.getDomain(params.account) + ']', util.errorCodes.engineError]);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('#SignIn-emailInput').val(params.account.login);
        // form.find('#SignIn-passwordInput').val(params.account.password);
        // reactjs
        provider.eval(
            "function triggerInput(selector, enteredValue) {\n" +
            "      let input = document.querySelector(selector);\n" +
            "      input.dispatchEvent(new Event('focus'));\n" +
            "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
            "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
            "      nativeInputValueSetter.call(input, enteredValue);\n" +
            "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
            "      input.dispatchEvent(inputEvent);\n" +
            "}\n" +
            "triggerInput('input[id = \"SignIn-emailInput\"], input[id = \"loginFormEmailInput\"], #signInName', '" + params.account.login + "');\n" +
            "triggerInput('input[id = \"SignIn-passwordInput\"], input[id = \"loginFormPasswordInput\"], #password', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            provider.eval('document.querySelector(\'#SignIn-submitButton, #next, #continue\').click();');
            /*
            form.find('#SignIn-submitButton').get(0).click();
            setTimeout(function () {
                if ($(`#SignIn-errorMessage:contains("We're having trouble with sign in right now. Please disable any pop up or ad blockers and try again")`).length > 0) {
                    form.find('#SignIn-submitButton').get(0).click();
                }
            }, 1000);
            */
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        provider.logBody("checkLoginErrorsPage");

        const errors = $('div#SignIn-errorMessage:visible, .kds-Message-content:visible');

        if (errors.length > 0 && util.filter(errors.eq(0).text()) !== '') {
            const message = util.filter(errors.eq(0).text());
            browserAPI.log("[Error]: '" + message + "'");

            if (
                message.indexOf('The email or password entered is incorrect.') !== -1
                || message.indexOf("We're updating our experience to better serve you. If you're having trouble logging in") !== -1
            ) {
                provider.setError(util.filter(errors.text()), true);
                return;
            }

            if (message.indexOf("Please reset your password to keep your account secure. We've sent you an email with instructions.") !== -1) {
                provider.setError([message, util.errorCodes.lockout], true);
                return;
            }

            if (
                message.indexOf("We're sorry, an unexpected error occurred. Please try signing in again.") !== -1
                || message.indexOf("We're having trouble with sign in right now. Please disable any pop up or ad blockers and try again.") !== -1
            ) {
                provider.setError([message, util.errorCodes.providerError], true);
                return;
            }

            // this is not errork
            if (message.indexOf("There are no Alt IDs linked to this Plus Card") === -1) {
                provider.complete();
                return;
            }
        }

        /*if (provider.isMobile) {
            provider.setNextStep('loginComplete', function () {
                document.location.reload();
            });
            return;
        }*/

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (params.autologin) {
            provider.complete();
            return;
        }

        util.waitFor({
            timeout: 10,
            selector: $('h1:contains("Profile Information"):visible'),
            success: function() {
                plugin.parse(params);
            },
            fail: function() {
                browserAPI.log("Failed load 'Profile Information'");
            }
        });
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("profilePage");
        provider.updateAccountMessage();
        let data = {};

        const domain = plugin.getDomain(params.account);

        $.ajax({
            url       : "https://www." + domain + "/atlas/v1/customer-profile/v1/profile?projections=customerProfile.full",
            async     : false,
            type      : 'GET',
            beforeSend: function (request) {
                request.setRequestHeader("Accept", 'application/json, text/plain, */*');
                request.setRequestHeader("x-kroger-channel", 'WEB');
            },
            dataType  : 'json',
            success   : function (responseProfile) {
                browserAPI.log("---------------- profile data ----------------");
                browserAPI.log(JSON.stringify(responseProfile));
                browserAPI.log("---------------- profile data ----------------");

                // Rewards Card Number
                if (typeof (responseProfile.data.profile.loyaltyCardNumber) != 'undefined') {
                    data.Number = responseProfile.data.profile.loyaltyCardNumber;
                    browserAPI.log("Number: " + data.Number);
                } else
                    browserAPI.log(">>> Number not found");

                // Name
                if (
                    typeof (responseProfile.data.profile.firstName) != 'undefined'
                    && typeof (responseProfile.data.profile.lastName) != 'undefined'
                    && (responseProfile.data.profile.firstName != null || responseProfile.data.profile.lastName != null)
                ) {
                    data.Name = util.beautifulName(responseProfile.data.profile.firstName + " " + responseProfile.data.profile.lastName);
                    browserAPI.log("Name: " + data.Name);
                } else
                    browserAPI.log(">>> Name not found");
                if (
                    typeof (responseProfile.data.profile.bannerSpecificDetails) != 'undefined'
                    && typeof (responseProfile.data.profile.bannerSpecificDetails[0]) != 'undefined'
                    && typeof (responseProfile.data.profile.bannerSpecificDetails[0].preferredStore) != 'undefined'
                    && typeof (responseProfile.data.profile.bannerSpecificDetails[0].preferredStore.locationId) != 'undefined'
                ) {
                    $.ajax({
                        url: "https://www." + domain + "/atlas/v1/stores/v1/details/" + responseProfile.data.profile.bannerSpecificDetails[0].preferredStore.locationId,
                        async     : false,
                        type      : 'GET',
                        beforeSend: function (request) {
                            request.setRequestHeader("Accept", 'application/json, text/plain, */*');
                            request.setRequestHeader("x-kroger-channel", 'WEB');
                        },
                        dataType  : 'json',
                        success   : function (responseStore) {
                            browserAPI.log("---------------- store data ----------------");
                            browserAPI.log(JSON.stringify(responseStore));
                            browserAPI.log("---------------- store data ----------------");
                            // Preferred Store
                            if (typeof (responseStore.data.storeDetails.address.address.addressLines) != 'undefined') {
                                let address = responseStore.data.storeDetails.address.address;
                                data.PreferredStore = address.addressLines[0] + ', ' + address.name + ', ' + address.stateProvince + ' ' + address.postalCode;
                                browserAPI.log("PreferredStore: " + data.PreferredStore);
                            } else
                                browserAPI.log(">>> PreferredStore not found");
                        },
                        error     : function (data) {
                            browserAPI.log("fail: store data");
                            data = $(data);
                            browserAPI.log("---------------- fail data ----------------");
                            browserAPI.log(JSON.stringify(data));
                            browserAPI.log("---------------- fail data ----------------");
                        }
                    });
                }
            },
            error: function (data) {
                browserAPI.log("fail: profile");
                data = $(data);
                browserAPI.log("---------------- fail data ----------------");
                browserAPI.log(JSON.stringify(data));
                browserAPI.log("---------------- fail data ----------------");
            }
        });

        // SubAccounts
        data.SubAccounts = [];

        $.ajax({
            url       : "https://www." + domain + "/accountmanagement/api/points-summary",
            async     : false,
            type      : 'GET',
            beforeSend: function (request) {
                request.setRequestHeader("Accept", 'application/json, text/plain, */*');
                request.setRequestHeader("Content-Type", 'application/json;charset=UTF-8');
                request.setRequestHeader("sec_req_type", 'ajax');
            },
            dataType  : 'json',
            success   : function (response) {
                browserAPI.log("---------------- points-summary data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- points-summary data ----------------");

                if (response) {
                    let fuelBalance = null;

                    for (let subAcc in response) {
                        if (!response.hasOwnProperty(subAcc)) {
                            continue;
                        }

                        let points = response[subAcc].programBalance.balanceDescription;
                        let title = response[subAcc].programDisplayInfo.loyaltyProgramName;
                        let titleBalance = ['Your Year-to-Date Plus Card Savings', 'Annual Savings', 'Annual Advantage Card Savings', 'Year-to-Date V.I.P. Card Savings', 'Annual rewards Card Savings'];

                        if ($.inArray(title, titleBalance) !== -1) {
                            // Balance - Annual Savings
                            data.AnnualSavings = points;
                            browserAPI.log("Annual Savings: " + data.AnnualSavings);
                            browserAPI.log('set Balance NA, Annual Savings were found');
                            data.Balance = 'null';

                            continue;
                        }

                        points = points.replace(/[^\d\-.,]/g, '') * 1;

                        if (
                            title.indexOf('Fuel Points') !== -1
                            || title.indexOf('Fuel Program') !== -1
                        ) {
                            if (fuelBalance === null) {
                                fuelBalance = 0;
                            }

                            fuelBalance = fuelBalance + points;
                        }// if (strstr($title, 'Fuel Points'))
                        else if (points === 0) {
                            browserAPI.log("Skip zero subaccount: " + title + " / " + points);
                            browserAPI.log('set Balance NA');
                            data.Balance = 'null';

                            continue;
                        }// else if (points === 0)

                        let sub = {
                            'Code'       : params.account.login2 + title.replace(/\s*/ig, '').replace(/\'/ig, ''),
                            'DisplayName': title,
                            'Balance'    : points,
                        };

                        if (title.indexOf('Fuel Points') !== -1) {
                            sub.BalanceInTotalSum = true;
                        }

                        let expiration = response[subAcc].programDisplayInfo.redemptionEndDate.replace('/T.+/ig', '');
                        let unixtime = new Date(expiration) / 1000;

                        if (!isNaN(unixtime)) {
                            sub.ExpirationDate = unixtime;
                        }

                        data.SubAccounts.push(sub);
                    }// for (let subAcc in response)

                    if (data.SubAccounts.length > 0) {
                        // TODO: temporarily fix, remove it in  2014
                        // "ralphs.com" - 1406283, 1124792
                        // "dillons.com" - 1255118
                        // if (ErrorCode == ENGINE_ERROR) {
                        //     data.Balance = 'null';
                        // }

                        // refs #14490
                        if (fuelBalance !== null) {
                            data.Balance = fuelBalance;
                            browserAPI.log('set Balance: ' + data.Balance);
                            data.Currency = "points";
                        }
                    }// if (data.SubAccounts.length > 0)
                }// if (is_array($response) && !empty($response))
                else if (
                    typeof (response.hasErrors) != 'undefined'
                    && response.hasErrors === true
                ) {
                    if (
                        typeof (response.errorCode) != 'undefined'
                        && response.errorCode === 'BannerProfileNotFound'
                    ) {
                        provider.setWarning("We are not able to display your Points Summary at this time, either because you do not have a preferred store selected or you do not have a Plus Card on file. Please update your Account Summary in order to view your points.");
                    }
                    // Please add a Plus Card to view your points.
                    if (
                        typeof (response.errorCode) != 'undefined'
                        && response.errorCode === 'UserDoesNotHaveACard'
                    ) {
                        provider.setError(["Please add a Plus Card to view your points.", util.errorCodes.providerError], true);
                        return;
                    }
                    // We're sorry, we are currently experiencing technical difficulties. Please try again later.
                    if (
                        typeof (response.errorMessage) != 'undefined'
                        && response.errorMessage === 'We\'re sorry, we are currently experiencing technical difficulties. Please try again later.'
                    ) {
                        provider.setError(["We're sorry, we are currently experiencing technical difficulties. Please try again later.", util.errorCodes.providerError], true);
                    }
                } else {
                    // No Transactions were found for the select criteria [No program balances were found for the card holder]
                    if (util.findRegExp(JSON.stringify(response), "/^\[\]$/")) {
                        browserAPI.log('set Balance NA');
                        data.Balance = 'null';
                    }// refs #16164
                    // provider.setWarning("No Transactions were found for the select criteria [No program balances were found for the card holder]");
                    if (
                        $("h2:contains('Oops, we seem to have a bad link'):visible").length
                        && $("h1:contains('Error')").length
                    ) {
                        provider.setError(["We're sorry, we are currently experiencing technical difficulties. Please try again later.", util.errorCodes.providerError], true);
                    }
                    // We're experiencing technical difficulties
                    if ($("h3:contains('Our support staff has been notified and are actively working to restore service as soon as possible')").length > 0) {
                        provider.setError(["We're sorry, we are currently experiencing technical difficulties. Please try again later.", util.errorCodes.providerError], true);
                    }
                }
            },
            error: function (data) {
                browserAPI.log("fail: points-summary");
                data = $(data);
                browserAPI.log("---------------- fail data ----------------");
                browserAPI.log(JSON.stringify(data));
                browserAPI.log("---------------- fail data ----------------");
            }
        });

        params.data.properties = data;
        params.data.properties.CombineSubAccounts = 'false';
        // save data
        provider.saveTemp(params.data);

        provider.setNextStep('parseCoupons', function () {
            document.location.href = "https://www." + domain + "/coupons";
        });
    },

    // refs #15112
    parseCoupons: function (params)
    {
        browserAPI.log("Coupons");
        provider.updateAccountMessage();
        const domain = plugin.getDomain(params.account);

        $.ajax({
            url       : "https://www." + domain + "/cl/api/coupons/clippedCouponsCatalogue",
            async     : false,
            type      : 'GET',
            beforeSend: function (request) {
                request.setRequestHeader("Accept", 'application/json, text/plain, */*');
                request.setRequestHeader("Content-Type", 'application/json;charset=UTF-8');
                request.setRequestHeader("sec_req_type", 'ajax');
            },
            dataType  : 'json',
            success   : function (response) {
                browserAPI.log("---------------- coupons data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- coupons data ----------------");

                let coupons = [];

                if (typeof (response.data.couponData.coupons) != 'undefined') {
                    coupons = response.data.couponData.coupons;
                }

                if (typeof (response.data.couponData.count) != 'undefined') {
                    browserAPI.log("Total " + response.data.couponData.count + " coupons were found");
                }

                let allCoupons = [];

                for (let coupon in coupons) {
                    if (!coupons.hasOwnProperty(coupon)) {
                        continue;
                    }

                    let displayName = coupons[coupon].shortDescription;
                    let code = coupons[coupon].id;
                    let exp = coupons[coupon].expirationDate;
                    let savings = coupons[coupon].savings;
                    let subAccount = {
                        'Code'       : 'krogerCoupons' + params.account.login2 + code,
                        'DisplayName': displayName,
                        'Balance'    : (savings === '') ? null : savings,
                    };

                    let unixtime = new Date(exp) / 1000;
                    if (!isNaN(unixtime)) {
                        subAccount.ExpirationDate = unixtime;
                    }

                    allCoupons.push(subAccount);
                }// foreach ($coupons as $coupon)

                allCoupons.sort(( a, b ) => a.ExpirationDate > b.ExpirationDate);
                let hotCoupons = allCoupons.slice(0, 10);
                params.data.properties.SubAccounts = [...params.data.properties.SubAccounts, ...hotCoupons];
            },
            error: function (data) {
                browserAPI.log("fail: coupons data");
                data = $(data);
                browserAPI.log("---------------- fail data ----------------");
                browserAPI.log(JSON.stringify(data));
                browserAPI.log("---------------- fail data ----------------");
            }
        });

        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        provider.complete();
    },
};