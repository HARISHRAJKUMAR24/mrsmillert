<?php
/*
|--------------------------------------------------------------------------
| bluetooth-print.php
|--------------------------------------------------------------------------
| Android Chrome + BLE Thermal Printer test
|--------------------------------------------------------------------------
*/
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Bluetooth Thermal Printer</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f5f7;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            margin: auto;
        }

        .card {
            background: #fff;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .08);
        }

        h2 {
            margin: 0 0 8px;
        }

        .status {
            padding: 12px;
            border-radius: 10px;
            background: #f1f3f5;
            margin: 15px 0;
            font-size: 14px;
        }

        button {
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 12px;
            margin-top: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .connect {
            background: #111827;
            color: #fff;
        }

        .print {
            background: #16a34a;
            color: #fff;
        }

        .disconnect {
            background: #dc2626;
            color: #fff;
        }

        button:disabled {
            opacity: .45;
        }

        .receipt {
            margin-top: 20px;
            padding: 18px;
            background: #fff;
            border: 1px dashed #aaa;
            font-family: monospace;
            white-space: pre-wrap;
            font-size: 13px;
        }
    </style>
</head>

<body>

    <div class="container">

        <div class="card">

            <h2>🖨️ Thermal Printer</h2>

            <div id="status" class="status">
                Printer not connected
            </div>

            <button
                class="connect"
                id="connectBtn"
                onclick="connectPrinter()">

                🔵 Connect Bluetooth Printer

            </button>

            <button
                class="print"
                id="printBtn"
                onclick="printReceipt()"
                disabled>

                🖨️ Print Receipt

            </button>

            <button
                class="disconnect"
                onclick="disconnectPrinter()">

                Disconnect

            </button>

            <div class="receipt">

                Mrs Mill@ Machanandapuram

                Cash Bill
                --------------------------------
                Customer : Keerthika Prasanna
                Phone : +919003155506

                Invoice : MM119
                Date : 06/10/2026

                --------------------------------
                PREMIUM WHITE
                1Kg

                Qty : 2
                Price : ₹60
                Amount : ₹120

                --------------------------------
                TOTAL : ₹120

                --------------------------------

            </div>

        </div>

    </div>


    <script>
        /*
|--------------------------------------------------------------------------
| Bluetooth state
|--------------------------------------------------------------------------
*/

        let bluetoothDevice = null;
        let bluetoothServer = null;
        let printerCharacteristic = null;


        /*
        |--------------------------------------------------------------------------
        | Common BLE UUIDs
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | These are examples.
        | Your printer may use different UUIDs.
        |
        */

        const SERVICE_UUID =
            "0000ff00-0000-1000-8000-00805f9b34fb";

        const CHARACTERISTIC_UUID =
            "0000ff02-0000-1000-8000-00805f9b34fb";


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        function setStatus(message) {

            document.getElementById("status").innerText =
                message;

        }


        /*
        |--------------------------------------------------------------------------
        | CONNECT
        |--------------------------------------------------------------------------
        */

        async function connectPrinter() {

            try {

                if (!navigator.bluetooth) {

                    alert(
                        "Bluetooth Web API is not supported in this browser."
                    );

                    return;
                }


                setStatus(
                    "Searching for Bluetooth printer..."
                );


                /*
                |--------------------------------------------------------------------------
                | Ask Android Chrome for Bluetooth device
                |--------------------------------------------------------------------------
                */

                bluetoothDevice =
                    await navigator.bluetooth.requestDevice({

                        acceptAllDevices: true,

                        optionalServices: [
                            SERVICE_UUID
                        ]

                    });


                console.log(
                    "Device:",
                    bluetoothDevice.name
                );


                setStatus(
                    "Connecting to " +
                    (bluetoothDevice.name || "printer") +
                    "..."
                );


                /*
                |--------------------------------------------------------------------------
                | Connect GATT
                |--------------------------------------------------------------------------
                */

                bluetoothServer =
                    await bluetoothDevice.gatt.connect();


                /*
                |--------------------------------------------------------------------------
                | Get printer service
                |--------------------------------------------------------------------------
                */

                const service =
                    await bluetoothServer.getPrimaryService(
                        SERVICE_UUID
                    );


                /*
                |--------------------------------------------------------------------------
                | Get write characteristic
                |--------------------------------------------------------------------------
                */

                printerCharacteristic =
                    await service.getCharacteristic(
                        CHARACTERISTIC_UUID
                    );


                setStatus(
                    "✅ Connected: " +
                    (bluetoothDevice.name || "Bluetooth Printer")
                );


                document.getElementById(
                    "printBtn"
                ).disabled = false;


                bluetoothDevice.addEventListener(
                    "gattserverdisconnected",
                    printerDisconnected
                );


            } catch (error) {

                console.error(error);

                setStatus(
                    "❌ Connection failed: " +
                    error.message
                );

                alert(
                    "Bluetooth connection failed.\n\n" +
                    error.message
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | DISCONNECTED
        |--------------------------------------------------------------------------
        */

        function printerDisconnected() {

            printerCharacteristic = null;

            document.getElementById(
                "printBtn"
            ).disabled = true;

            setStatus(
                "Printer disconnected"
            );

        }


        /*
        |--------------------------------------------------------------------------
        | SEND DATA
        |--------------------------------------------------------------------------
        */

        async function sendData(data) {

            if (!printerCharacteristic) {

                throw new Error(
                    "Printer is not connected."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Thermal printers usually have a small packet limit.
            | Send data in chunks.
            |--------------------------------------------------------------------------
            */

            const chunkSize = 100;

            for (
                let i = 0; i < data.length; i += chunkSize
            ) {

                const chunk =
                    data.slice(i, i + chunkSize);


                await printerCharacteristic
                    .writeValue(chunk);


                await new Promise(
                    resolve => setTimeout(resolve, 20)
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | TEXT → BYTES
        |--------------------------------------------------------------------------
        */

        function textToBytes(text) {

            return new TextEncoder().encode(text);

        }


        /*
        |--------------------------------------------------------------------------
        | PRINT RECEIPT
        |--------------------------------------------------------------------------
        */

        async function printReceipt() {

            try {

                if (!printerCharacteristic) {

                    alert(
                        "Please connect the printer first."
                    );

                    return;

                }


                setStatus(
                    "Printing receipt..."
                );


                /*
                |--------------------------------------------------------------------------
                | ESC/POS commands
                |--------------------------------------------------------------------------
                */

                const ESC = "\x1B";
                const GS = "\x1D";


                let receipt = "";


                // Initialize
                receipt += ESC + "@";


                // Center
                receipt += ESC + "a" + "\x01";


                // Bold
                receipt += ESC + "E" + "\x01";

                receipt +=
                    "Mrs Mill@ Machanandapuram\n";


                receipt += ESC + "E" + "\x00";

                receipt +=
                    "Ph.No.: 363752025\n";

                receipt +=
                    "Cash Bill\n";

                receipt +=
                    "--------------------------------\n";


                // Left
                receipt += ESC + "a" + "\x00";


                receipt +=
                    "Customer : Keerthika Prasanna\n";

                receipt +=
                    "Phone    : +919003155506\n";

                receipt +=
                    "Invoice  : MM119\n";

                receipt +=
                    "Date     : 06/10/2026\n";


                receipt +=
                    "--------------------------------\n";


                receipt +=
                    "Item             Qty Price Amount\n";

                receipt +=
                    "PREMIUM WHITE      2   60    120\n";

                receipt +=
                    "1Kg\n";


                receipt +=
                    "--------------------------------\n";


                // Bold total
                receipt += ESC + "E" + "\x01";

                receipt +=
                    "TOTAL                  Rs.120\n";

                receipt += ESC + "E" + "\x00";


                receipt +=
                    "--------------------------------\n";


                // Center
                receipt += ESC + "a" + "\x01";


                receipt +=
                    "\n";

                receipt +=
                    "Available Point: 0\n";

                receipt +=
                    "\n";

                receipt +=
                    "Scan this QR code to pay\n";

                receipt +=
                    "\n\n";


                receipt +=
                    "Mrs Mill@ Happy to serve you!\n";


                receipt +=
                    "\n\n\n";


                /*
                |--------------------------------------------------------------------------
                | Cut paper
                |--------------------------------------------------------------------------
                */

                receipt +=
                    GS + "V" + "\x00";


                /*
                |--------------------------------------------------------------------------
                | Convert to bytes
                |--------------------------------------------------------------------------
                */

                const bytes =
                    textToBytes(receipt);


                /*
                |--------------------------------------------------------------------------
                | Send to printer
                |--------------------------------------------------------------------------
                */

                await sendData(bytes);


                setStatus(
                    "✅ Receipt sent to printer"
                );


            } catch (error) {

                console.error(error);

                setStatus(
                    "❌ Print failed: " +
                    error.message
                );

                alert(
                    "Print failed.\n\n" +
                    error.message
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | DISCONNECT
        |--------------------------------------------------------------------------
        */

        function disconnectPrinter() {

            try {

                if (
                    bluetoothDevice &&
                    bluetoothDevice.gatt.connected
                ) {

                    bluetoothDevice.gatt.disconnect();

                }

            } catch (error) {

                console.error(error);

            }

            bluetoothDevice = null;
            bluetoothServer = null;
            printerCharacteristic = null;

            document.getElementById(
                "printBtn"
            ).disabled = true;

            setStatus(
                "Printer disconnected"
            );

        }
    </script>

</body>

</html>