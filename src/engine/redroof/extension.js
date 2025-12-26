var plugin = {
    //keepTabOpen: true,
    hideOnStart: true,
    hosts: {'www.redroof.com': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.redroof.com/members';
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
                        plugin.logout();
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("lastPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#floatingEmail:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign Out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.filter($('p:contains("Your Member Number:") + p strong').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Sign Out"):visible').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#floatingEmail').closest('form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // form.find('input[name = "email"]').val(params.account.login);
            // form.find('input[name = "password"]').val(params.account.password);
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
                "triggerInput('floatingEmail', '" + params.account.login + "');" +
                "triggerInput('floatingPassword', '" + params.account.password + "')"
            );
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button:contains("SIGN IN")').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 10000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        //We’re sorry, we encountered an error in processing your request. Please contact RediRewards Services at: 800-333-0991 and provide incident code 5689. Thank you.
        var errors = $('.module-info-box >.content:contains("re sorry, we encountered an error in processing your request. Please contact RediRewards Services at"):visible');
        if (errors.length > 0) {
            provider.setError([errors.text(), util.errorCodes.providerError], true);
            return;
        }

        errors = $('label.error-message:visible');
        if (errors.length === 0)
            errors = $('div.module-info-box > div.content:visible');
        if (errors.length > 0) {
            if (/We apologize\. An error has occurred\. Agents are standing by\. Please give us a call \@ 877-843-7663\./.test(errors.text())) {
                provider.setError([errors.text(), util.errorCodes.providerError], true);
                return;
            }
            provider.setError(errors.text(), true);
        }
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        /*if (params.autologin) {
            browserAPI.log("only autologin");
            provider.complete();
            return;
        }*/
        plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);

        let data = {};
        /*
        // Name
        let name = $('h3:contains("Personal Information")').parent().next('div').find('ul >li:eq(0)');
        if (name.length === 0) {
            name = $('a.users-name');
        }
        if (name.length > 0) {
            name = util.beautifulName(util.filter(name.text()));

            if (util.stristr(name, 'Hi ')) {
                name = util.findRegExp(name, /Hi\s*(.+)/i);
            }

            browserAPI.log("Name: " + name);
            data.Name = name;
        } else
            browserAPI.log("Name is not found");

        // Balance - Points Available
        let balance = util.findRegExp($('a.redirewards > span:contains("pts")').text(), /([\d.,]+) pts/i);
        if (balance && balance.length > 0) {
            browserAPI.log("Balance: " + balance);
            data.Balance = balance;
        } else {
            browserAPI.log("Balance is not found");
        }

        // Account Number
        let number = $('span:contains("Account Number:") + span');
        if (number && number.length > 0) {
            data.Number = util.filter(number.text());
            browserAPI.log("Number: " + data.Number);
        } else {
            browserAPI.log("Number is not found");
        }
        */

        // Expiration date // refs #3837, https://redmine.awardwallet.com/issues/3837#note-9
        $.ajax({
            url: 'https://prd-e-gwredroofwebapi.redroof.com/api/v1/member/get-profile-page',
            async: false,
            type: 'GET',
            xhrFields: {
                withCredentials: true
            },
            headers: {
                'Accept': '*/*',
                'Content-Type': 'application/json',
                'Origin': 'https://www.redroof.com',
                'Referer': 'https://www.redroof.com/'
            },
            contentType: "application/json",
            beforeSend: function(request) {},
            dataType: 'json',
            success: function (response) {
                // console.log("---------------- data ----------------");
                // console.log(transactions);
                // console.log("---------------- data ----------------");

                if (response.memberProfile !== null) {
                    // Balance - Points Available
                    if (typeof (response.memberProfile.pointsBalanceFormatted) != 'undefined') {
                        data.Balance = response.memberProfile.pointsBalanceFormatted;
                        browserAPI.log("Balance: " + data.Balance);
                    } else {
                        browserAPI.log("Balance is not found");
                    }
                    // Name
                    if (typeof (response.memberProfile.firstName) != 'undefined') {
                        data.Name = response.memberProfile.firstName + ' ' + response.memberProfile.lastName;
                        browserAPI.log("Name: " + data.Name);
                    } else {
                        browserAPI.log("Name is not found");
                    }
                    // Account Number
                    if (typeof (response.memberProfile.LoyaltyAccountNbr) != 'undefined') {
                        data.Number = response.memberProfile.LoyaltyAccountNbr;
                        browserAPI.log("Number: " + data.Number);
                    } else {
                        browserAPI.log("Number is not found");
                    }
                } else {
                    browserAPI.log("memberProfile is not found");
                }

                browserAPI.log("Parse Exp Date");
                let transactions = response.redicardActivity;
                if (typeof transactions !== 'undefined') {
                    for (let i in transactions) {
                        if (transactions.hasOwnProperty(i)) {
                            let t = transactions[i];
                            if (t.transactionType && t.pointsAmount && t.transactionType.toLowerCase() === 'stay' && t.pointsAmount > 0) {
                                // Last Activity
                                let lastActivity = t.date;
                                let exp = new Date(lastActivity + ' UTC');
                                exp.setMonth(exp.getMonth() + 14);
                                let unixtime = exp / 1000;
                                if (!isNaN(unixtime)) {
                                    browserAPI.log("ExpirationDate = lastActivity + 14 month");
                                    browserAPI.log("Expiration Date: " + lastActivity + " Unixtime: " + unixtime + " Date: " + exp);
                                    data.AccountExpirationDate = unixtime;
                                    data.LastActivity = lastActivity;
                                }
                                break;
                            }
                        }
                    }
                }

                // Certificates
                browserAPI.log("Parse Certificates");
                let subAccounts = [];
                let certificates = response.CertificateActivity;
                // console.log("---------------- data ----------------");
                // console.log(certificates);
                // console.log("---------------- data ----------------");
                if (typeof certificates !== 'undefined') {
                    for (let i in certificates) {
                        if (certificates.hasOwnProperty(i)) {
                            let t = certificates[i];
                            if (t.status && t.CertificateNumber && t.Status.toLowerCase() === 'issued') {

                                let expirationDate = new Date(t.ExpirationDate + ' UTC');
                                let unixtime = expirationDate / 1000;

                                subAccounts.push({
                                    'Code': 'redroofCertificate' . t.CertificateNumber,
                                    'DisplayName': 'Cert #' + t.CertificateNumber,
                                    'Balance': null,
                                    'ExpirationDate': unixtime,
                                    'IssuedTo': t.issueDate,
                                    'StatusCertificate': t.Status
                                });
                            }
                        }
                    }
                }

                // Save properties
                params.account.properties = data;
                params.account.properties.SubAccounts = subAccounts;
                params.account.properties.CombineSubAccounts = 'false';
                //console.log(params.account.properties);
                provider.saveProperties(params.account.properties);
                provider.complete();
            }
        });

    }


};
