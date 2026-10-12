{{-- Poster / image upload with live preview. Expects $model (with image_path / image_url). --}}
<div>
    <label class="block text-sm font-medium mb-1">{{ $label ?? 'Poster / image' }} <span class="text-gray-400 font-normal">(optional)</span></label>

    <div class="flex flex-col sm:flex-row gap-4 items-start">
        <div class="w-full sm:w-48 aspect-[4/3] rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 overflow-hidden flex items-center justify-center shrink-0">
            <img id="image-preview" src="{{ $model->image_url }}" alt=""
                 class="w-full h-full object-cover {{ $model->image_url ? '' : 'hidden' }}">
            <span id="image-placeholder" class="flex flex-col items-center text-gray-400 text-xs {{ $model->image_url ? 'hidden' : '' }}">
                <x-icon name="photo" class="w-8 h-8 mb-1" />
                No image
            </span>
        </div>

        <div class="grid gap-2 text-sm">
            <input type="file" name="image" id="image-input" accept="image/png,image/jpeg,image/webp"
                   class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-green-50 file:px-3 file:py-2 file:font-medium file:text-green-800 hover:file:bg-green-100">
            <p class="text-xs text-gray-500">JPG, PNG or WEBP. Big phone photos are shrunk automatically.</p>
            <p id="image-note" class="text-xs text-green-700 hidden"></p>

            @if ($model->image_path)
                <label class="flex items-center gap-2 text-red-700">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300">
                    Remove current image
                </label>
            @endif

            @error('image')
                <p class="text-red-600 text-sm">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

<script>
    (function () {
        var input = document.getElementById('image-input');
        var img = document.getElementById('image-preview');
        var placeholder = document.getElementById('image-placeholder');
        var note = document.getElementById('image-note');
        var MAX_SIDE = 1600;              // longest side in pixels
        var SHRINK_ABOVE = 1.5 * 1024 * 1024; // shrink files bigger than 1.5 MB

        function mb(bytes) { return (bytes / 1024 / 1024).toFixed(1) + ' MB'; }

        function showPreview(file) {
            img.src = URL.createObjectURL(file);
            img.classList.remove('hidden');
            placeholder.classList.add('hidden');
        }

        input.addEventListener('change', function () {
            var file = input.files[0];
            note.classList.add('hidden');
            if (!file) return;
            showPreview(file);

            if (file.size <= SHRINK_ABOVE || !window.DataTransfer) return;

            // Shrink the photo in the browser so it uploads fast and fits the server limit
            var source = new Image();
            source.onload = function () {
                var scale = Math.min(1, MAX_SIDE / Math.max(source.width, source.height));
                var canvas = document.createElement('canvas');
                canvas.width = Math.round(source.width * scale);
                canvas.height = Math.round(source.height * scale);
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(source, 0, 0, canvas.width, canvas.height);

                canvas.toBlob(function (blob) {
                    if (!blob || blob.size >= file.size) return;
                    var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    var smaller = new File([blob], name, { type: 'image/jpeg' });
                    var dt = new DataTransfer();
                    dt.items.add(smaller);
                    input.files = dt.files;
                    showPreview(smaller);
                    note.textContent = 'Photo resized from ' + mb(file.size) + ' to ' + mb(smaller.size) + ' for faster upload.';
                    note.classList.remove('hidden');
                }, 'image/jpeg', 0.85);
            };
            source.src = URL.createObjectURL(file);
        });
    })();
</script>