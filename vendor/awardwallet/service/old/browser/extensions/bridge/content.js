console.log('[bc] running content.js');

function sendResponseToPage(response) {
    console.log('[bc] sendResponseToPage', response)
    responseElement.dispatchEvent(new CustomEvent(
        'GotResponseEvent',
        {
            bubbles: false,
            cancellable: false,
            detail: JSON.stringify(response),
        },
    ));
}

function onBackgroundResponse(response) {
    console.log('[bc] received bg response');
    console.log(response, chrome.runtime.lastError);
    if (response === null) {
        sendResponseToPage([false, chrome.runtime.lastError]);
        return;
    }
    sendResponseToPage([true, response]);
}

function onMessageFromPage(e) {
    console.log('[bc] click received');
    const request = JSON.parse(e.detail);
    console.log('[bc] request', e.detail);
    chrome.runtime.sendMessage(request, onBackgroundResponse);
}

const requestElement = document.createElement("%tag%");
requestElement.id = '%request-element-id%';
requestElement.addEventListener('GotRequestEvent', onMessageFromPage);
requestElement.style.display = 'none';
document.body.appendChild(requestElement);

const responseElement = document.createElement("%tag%");
responseElement.id = '%response-element-id%';
responseElement.style.display = 'none';
document.body.appendChild(responseElement);

console.log('[bc] control elements created, %request-element-id%, %response-element-id%');