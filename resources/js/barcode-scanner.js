import { BrowserMultiFormatReader } from '@zxing/browser';

let modal = null;
let video = null;
let reader = null;
let controls = null;

function ensureModal() {
    if (modal) {
        return;
    }

    modal = document.createElement('div');
    modal.id = 'barcode-scanner-modal';
    modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,15,14,0.85);align-items:center;justify-content:center;flex-direction:column;padding:1rem;';
    modal.innerHTML = `
        <div style="background:#fff;border-radius:16px;padding:16px;max-width:420px;width:100%;display:flex;flex-direction:column;align-items:center;gap:12px;">
            <video id="barcode-scanner-video" muted playsinline style="width:100%;border-radius:12px;background:#000;aspect-ratio:1/1;object-fit:cover;"></video>
            <p id="barcode-scanner-message" style="font-size:14px;color:#57534e;margin:0;text-align:center;">Point the camera at a barcode or QR code</p>
            <button type="button" id="barcode-scanner-cancel" style="width:100%;padding:10px;border-radius:10px;background:#f5f5f4;color:#292524;font-weight:600;border:none;cursor:pointer;">Cancel</button>
        </div>
    `;
    document.body.appendChild(modal);

    video = modal.querySelector('#barcode-scanner-video');
    modal.querySelector('#barcode-scanner-cancel').addEventListener('click', () => close());
}

function close() {
    if (controls) {
        controls.stop();
        controls = null;
    }
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Opens a camera modal and calls onResult(text) with the first decoded
 * barcode/QR value. Silently does nothing further if the user cancels.
 */
async function open(onResult) {
    ensureModal();
    modal.style.display = 'flex';
    modal.querySelector('#barcode-scanner-message').textContent = 'Point the camera at a barcode or QR code';

    if (! reader) {
        reader = new BrowserMultiFormatReader();
    }

    try {
        controls = await reader.decodeFromConstraints(
            { video: { facingMode: 'environment' } },
            video,
            (result, error) => {
                if (result) {
                    const text = result.getText();
                    close();
                    onResult(text);
                }
            },
        );
    } catch (e) {
        modal.querySelector('#barcode-scanner-message').textContent = 'Could not access the camera: ' + e.message;
    }
}

window.BarcodeScanner = { open, close };
