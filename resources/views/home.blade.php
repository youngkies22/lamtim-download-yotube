<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Download Video YouTube, TikTok, Instagram, Facebook</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    <main class="max-w-2xl mx-auto px-4 py-12">
        <h1 class="text-3xl font-semibold text-center mb-2">Download Video</h1>
        <p class="text-center text-gray-500 dark:text-gray-400 mb-8">
            Tempel link YouTube, TikTok, Instagram, atau Facebook lalu klik Cek Video.
        </p>

        <form id="info-form" class="flex flex-col sm:flex-row gap-2 mb-6">
            <input
                type="url"
                id="url-input"
                name="url"
                required
                placeholder="https://..."
                class="flex-1 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-4 py-2 focus:outline-none focus:ring focus:ring-blue-300"
            >
            <button
                type="submit"
                id="info-button"
                class="rounded-md bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 px-6 py-2 font-medium disabled:opacity-50"
            >
                Cek Video
            </button>
        </form>

        <div id="error-box" class="hidden mb-6 rounded-md border border-red-300 bg-red-50 dark:bg-red-950 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 text-sm"></div>

        <div id="result-card" class="hidden rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex gap-4">
                <img id="result-thumbnail" src="" alt="" class="w-32 h-20 object-cover rounded-md bg-gray-100 dark:bg-gray-800 shrink-0">
                <div class="min-w-0">
                    <p id="result-platform" class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1"></p>
                    <p id="result-title" class="font-medium leading-snug break-words"></p>
                    <p id="result-duration" class="text-sm text-gray-500 dark:text-gray-400 mt-1"></p>
                </div>
            </div>

            <div id="quality-list" class="mt-4 flex flex-wrap gap-2"></div>
        </div>

        <form id="download-form" method="POST" action="/api/download" class="hidden">
            @csrf
            <input type="hidden" name="url">
            <input type="hidden" name="quality">
        </form>
    </main>

    <script>
        const infoForm = document.getElementById('info-form');
        const urlInput = document.getElementById('url-input');
        const infoButton = document.getElementById('info-button');
        const errorBox = document.getElementById('error-box');
        const resultCard = document.getElementById('result-card');
        const qualityList = document.getElementById('quality-list');
        const downloadForm = document.getElementById('download-form');

        function showError(message) {
            errorBox.textContent = message;
            errorBox.classList.remove('hidden');
        }

        function hideError() {
            errorBox.classList.add('hidden');
        }

        function formatDuration(seconds) {
            if (!seconds) return '';
            const m = Math.floor(seconds / 60);
            const s = Math.floor(seconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        }

        infoForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideError();
            resultCard.classList.add('hidden');
            qualityList.innerHTML = '';
            infoButton.disabled = true;
            infoButton.textContent = 'Memeriksa...';

            try {
                const response = await fetch('/api/video-info', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ url: urlInput.value }),
                });

                const data = await response.json();

                if (!response.ok) {
                    showError(data.message || 'Terjadi kesalahan.');
                    return;
                }

                document.getElementById('result-thumbnail').src = data.thumbnail || '';
                document.getElementById('result-title').textContent = data.title;
                document.getElementById('result-platform').textContent = data.platform || '';
                document.getElementById('result-duration').textContent = formatDuration(data.duration);

                data.qualities.forEach((q) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = q.label;
                    btn.className = 'rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-800';
                    btn.addEventListener('click', () => {
                        downloadForm.querySelector('[name=url]').value = urlInput.value;
                        downloadForm.querySelector('[name=quality]').value = q.key;
                        downloadForm.submit();
                    });
                    qualityList.appendChild(btn);
                });

                resultCard.classList.remove('hidden');
            } catch (err) {
                showError('Tidak bisa terhubung ke server.');
            } finally {
                infoButton.disabled = false;
                infoButton.textContent = 'Cek Video';
            }
        });
    </script>
</body>
</html>
