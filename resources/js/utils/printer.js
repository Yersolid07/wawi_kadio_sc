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
        
        // Try to reconnect to a previously permitted device first
        if (navigator.bluetooth.getDevices) {
            const devices = await navigator.bluetooth.getDevices();
            for (const d of devices) {
                // We assume any previously paired device starting with KPrinter or any saved device is the printer
                // Or simply try the first available one that is a printer
                device = d;
                break;
            }
        }

        if (!device) {
            device = await navigator.bluetooth.requestDevice({
                acceptAllDevices: true,
                optionalServices: [
                    '000018f0-0000-1000-8000-00805f9b34fb', // Standard BLE Printer
                    'e7810a71-73ae-499d-8c15-faa9aef0c3f2', // Common Chinese Printer
                    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // Other Chinese Printer
                    '0000ff00-0000-1000-8000-00805f9b34fb', // Vendor specific
                    '0000ae30-0000-1000-8000-00805f9b34fb',
                    '0000af30-0000-1000-8000-00805f9b34fb'
                ]
            });
        }

        const server = await device.gatt.connect();
        
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
export function buildReceiptBuffer(order, items, total, companyName = 'Wawi Kadio', address = '') {
    const ESC = 0x1B;
    const GS = 0x1D;

    const commands = [];

    // Initialize printer
    commands.push(ESC, 0x40);

    // Center alignment for header
    commands.push(ESC, 0x61, 1);
    
    // Bold on
    commands.push(ESC, 0x45, 1);
    commands.push(...new TextEncoder().encode(companyName + '\n'));
    commands.push(ESC, 0x45, 0); // Bold off

    if (address) {
        commands.push(...new TextEncoder().encode(address + '\n'));
    }
    
    commands.push(...new TextEncoder().encode('--------------------------------\n'));

    // Left alignment for body
    commands.push(ESC, 0x61, 0);

    commands.push(...new TextEncoder().encode(`Waktu: ${new Date().toLocaleString('id-ID')}\n`));
    commands.push(...new TextEncoder().encode(`Pelanggan: ${order.customerName}\n`));
    commands.push(...new TextEncoder().encode('--------------------------------\n'));

    // Items
    items.forEach(item => {
        let name = item.name.substring(0, 32); // Truncate long names
        commands.push(...new TextEncoder().encode(`${name}\n`));
        
        let qtyPrice = `${item.quantity}x ${item.price.toLocaleString('id-ID')}`;
        let subtotal = (item.quantity * item.price).toLocaleString('id-ID');
        
        // Pad with spaces for alignment
        let spaces = 32 - (qtyPrice.length + subtotal.length);
        if (spaces < 1) spaces = 1;
        
        commands.push(...new TextEncoder().encode(`${qtyPrice}${' '.repeat(spaces)}${subtotal}\n`));
    });

    commands.push(...new TextEncoder().encode('--------------------------------\n'));
    
    // Total
    commands.push(ESC, 0x61, 2); // Right align
    commands.push(ESC, 0x45, 1); // Bold on
    commands.push(...new TextEncoder().encode(`TOTAL: Rp ${total.toLocaleString('id-ID')}\n`));
    commands.push(ESC, 0x45, 0); // Bold off

    commands.push(...new TextEncoder().encode('\nTerima Kasih!\n'));

    // Feed lines and cut
    commands.push(0x0A, 0x0A, 0x0A); // 3 blank lines
    commands.push(GS, 0x56, 0x41, 0x00); // Partial cut

    return commands;
}
