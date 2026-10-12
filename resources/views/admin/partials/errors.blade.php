@if ($errors->any())
    <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
        <p class="font-semibold mb-1">Please fix the following:</p>
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif