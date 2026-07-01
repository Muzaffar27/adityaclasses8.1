<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Aditya Classes">
    <title>Aditya Classes - Online School Learning</title>

    <link rel="icon" type="image/png" href="/logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/pwa-icons/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    @vite(['resources/js/app.js'])
</head>

<body>
    <div id="app">
        <style>
            body {
                margin: 0;
                background: #0f172a;
                color: #fff;
                font-family: Arial, sans-serif;
            }

            .boot-fallback {
                align-items: center;
                display: flex;
                min-height: 100vh;
                justify-content: center;
                padding: 24px;
                text-align: center;
            }

            .boot-fallback-card {
                background: rgba(255, 255, 255, 0.06);
                border: 1px solid rgba(255, 255, 255, 0.12);
                border-radius: 14px;
                max-width: 360px;
                padding: 22px;
            }

            .boot-fallback-card h1 {
                font-size: 20px;
                margin: 0 0 8px;
            }

            .boot-fallback-card p {
                color: #cbd5e1;
                font-size: 14px;
                line-height: 1.5;
                margin: 0 0 16px;
            }

            .boot-fallback-card button {
                background: #4f46e5;
                border: 0;
                border-radius: 8px;
                color: #fff;
                cursor: pointer;
                font-weight: 700;
                padding: 10px 14px;
            }
        </style>
        <div class="boot-fallback">
            <div class="boot-fallback-card">
                <h1>Loading Aditya Classes</h1>
                <p>If this stays here, refresh once. If it still does not open, clear this app/site from your browser cache and try again.</p>
                <button type="button" onclick="window.location.reload()">Refresh</button>
            </div>
        </div>
    </div>
</body>

</html>
