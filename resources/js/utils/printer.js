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

        const device = await navigator.bluetooth.requestDevice({
            filters: [{ services: ['000018f0-0000-1000-8000-00805f9b34fb'] }],
            optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb'] // Common thermal printer service UUID
        });

        const server = await device.gatt.connect();
        const service = await server.getPrimaryService('000018f0-0000-1000-8000-00805f9b34fb');
        const characteristic = await service.getCharacteristic('00002af1-0000-1000-8000-00805f9b34fb'); // Write characteristic

        // Helper to send data in chunks to prevent overflowing the printer buffer
        const sendChunks = async (data) => {
            const CHUNK_SIZE = 512;
            for (let i = 0; i < data.length; i += CHUNK_SIZE) {
                const chunk = data.slice(i, i + CHUNK_SIZE);
                await characteristic.writeValue(new Uint8Array(chunk));
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
