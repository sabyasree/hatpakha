{{--
  Shown while a deploy is running (php artisan down --render="errors::503") and for any 503.
  Pre-rendered into storage/framework/maintenance.php, so it must not need Vite, the database
  or a session: inline styles only, palette colours only (docs/DESIGN.md).
--}}
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta http-equiv="refresh" content="30">
    <title>একটু পরেই ফিরছি · হাতপাখা</title>
    <link rel="stylesheet" href="/fonts/fonts.css?v=1">
    <style>
        html, body { margin: 0; height: 100%; }
        body {
            background: #FFFBF4; color: #13212C;
            font-family: 'Anek Bangla', 'Noto Sans Bengali', system-ui, sans-serif;
            display: flex; align-items: center; justify-content: center; text-align: center;
        }
        main { max-width: 32rem; padding: 2rem 1rem; }
        img { width: 5rem; height: auto; }
        h1 { font-size: 1.75rem; margin: 1.25rem 0 .75rem; color: #CB1B32; }
        p { font-size: 1.125rem; line-height: 1.7; margin: 0 0 .75rem; color: #4D5A63; }
        .rule { width: 4rem; height: 3px; background: #E39A6A; border: 0; margin: 1.5rem auto; }
        small { color: #4D5A63; }
    </style>
</head>
<body>
    <main>
        <img src="/images/logo.png" alt="হাতপাখা">
        <h1>একটু পরেই ফিরছি</h1>
        <p>হাতপাখা এখন নতুন করে সাজানো হচ্ছে। কয়েক মিনিটের মধ্যেই আবার সব খুলে যাবে।</p>
        <p>আপনার লেখা, মন্তব্য, অ্যাকাউন্ট: সব নিরাপদে আছে।</p>
        <hr class="rule">
        <small>পাতাটি নিজে থেকেই আবার চেষ্টা করবে।</small>
    </main>
</body>
</html>
