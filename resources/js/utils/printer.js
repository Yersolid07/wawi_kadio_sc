/**
 * Web Bluetooth API implementation for Thermal Printers (ESC/POS)
 * Note: Web Bluetooth is only supported on Chrome, Edge, Opera, Android Chrome/Samsung Internet.
 * Not supported on iOS or Firefox/Safari desktop.
 */
export async function connectAndPrint(printData) {
    try {
        if (!navigator.bluetooth) {
            throw new Error('Web Bluetooth API tidak didukung di browser ini. Gunakan Chrome atau Edge.');
        }

        let device = null;
        let server = null;
        
        // Try to reconnect to a previously permitted device first
        if (navigator.bluetooth.getDevices) {
            const devices = await navigator.bluetooth.getDevices();
            for (const d of devices) {
                try {
                    server = await d.gatt.connect();
                    device = d;
                    break; // Connected successfully!
                } catch (e) {
                    // This device is offline or unreachable, try the next one
                    console.log('Skipping saved device', d.name, e);
                }
            }
        }

        // If no saved device could be connected, prompt the user
        if (!device || !server) {
            device = await navigator.bluetooth.requestDevice({
                acceptAllDevices: true,
                optionalServices: [
                    '000018f0-0000-1000-8000-00805f9b34fb', // Standard BLE Printer
                    'e7810a71-73ae-499d-8c15-faa9aef0c3f2', // Common Chinese Printer
                    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // Other Chinese Printer
                    '0000ff00-0000-1000-8000-00805f9b34fb', // Vendor specific
                    '0000ae30-0000-1000-8000-00805f9b34fb',
                    '0000af30-0000-1000-8000-00805f9b34fb',
                    '00001101-0000-1000-8000-00805f9b34fb', // SPP Generic Profile
                    '0000180a-0000-1000-8000-00805f9b34fb'  // Device Info
                ]
            });
            server = await device.gatt.connect();
        }
        
        let targetCharacteristic = null;
        const services = await server.getPrimaryServices();
        for (const service of services) {
            const characteristics = await service.getCharacteristics();
            for (const char of characteristics) {
                if (char.properties.write || char.properties.writeWithoutResponse) {
                    targetCharacteristic = char;
                    break;
                }
            }
            if (targetCharacteristic) break;
        }

        if (!targetCharacteristic) {
            throw new Error('Tidak dapat menemukan service printer bluetooth yang bisa ditulis (writable).');
        }

        // Helper to send data in chunks to prevent overflowing the printer buffer
        const sendChunks = async (data) => {
            const CHUNK_SIZE = 512;
            for (let i = 0; i < data.length; i += CHUNK_SIZE) {
                const chunk = data.slice(i, i + CHUNK_SIZE);
                await targetCharacteristic.writeValue(new Uint8Array(chunk));
                await new Promise(resolve => setTimeout(resolve, 50)); // Small delay between chunks
            }
        };

        await sendChunks(printData);
        device.gatt.disconnect();
        
        return { success: true };
    } catch (error) {
        console.error('Print error:', error);
        return { success: false, error: error.message };
    }
}

/**
 * Helper to build an ESC/POS receipt string buffer from order details
 */
export function buildReceiptBuffer(order, items, total, companyName = 'WAWI KADIO', address = 'Jl. Pariwisata No. 1, Minahasa, Sulut') {
    const ESC = 0x1B;
    const GS = 0x1D;

    const commands = [];

    // Helpers
    const text = (str) => commands.push(...new TextEncoder().encode(str));
    const newline = () => text('\n');
    const boldOn = () => commands.push(ESC, 0x45, 1);
    const boldOff = () => commands.push(ESC, 0x45, 0);
    const centerAlign = () => commands.push(ESC, 0x61, 1);
    const leftAlign = () => commands.push(ESC, 0x61, 0);
    const rightAlign = () => commands.push(ESC, 0x61, 2);

    const padRight = (str, len) => str.length >= len ? str.substring(0, len) : str + ' '.repeat(len - str.length);
    const padLeft = (str, len) => str.length >= len ? str.substring(0, len) : ' '.repeat(len - str.length) + str;

    // Initialize printer
    commands.push(ESC, 0x40);

    // Header
    centerAlign();
    boldOn();
    text(companyName);
    newline();
    boldOff();
    text('Resort & Resto');
    newline();
    text('Jl. Pariwisata No. 1, Minahasa,');
    newline();
    text('Sulut');
    newline();
    
    text('--------------------------------');
    newline();

    // Meta Data
    leftAlign();
    
    let orderDate = order.date ? new Date(order.date) : new Date();
    let dateStr = `${orderDate.getDate().toString().padStart(2, '0')}/${(orderDate.getMonth() + 1).toString().padStart(2, '0')}/${orderDate.getFullYear()}`;
    let timeStr = `${orderDate.getHours().toString().padStart(2, '0')}:${orderDate.getMinutes().toString().padStart(2, '0')}`;
    
    text(`Tgl: ${dateStr}`);
    newline();
    
    let cashierStr = `Kasir: ${order.cashierName || 'System'}`;
    text(`${padRight(timeStr, 32 - cashierStr.length)}${cashierStr}`);
    newline();
    
    let idStr = `ID : #ORD-${(order.id || '').substring(0, 5).toUpperCase()}`;
    let typeStr = `Tipe : ${order.orderType || 'DINE IN'}`;
    if (idStr.length + typeStr.length <= 32) {
        text(`${padRight(idStr, 32 - typeStr.length)}${typeStr}`);
    } else {
        text(idStr); newline();
        text(typeStr);
    }
    newline();
    
    let tamuStr = `Tamu: ${order.customerName || 'Walk-in'}`;
    if (order.tableNumber) tamuStr += ` | Meja: ${order.tableNumber}`;
    text(tamuStr.substring(0, 32));
    newline();
    
    text('--------------------------------');
    newline();

    // Items Header
    text(padRight('Menu', 22) + padLeft('Qty', 3) + padLeft('Total', 7));
    newline();
    
    items.forEach(item => {
        let name = item.name.substring(0, 22);
        let qty = item.quantity.toString();
        let subtotal = (item.quantity * item.price).toLocaleString('id-ID');
        
        text(padRight(name, 22) + padLeft(qty, 3) + padLeft(subtotal, 7));
        newline();
        
        if (item.notes) {
            text(`  *${item.notes.substring(0, 28)}`);
            newline();
        }
    });

    text('--------------------------------');
    newline();

    // Totals
    let subtotalStr = total.toLocaleString('id-ID');
    text(padRight('Subtotal', 32 - subtotalStr.length) + subtotalStr);
    newline();
    text('--------------------------------');
    newline();

    boldOn();
    let grandTotalStr = `Rp ${subtotalStr}`;
    text(padRight('TOTAL', 32 - grandTotalStr.length) + grandTotalStr);
    newline();
    boldOff();
    
    let rawStatus = order.paymentStatus || order.payment_status || 'unpaid';
    let isPaid = String(rawStatus).toLowerCase() === 'paid' || String(rawStatus).toLowerCase() === 'lunas' || String(rawStatus).toLowerCase() === 'success';
    let statusStr = isPaid ? 'LUNAS' : 'BELUM LUNAS';
    text(padRight('Status Bayar', 32 - statusStr.length) + statusStr);
    newline();
    
    text('--------------------------------');
    newline();

    // Footer
    centerAlign();
    text('Terima Kasih Atas Kunjungan Anda!');
    newline();
    text('Silakan tinggalkan ulasan di');
    newline();
    text('website kami.');
    newline();
    newline();
    newline();
    newline(); // Extra spaces for tear-off

    commands.push(0x0A, 0x0A, 0x0A); // 3 blank lines
    commands.push(GS, 0x56, 0x41, 0x00); // Partial cut

    return commands;
}
