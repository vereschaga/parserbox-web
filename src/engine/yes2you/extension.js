var plugin = {
    //keepTabOpen: true,
    hosts: {'www.kohls.com': true, },

    getStartingUrl: function (params) {
        return 'https://www.kohls.com/myaccount/dashboard.jsp';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
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
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#signin-email').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#baseRewardsLink:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('#baseRewardsLink:visible').text(), /(\w+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            setTimeout(function () {
                $('#button-baseLogOut').get(0).click();
            }, 1000);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form[data-testid="SignInWithEmailForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            /*form.find('input[name = "email"]').val(params.account.login);
            util.sendEvent(form.find('input[name = "email"]').get(0), 'click');
            util.sendEvent(form.find('input[name = "email"]').get(0), 'input');
            util.sendEvent(form.find('input[name = "email"]').get(0), 'change');
            util.sendEvent(form.find('input[name = "email"]').get(0), 'blur');*/


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
                "triggerInput('input[name = \"email\"]', '" + params.account.login + "');"
            );

            setTimeout(function () {
                provider.setNextStep('loginPassword', function () {
                    form.find('button[type="submit"]:contains("Continue")').get(0).click();
                    setTimeout(function () {
                        browserAPI.log("start loginPassword");

                        plugin.loginPassword(params);
                    }, 3000);
                });
            }, 1000);
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    loginPassword: function (params) {
        browserAPI.log("loginPassword");
        let form = $('form[data-testid="SignInWithPWForm"]');
        if (form.length > 0) {
            /*form.find('input[name = "signInPW"]').val(params.account.password);
            util.sendEvent(form.find('input[name = "signInPW"]').get(0), 'click');
            util.sendEvent(form.find('input[name = "signInPW"]').get(0), 'input');
            util.sendEvent(form.find('input[name = "signInPW"]').get(0), 'change');
            util.sendEvent(form.find('input[name = "signInPW"]').get(0), 'blur');*/
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
                "triggerInput('input[name = \"signInPW\"]', '" + params.account.password + "');"
            );
            setTimeout(function () {
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[type="submit"]:contains("Sign In")').get(0).click();
                });
            }, 1000);
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#errorCopy');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.logBody("loginCompletePage");
        if (params.autologin) {
            provider.complete();
            return;
        }
        browserAPI.log("Parse account");
        plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);
        // Rewards ID
        let number = util.findRegExp( $('#baseRewardsLink:visible').text(), /(\w+)/);
        if (!number) {
            return;
        }
        var data = {};
        browserAPI.log("number: " + number);
        data.AccountNumber = number;

        plugin.ajaxRequest('https://www.kohls.com/myaccount/json/myinfo/customer_info_details_json.jsp',
            'GET', null, false, function (response) {
                let name = util.beautifulName(response.payload.profile.customerName.firstName + ' ' + response.payload.profile.customerName.lastName);
                browserAPI.log("Name: " + name);
                data.Name = name;
                let MemberSince =  new Date (response.payload.profile.createdTimestamp).getFullYear();
                browserAPI.log("MemberSince: " + MemberSince);
                data.MemberSince = MemberSince;
            }, function (response) {
                browserAPI.log('Parse Props error: ' + response);
            });

        plugin.ajaxRequest('https://www.kohls.com/myaccount/json/rewrads/getRewardsTrackerJson.jsp',
            'POST', null, false, function (response) {
                // Balance - Kohl's Rewards Balance
                browserAPI.log("Balance: " + response.existingEarnTrackerBal);
                data.Balance = response.existingEarnTrackerBal;
                // PointsToNextReward - Spend $... to earn your next $5 in Kohl's Rewards.
                if (response.existingEarnTrackerBal && response.earnTrackerThreshold) {
                    let pointsToNextReward = response.earnTrackerThreshold - response.existingEarnTrackerBal;
                    browserAPI.log("PointsToNextReward: " + pointsToNextReward);
                    data.PointsToNextReward = '$' + pointsToNextReward;
                }
            }, function (response) {
                browserAPI.log('Parse Props error: ' + response);
            });


        var subAccounts = [];
        plugin.ajaxRequest('https://www.kohls.com/wallet/json/wallet_json.jsp',
            'GET', null, false, function (response) {
                if (response.payload.kohlsCashBalance) {
                    subAccounts.push({
                        "Code"        : 'yes2youCash',
                        "DisplayName" : "Kohl’s Cash",
                        "Balance"     : response.payload.kohlsCashBalance,
                    });
                }
            }, function (response) {
                browserAPI.log('Parse Props error: ' + response);
            });


        // Offers
        plugin.ajaxRequest('https://www.kohls.com/myaccount/json/dashboard/walletOcpPanelJson.jsp',
            'POST', {'nds-pmd': ''}, false, function (response) {
                let offers = response.response.offers;
                if (!offers || typeof response.response === 'undefined' || typeof response.response.offers === 'undefined') {
                    browserAPI.log('response.response.offers');
                    return;
                }
                offers.forEach(function (offer) {
                    if (offer.status !== 'ACTIVE') {
                        return false;
                    }
                    if (offer.endDate) {
                        subAccounts.push({
                            "Code": 'yes2youCoupons' + offer.eventName + offer.barcode,
                            "DisplayName": offer.description,
                            "Balance": null,
                            "PromoCode": offer.eventName,
                            'BarCode': offer.barcode,
                            "BarCodeType": 'code128',
                            "ExpirationDate": offer.endDate / 1000,
                        });
                    }
                });

            }, function (response) {
                browserAPI.log('Parse Props error: ' + response);
            });

        params.account.properties = data;
        params.account.properties.SubAccounts = subAccounts;
        params.account.properties.CombineSubAccounts = 'false';
        // console.log(params.account.properties);// TODO
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    ajaxRequest: function (url = '', method = 'POST', data = null, withCredentials = true, callback, callbackError) {
        browserAPI.log('plugin.ajax');
        browserAPI.log(url + ' => ' + method + ' ' + (data ? JSON.stringify(data) : null));
        return $.ajax({
            url: url,
            type: method,
            beforeSend: function (request) {
                request.setRequestHeader('Accept', '*/*');
                request.setRequestHeader('Content-Type', 'application/json');
                request.setRequestHeader('x-requested-with', 'XMLHttpRequest');

            },
            data: (data ? JSON.stringify(data) : null),
            crossDomain: true,
            dataType: 'json',
            cache: false,
            async: false,
            xhrFields: {
                withCredentials: withCredentials
            },
            success: function (response) {
                if (response) {
                    callback(response);
                } else {
                    browserAPI.log('ajax error, response null');
                    callbackError(response);
                }
            },
            error: function (xhr, status) {
                browserAPI.log('ajax error' + xhr.responseText);
                callbackError(xhr.responseText);
            }
        });
    },
}