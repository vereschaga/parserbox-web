import {DesktopExtensionInterface} from "@awardwallet/extension-client/dist/DesktopExtensionInterface"
import {Centrifuge} from 'centrifuge'

const bridge = new DesktopExtensionInterface();

function connectExtensionChannel(centrifugoJwtToken:string, sessionId:string) {
    // @ts-ignore
    var runInSingleTab = document.querySelector('#form_runInSingleTab')?.checked || false;
    bridge.connect(
        centrifugoJwtToken,
        sessionId,
        (message) => {
            alert(message)
        },
        (message) => {
            console.log('complete', message)
        },
        runInSingleTab
    )
}

function connectLog(debugChannelJwtToken:string) {
    const centrifuge = new Centrifuge(bridge.getTransports(), {
        token: debugChannelJwtToken,
        emulationEndpoint: '/connection/emulation',
    });

    centrifuge.on('connected', function(ctx) {
        console.log('debug channel connected')
    });

    centrifuge.on('publication', async function(ctx) {
        const payload = ctx.data;
        const logDiv = document.getElementById('log') as HTMLElement
        const element = document.createElement('div')
        let html = payload.formatted
        html = html.replace(/writing logs to ([^\s<>]+)/g, 'writing logs to <a href="/admin/common/logFile.php?Dir=$1&File=log.html" target="_blank">$1</a>')
        element.innerHTML = html
        logDiv.append(element)
    });

    centrifuge.connect()
}

function log(message:string, level:string = 'INFO') {
    const logDiv = document.getElementById('log') as HTMLElement
    const element = document.createElement('div')
    element.innerText = message
    element.className = 'log-' + level.toLowerCase()
    logDiv.append(element)
}

async function checkExtensionInstalled()
{
    console.log('checkExtensionInstalled start')
    const info = await bridge.getExtensionInfo()
    console.log('checkExtensionInstalled', info)
    if (!info.installed) {
        (document.getElementById('extension-install') as HTMLElement).style.display = 'block'
    }

    return info.installed
}

export {connectExtensionChannel, connectLog, checkExtensionInstalled}
