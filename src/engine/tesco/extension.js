var plugin = {
    hideOnStart: true,//todo
    // keepTabOpen: true,//todo
    hosts: {'www.tesco.com': true, 'secure.tesco.com': true, 'secure.tesco.ie': true, 'www.tesco.ie': true},
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),
    // alwaysSendLogs: true,//todo:
    clearCache:true,

    getStartingUrl: function (params) {
        if (params.account.login3 === 'Ireland')
            return "https://secure.tesco.ie/Clubcard/MyAccount/Home.aspx";

        /*if (provider.isMobile) {
            //return "https://secure.tesco.com/account/en-GB/logout?from=https://secure.tesco.com/clubcard/myaccount/home/home";
            return 'https://www.tesco.com/account/login/en-GB/logout?from=https://secure.tesco.com/clubcard';
        }*/

        return "https://secure.tesco.com/account/en-GB/login?from=https://secure.tesco.com/clubcard/myaccount/home/home";
    },

    getFocusTab: function(account, params){
        return true;
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        browserAPI.log('Current URL: ' + document.location.href);

        if (document.location.href === 'https://www.tesco.com/') {
            plugin.loadLoginForm(params);
            return;
        }
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params.account);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loadAccount(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.logBody("lastPage");
                let errors = $('p:contains("If you are seeing this page it is because you need to be in the UK to access your Clubcard information."):visible, p:contains("If you are seeing this page it is because your browser has failed some security checks"):visible');
                // We've been notified about this issue please try and sign in again later. We apologise for any inconvenience caused.
                if (errors.length === 0)
                    errors = $('p:contains("We\'ve been notified about this issue please try and sign in again later."):visible');
                if (errors.length > 0) {
                    provider.setError([errors.text(), util.errorCodes.providerError], true);
                    return;
                }

                if (params.account.login3 !== 'Ireland') {
                    if (
                        $('h2:contains("Web page not available"), p:contains("... we couldn\'t find the page you requested."), title:contains("Error")').length
                        || $('h1:contains("503 Service Temporarily Unavailable"):visible').length
                    ) {
                        provider.setError(util.errorMessages.providerErrorMessage, true);
                        return false;
                    }// if ($('h2:contains("Web page not available")').length)
                }// if (params.account.login3 !== 'Ireland')

                // retries
                browserAPI.log(">>> retry");
                var retry = $.cookie("tesco.com_retry_" + params.account.login);
                browserAPI.log(">>> retry number: " + retry);
                browserAPI.log('document.cookie = ' + document.cookie);
                if ((retry === null || typeof(retry) === 'undefined') || retry < 3) {
                    if (retry === null || typeof(retry) === 'undefined')
                        retry = 0;
                    provider.logBody("lastPage-" + retry);
                    browserAPI.log(">>> Retry: " + retry);
                    retry++;
                    $.cookie("tesco.com_retry_" + params.account.login, retry, { expires: 0.01, path:'/', domain: '.tesco.com', secure: true });
                    provider.setNextStep('start', function() {
                        document.location.href = plugin.getStartingUrl(params);
                    });
                    return;
                }// if (retry == null || retry < 3)

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    isLoggedIn: function (account) {
        browserAPI.log("isLoggedIn");
        if ($('#liSignout').length > 0 || $('div:contains("Your current total") + div:eq(0):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if (
            $('form#fSignin').length > 0
            || $('form[action *= "/account/en-GB/login?from"]').length > 0
            || $('form:has(input[name = "email"]):visible').length > 0
            || $('#appbar.sign-in, #liSignin').length > 0
        ) {
            browserAPI.log('not logged in');
            return false;
        }

        if (account.login3 === 'Ireland') {
            if ($('a:contains("Log out"), a:contains("Sign out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
        }// if (params.login3 === 'Ireland')

        return null;
    },

    isSameAccount: function (account) {
        return false;//todo
        // user has several accounts under the same name
        const name = util.filter(util.findRegExp( $('span:contains("Hello"):eq(0)').text() , /Hello\s*([^<]+)/i));
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name === account.properties.Name));
    },

    logout: function (params) {
        browserAPI.log("logout");
        if (params.account.login3 === 'Ireland') {
            provider.setNextStep('logoutConfirmation', function () {
                document.location.href = 'https://secure.tesco.ie/register/signout.aspx';
            });
        }
        else {
            provider.setNextStep('loadLoginForm', function () {
                document.location.href = 'https://secure.tesco.com/account/en-GB/logout';
            });
        }
    },

    logoutConfirmation: function () {
        browserAPI.log("logoutConfirmation");
        browserAPI.log("[Current URL] -> " + document.location.href);
        provider.setNextStep('loadLoginForm', function() {
            $('#remember2').click();
            $('form#fMain').submit();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        browserAPI.log("[Current URL] -> " + document.location.href);
        setTimeout(function() {
            provider.setNextStep('start', function() {
                document.location.href = plugin.getStartingUrl(params);
            });
        }, 2000);
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#fSignin');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials 1");
            form.find('input[name = "loginID"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            return provider.setNextStep('checkLoginErrors', function() {
                form.find('input[name = "confirm-signin"]').click();
            });
        }

        form = $('form[action *= "/account/en-GB/login?from"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials 2");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            util.sendEvent(form.find('input[name = "username"]').get(0), 'input');
            util.sendEvent(form.find('input[name = "password"]').get(0), 'input');
            setTimeout(function() {
                provider.setNextStep('checkLoginErrors', function () {
                    var button = form.find('button[type = "submit"], button.ui-component__button');
                    button.get(0).click();
                });
            }, 2000);

            return;
        }

        form = $('form:has(input[name = "email"])');
        if (form.length > 0) {
            browserAPI.log("[react form]: submitting saved credentials");
            // reactjs
            provider.eval(
                "function triggerInput(enteredName, enteredValue) {\n" +
                "  const input = document.getElementById(enteredName);\n" +
                "  const lastValue = input.value;\n" +
                "  input.value = enteredValue;\n" +
                "  const event = new Event(\"input\", { bubbles: true });\n" +
                "  const tracker = input._valueTracker;\n" +
                "  if (tracker) {\n" +
                "    tracker.setValue(lastValue);\n" +
                "  }\n" +
                "  input.dispatchEvent(event);\n" +
                "}\n" +
                "triggerInput('email', '" + params.account.login + "');\n" +
                "triggerInput('password', '" + params.account.password + "');"
            );

            return provider.setNextStep('checkLoginErrors', function () {
                let button = form.find('button#signin-button');
                button.get(0).click();

                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }

        if ($('a[href *= "signout"]').length > 0) {
            browserAPI.log("something went wrong, try to logout one more time");
            plugin.logout(params);
            return;
        }

        browserAPI.log("[Current URL] -> " + document.location.href);
        provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('p:contains("Sorry the email and/or password you have entered has not been recognised."):visible');
        var errorCode = util.errorCodes.invalidPassword;
        if (errors.length == 0) {
            errors = $('p:contains("If you are seeing this page it is because you need to be in the UK to access your Clubcard information."):visible');
            if (errors.length == 0) {
                errors = $('p:contains("... we couldn\'t find the page you requested."):visible');
                if (errors.length > 0)
                    provider.setError(["We're sorry... we couldn't find the page you requested.", util.errorCodes.providerError], true);
            }
            if (errors.length > 0)
                errorCode = util.errorCodes.providerError;
        }

        // new form
        if (errors.length == 0)
            errors = $('p:contains("Unfortunately we do not recognise those details."):visible');
        if (errors.length == 0) {
            errors = $('h2.ui-component__notice__heading:contains("Your account is locked"):visible');
            if (errors.length > 0)
                errorCode = util.errorCodes.lockout;
        }

        if (errors.length === 0) {
            errors = $('p[class *= "__error-message"]:visible');
        }

        if (errors.length > 0) {
            provider.setError([errors.text(), errorCode], true);
            return;
        }

        // skip "Update your details"
        const remindLater = $('a[data-tracking="remind-later"]:visible');

        if (remindLater.length) {
            return provider.setNextStep('loadAccount', function () {
                remindLater.get(0).click();
            });
        }

        plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        if (params.autologin) {
            provider.complete();
            return;
        }
        // wait signout link
        var counter = 0;
        var loadAccount = setInterval(function () {
            var logout = $('#liSignout:visible');
            browserAPI.log("waiting... " + counter);
            if (logout.length > 0 || counter > 15) {
                clearInterval(loadAccount);
                provider.setNextStep('parse', function () {
                    document.location.href = 'https://secure.tesco.com/clubcard/myaccount/home/home';
                });
            }
            counter++;
        }, 500);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parsePage");
        if (plugin.clubcardSecurityVerification(params)) {
            provider.setNextStep('parseBalance', function () {
                $('input[id = "btnSbmtNumbers"], button[data-tracking="account verification:submit button"]').click();
            });
        }
        else {
            let data = {};
            data.properties = {};
            // Name
            let name = util.findRegExp( $('h1:contains("Hello")').text() , /Hello\s*([^<]+)/i);

            if (name) {
                browserAPI.log("Name: " + name);
                data.properties.Name = util.filter(name);
            }

            params.data = data;
            provider.saveTemp(params.data);

            if (document.location.href === 'https://secure.tesco.com/clubcard/mypoints') {
                plugin.parseBalance(params);
                return;
            }

            provider.setNextStep('preParseBalance', function () {
                // refs 11561#note-40
                if ($('#lblheader:contains("An error occurred")').length)
                    document.location.href = 'https://secure.tesco.com/Clubcard/MyAccount/Points/Home';
                else
                    document.location.href = 'https://secure.tesco.com/clubcard/mypoints';
            });
        }
    },

    validateEmail: function (email) {
        const re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(email);
    },

    clubcardSecurityVerification: function (params) {
        browserAPI.log("clubcardSecurityVerification");
        browserAPI.log("[Current URL] -> " + document.location.href);
        let firstDigit = util.findRegExp($('div#security1 > label, label[for *= "digit"]:eq(0)').text(), /(\d+)[a-z]*(?: digit|)/i);
        let secondDigit = util.findRegExp($('div#security1 > label, label[for *= "digit"]:eq(1)').text(), /(\d+)[a-z]*(?: digit|)/i);
        let thirdDigit = util.findRegExp($('div#security1 > label, label[for *= "digit"]:eq(2)').text(), /(\d+)[a-z]*(?: digit|)/i);
        let fourthDigit = util.findRegExp($('div#security1 > label, label[for *= "digit"]:eq(3)').text(), /(\d+)[a-z]*(?: digit|)/i);
        browserAPI.log("Digits: " + firstDigit + ", " + secondDigit + ", " + thirdDigit + ", " + fourthDigit);

        params.account.login2 = params.account.login2.replace(/\s/ig, '');
        if (!params.account.login2 || plugin.validateEmail(params.account.login2)) {
            provider.setError(["To update this Tesco Clubcard account you need to enter the whole number of your Tesco Clubcard. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", util.errorCodes.providerError], true);/*review*/
            return;
        }
        let errors = $('p:contains("If you are seeing this page it is because you need to be in the UK to access your Clubcard information."):visible');
        if (errors.length > 0)
            provider.setError([errors.text(), util.errorCodes.providerError], true);

        if (firstDigit && secondDigit && thirdDigit) {
            let firstSecureDigit = $('input[name = "txtfirstSecureDigit"], input[name *= "digit"]:eq(0)');
            firstSecureDigit.val(params.account.login2.charAt(firstDigit-1));
            util.sendEvent(firstSecureDigit.get(0), 'input');
            let secondSecureDigit = $('input[name = "txtsecondSecureDigit"], input[name *= "digit"]:eq(1)');
            secondSecureDigit.val(params.account.login2.charAt(secondDigit-1));
            util.sendEvent(secondSecureDigit.get(0), 'input');
            let thirdSecureDigit = $('input[name = "txtthirdSecureDigit"], input[name *= "digit"]:eq(2)');
            thirdSecureDigit.val(params.account.login2.charAt(thirdDigit-1));
            util.sendEvent(thirdSecureDigit.get(0), 'input');
            if (fourthDigit) {
                let fourthSecureDigit = $('input[name = "txtthirdSecureDigit"], input[name *= "digit"]:eq(3)');
                fourthSecureDigit.val(params.account.login2.charAt(fourthDigit-1));
                util.sendEvent(fourthSecureDigit.get(0), 'input');
            }
            return true;
        }// if (firstDigit && secondDigit && thirdDigit)

        return false;
    },

    preParseBalance: function (params) {
        browserAPI.log(">>> preParseBalance");
        let counter = 0;
        let waitBalance = setInterval(function () {
            let logout = $('span.balanceValue:visible');
            browserAPI.log("waiting balance... " + counter);
            if (logout.length > 0 || counter > 10) {
                clearInterval(waitBalance);
                plugin.parseBalance(params);
            }
            counter++;
        }, 500);
    },

    parseBalance: function (params) {
        browserAPI.log("parseBalance");
        provider.logBody("parseBalancePage");
        browserAPI.log("[Current URL] -> " + document.location.href);
        var data = {};
        data.properties = {};

        /*
        // Confirm your Clubcard details
        var confirm = $('p:contains("confirm your Clubcard details")');
        if (confirm.length > 0) {
            var message = "Tesco Clubcard website is asking you to confirm your Clubcard details, until you do so we would not be able to retrieve your account information.";
            provider.setError([message, util.errorCodes.providerError], true);
            return;
        }
        */
        // invalid card numbers
        var errors = $('p:contains("The details you have entered do not match an active Clubcard for this account. Please try again"):visible');
        if (errors.length > 0) {
            provider.setError([errors.text(), util.errorCodes.providerError], true);
            return;
        }

        // Name
        let name = util.findRegExp( $('h2:contains("Hello")').text() , /Hello\s*([^<]+)/i);
        if (!name) {
            name = util.findRegExp( $('h1:contains("Hello")').text() , /Hello\s*([^<]+)/i);
        }
        if (name) {
            browserAPI.log("Name: " + name);
            params.data.properties.Name = util.filter(name);
        } else
            browserAPI.log("Name is not found");
        // Balance - My current points total
        let balance = util.findRegExp( $('#pointsTotal').text() , /([\d\.\,]+)/i);
        browserAPI.log("Balance: " + balance);
        // account: 2958255
        let balancePts = util.findRegExp( $('td:contains("Current points total")').next('td').text() , /([\d\.\,]+)/i);
        browserAPI.log("Balance v.1 PTS: " + balancePts);
        if (!balancePts || balancePts.length === 0) {
            balancePts = util.findRegExp( $('div:contains("Your current total") + div:contains("pts")').text(), /([\d\.\,]+)pts/i);
            browserAPI.log("Balance v.2 PTS: " + balancePts);
        }
        if (!balancePts || balancePts.length === 0) {
            balancePts = util.filter( $('span.balanceValue').text());
            browserAPI.log("Balance v.3 PTS: " + balancePts);
        }
        if (balance && balance.length > 0) {
            browserAPI.log("Balance: " + balance);
            params.data.properties.Balance = balance;
        } else if (balancePts && balancePts.length > 0 && balancePts !== '.') {
            browserAPI.log("Balance from balancePts: " + balancePts);
            params.data.properties.Balance = balancePts;
        } else {
            browserAPI.log("Balance is not found");

            browserAPI.log("try ajax");
            $.ajax({
                async: false,
                type: 'GET',
                url: "https://secure.tesco.com/Clubcard/MyAccount/Home/app/points/a/api/summary",
                success: function (ajaxData) {
                    browserAPI.log('---------------- success data ----------------');
                    browserAPI.log(JSON.stringify(ajaxData));
                    browserAPI.log('---------------- success data ----------------');

                    params.data.properties.Balance = ajaxData.points;
                },
                error: function (ajaxData) {
                    browserAPI.log('Failed to parse itinerary');
                    browserAPI.log('---------------- fail data ----------------');
                    browserAPI.log(JSON.stringify(ajaxData));
                    browserAPI.log('---------------- fail data ----------------');
                }
            });
        }

        if (params.data.properties.Balance === '.') {
            browserAPI.log("remove wrong Balance");
            delete params.data.properties.Balance;
        }

        // Balance Equivalent
        var subAccounts = [];
        var equivalent = util.findRegExp( $('td:contains("Equivalent ") + td').text() , /([\d\.\,]+)/i);
        var displayName = util.findRegExp( $('td:contains("Equivalent ")').text() , /([^\:]+)/i);
        if (equivalent && displayName) {
            subAccounts.push({
                "Code": 'tescoEquivalent',
                "DisplayName": displayName,
                "Balance": equivalent
            });
            console.log(subAccounts);
        }

        params.data.properties.SubAccounts = subAccounts;
        provider.saveTemp(params.data);
        // Vouchers to spend now
        provider.setNextStep('selectClubcardSecurityVerification', function () {
            var vouchers = $('a:contains("Vouchers"), a[data-bdd-selector="vouchers:view-all"]');
            if (vouchers.length > 0)
                vouchers.get(0).click();
            // document.location.href = 'https://secure.tesco.com/Clubcard/MyAccount/Vouchers/Home.aspx';
        });
    },

    selectClubcardSecurityVerification: function (params) {
        browserAPI.log("selectClubcardSecurityVerification");
        provider.logBody("selectClubcardSecurityVerificationPage");
        browserAPI.log("[Current URL] -> " + document.location.href);
        let select = $('label:contains("Clubcard verification"), button[data-tracking="verify with clubcard"], button:contains("Verify with Clubcard")');
        if (select.length > 0) {
            provider.setNextStep('vouchers', function () {
                select.click();
                $('button#continue').click();
            });
            return;
        }
        // refs #21335
        select = $('#send-clubcard-button');
        if (select.length > 0) {
            provider.setNextStep('vouchers', function () {
                select.click();
            });
            return;
        }

        plugin.vouchers(params);
    },

    vouchers: function (params) {
        browserAPI.log("vouchers");
        provider.logBody("vouchersPage");
        browserAPI.log("[Current URL] -> " + document.location.href);
        if (plugin.clubcardSecurityVerification(params)) {
            if (!provider.isMobile) {
                provider.setNextStep('waitVouchers', function () {
                    $('input[id = "btnSbmtNumbers"], button[data-tracking="account verification:submit button"]').click();
                    plugin.waitVouchers(params);
                });
            } else {
                $('#account-verification').prop('action', '/Clubcard/MyAccount/Account/SecurityHome');
                provider.setNextStep('openVouchers', function () {
                    $('#account-verification, form:has(input[name *= "digit"])').submit();
                });
            }
        }
        else
            plugin.waitVouchers(params);
    },

    waitVouchers: function (params) {
        browserAPI.log("waitVouchers");
        browserAPI.log("[Current URL] -> " + document.location.href);
        let counter = 0;
        const start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            const balanceVoucher = $('td#ltrtotalUnusedVouchers, td:contains("Available to spend") + td').text();
            const balanceVoucherNew = $('div#lwActive div[class *= "__VoucherWrapper"]');
            if (balanceVoucher.length > 0 || balanceVoucherNew.length > 0 || counter > 10) {
                clearInterval(start);
                plugin.parseVouchers(params);
            }// if (balanceVoucher.length > 0 || counter > 10)
            counter++;
        }, 500);
    },

    openVouchers: function(params) {
        browserAPI.log("openVouchers");
        plugin.waitVouchers(params);
        /*
        $.get('https://secure.tesco.com/clubcard/myaccount/vouchers/home', function (data) {
            document.body.innerHTML = data;
            plugin.parseVouchers(params);
        });
         */
    },

    parseVouchers: function (params) {
        browserAPI.log("parseVouchers");
        provider.logBody("parseVouchersPage");
        // Vouchers to spend now
        const balanceVoucher = util.findRegExp( $('td#ltrtotalUnusedVouchers, td:contains("Available to spend") + td, div#lwVouchersAvailable div[class *= "__TableWrapper"]').text() , /([\d\.\,]+)/i);
        if (balanceVoucher) {
            params.data.properties.SubAccounts.push({
                "Code": 'tescoVouchers',
                "DisplayName": "Value in Clubcard vouchers",
                "Balance": balanceVoucher
            });
            browserAPI.log('>> ' + JSON.stringify(params.data.properties.SubAccounts));
            // console.log(params.data.properties.SubAccounts);
        } else {
            browserAPI.log("Value in Clubcard vouchers not found");
        }
        // Vouchers // refs #7879
        let subAccounts = [];
        const vouchers = $('div#div_UnusedVoucherSummary tr:has(td#lblVouchersExpirydate), div#vouchersAvailable tr:has(td[data-label="Expiry date"]), div#lwActive div[class *= "__VoucherWrapper"]');
        browserAPI.log('Total ' + vouchers.length + ' vouchers were found');
        vouchers.each(function () {
            const code = util.trim($('td#lblVoucherslist, td[data-label="Online code"], div[class *= "BarcodeContainer"]', $(this)).text());
            browserAPI.log("[Code]: " + code);
            const displayName = 'Voucher # ' + code;
            const balance = util.findRegExp( $('td#lblVoucherlists, td[data-label="Value"], p[class *= "__ActiveVouchersDesc"], , span[class *= "__ActiveVouchersDesc"]', $(this)).text(), /([\d\,\.\s\-]+)/i);
            browserAPI.log("[Balance]: " + balance);
            let exp = util.trim($('td#lblVouchersExpirydate, td[data-label="Expiry date"]', $(this)).text());
            browserAPI.log("[Exp]: " + exp);
            if (exp) {
                exp = util.modifyDateFormat(exp, '/');
            } else {
                exp = util.findRegExp($('span[class *= "__ExpiryLabel"]', $(this)).text(), /Expires\s*(.+)/i);
                browserAPI.log("[Exp]: " + exp);
            }
            exp = new Date(exp + ' UTC');
            var unixtime =  exp / 1000;
            if (!isNaN(exp) && code) {
                params.data.properties.SubAccounts.push({
                    "Code" : 'tescoVoucher' + code,
                    "DisplayName" : displayName,
                    "Balance" : balance,
                    "ExpirationDate" : unixtime
                });
            } else if (code) {
                params.data.properties.SubAccounts.push({
                    "Code" : 'tescoVoucher' + code,
                    "DisplayName" : displayName,
                    "Balance" : balance
                });
            }
        });

        params.data.properties.CombineSubAccounts = 'false';
        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        browserAPI.log('>> ' + JSON.stringify(params.account.properties));
        provider.complete();
    }

};